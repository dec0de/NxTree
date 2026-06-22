<?php

declare(strict_types=1);

namespace OCA\NxTree\Service;

use InvalidArgumentException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final class TreeService {
    public function __construct(private IDBConnection $db) {
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    public function listTrees(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('id', 'title', 'root_node_id', 'revision', 'created_at', 'updated_at')
            ->from('nxtree_trees')
            ->where($qb->expr()->eq('owner_user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->isNull('deleted_at'))
            ->orderBy('updated_at', 'DESC')
            ->executeQuery();

        $trees = [];
        while (($row = $result->fetch()) !== false) {
            $trees[] = $this->formatTree($row);
        }
        $result->closeCursor();

        return $trees;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function createTree(string $userId, string $title): array {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('Tree title is required');
        }

        if (mb_strlen($title) > 255) {
            throw new InvalidArgumentException('Tree title must be 255 characters or fewer');
        }

        $now = time();
        $this->db->beginTransaction();

        try {
            $treeId = $this->insertTree($userId, $title, $now);
            $rootNodeId = $this->insertRootNode($treeId, $title, $now);
            $this->activateTree($treeId, $rootNodeId, $now);
            $this->insertOperation($treeId, $userId, 1, 'createTree', [
                'title' => $title,
                'rootNodeId' => $rootNodeId,
            ], $now);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'id' => $treeId,
            'title' => $title,
            'rootNodeId' => $rootNodeId,
            'revision' => 1,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTree(string $userId, int $treeId): ?array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('id', 'title', 'root_node_id', 'revision', 'created_at', 'updated_at')
            ->from('nxtree_trees')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('owner_user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->isNull('deleted_at'))
            ->executeQuery();

        $row = $result->fetch();
        $result->closeCursor();
        if ($row === false) {
            return null;
        }

        $tree = $this->formatTree($row);
        $tree['nodes'] = $this->listNodes($treeId);

        return $tree;
    }

    private function insertTree(string $userId, string $title, int $now): int {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('nxtree_trees')
            ->values([
                'owner_user_id' => $qb->createNamedParameter($userId),
                'title' => $qb->createNamedParameter($title),
                'revision' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
                'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
                'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
            ])
            ->executeStatement();

        return (int)$this->db->lastInsertId('nxtree_trees');
    }

    private function insertRootNode(int $treeId, string $title, int $now): int {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('nxtree_nodes')
            ->values([
                'tree_id' => $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT),
                'parent_id' => $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
                'sort_order' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
                'title' => $qb->createNamedParameter($title),
                'content_markdown' => $qb->createNamedParameter(''),
                'version' => $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT),
                'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
                'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
            ])
            ->executeStatement();

        return (int)$this->db->lastInsertId('nxtree_nodes');
    }

    private function activateTree(int $treeId, int $rootNodeId, int $now): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('nxtree_trees')
            ->set('root_node_id', $qb->createNamedParameter($rootNodeId, IQueryBuilder::PARAM_INT))
            ->set('revision', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
            ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    /**
     * @param array<string, int|string|null> $payload
     */
    private function insertOperation(int $treeId, string $userId, int $revision, string $type, array $payload, int $now): void {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('nxtree_operations')
            ->values([
                'tree_id' => $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT),
                'revision' => $qb->createNamedParameter($revision, IQueryBuilder::PARAM_INT),
                'user_id' => $qb->createNamedParameter($userId),
                'type' => $qb->createNamedParameter($type),
                'payload_json' => $qb->createNamedParameter(json_encode($payload, JSON_THROW_ON_ERROR)),
                'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
            ])
            ->executeStatement();
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    private function listNodes(int $treeId): array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('id', 'parent_id', 'sort_order', 'title', 'content_markdown', 'version', 'created_at', 'updated_at')
            ->from('nxtree_nodes')
            ->where($qb->expr()->eq('tree_id', $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->isNull('deleted_at'))
            ->orderBy('parent_id', 'ASC')
            ->addOrderBy('sort_order', 'ASC')
            ->addOrderBy('id', 'ASC')
            ->executeQuery();

        $nodes = [];
        while (($row = $result->fetch()) !== false) {
            $nodes[] = [
                'id' => (int)$row['id'],
                'parentId' => $row['parent_id'] === null ? null : (int)$row['parent_id'],
                'sortOrder' => (int)$row['sort_order'],
                'title' => (string)$row['title'],
                'contentMarkdown' => (string)($row['content_markdown'] ?? ''),
                'version' => (int)$row['version'],
                'createdAt' => (int)$row['created_at'],
                'updatedAt' => (int)$row['updated_at'],
            ];
        }
        $result->closeCursor();

        return $nodes;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, int|string|null>
     */
    private function formatTree(array $row): array {
        return [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'rootNodeId' => $row['root_node_id'] === null ? null : (int)$row['root_node_id'],
            'revision' => (int)$row['revision'],
            'createdAt' => (int)$row['created_at'],
            'updatedAt' => (int)$row['updated_at'],
        ];
    }
}
