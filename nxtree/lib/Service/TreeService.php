<?php

declare(strict_types=1);

namespace OCA\NxTree\Service;

use InvalidArgumentException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use UnexpectedValueException;

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
     * @return array<string, mixed>
     */
    public function importMtre(string $userId, string $contents, string $fallbackTitle): array {
        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Import file is not valid MeeTree JSON');
        }

        $root = $decoded['root'] ?? $decoded;
        if (!is_array($root)) {
            throw new InvalidArgumentException('Import file does not contain a root node');
        }

        $title = $this->nodeTitle($root, $fallbackTitle !== '' ? $fallbackTitle : 'Imported tree');
        $now = time();
        $nodeCount = 0;
        $this->db->beginTransaction();

        try {
            $treeId = $this->insertTree($userId, $title, $now);
            $rootNodeId = $this->insertImportedNode($treeId, null, 0, $root, $now, $nodeCount);
            $this->activateTree($treeId, $rootNodeId, $now);
            $this->insertOperation($treeId, $userId, 1, 'importTree', [
                'title' => $title,
                'rootNodeId' => $rootNodeId,
                'nodeCount' => $nodeCount,
                'format' => 'mtre',
            ], $now);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $tree = $this->getTree($userId, $treeId);
        if ($tree === null) {
            throw new InvalidArgumentException('Imported tree could not be loaded');
        }

        return $tree;
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

    /**
     * @return array<string, mixed>
     */
    public function updateNode(string $userId, int $nodeId, string $title, string $contentMarkdown, int $baseRevision): array {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('Node title is required');
        }
        if (mb_strlen($title) > 255) {
            throw new InvalidArgumentException('Node title must be 255 characters or fewer');
        }

        $now = time();
        $this->db->beginTransaction();

        try {
            $node = $this->nodeRow($nodeId);
            if ($node === null) {
                throw new InvalidArgumentException('Node not found');
            }

            $tree = $this->treeRow((int)$node['tree_id']);
            if ($tree === null || (string)$tree['owner_user_id'] !== $userId) {
                throw new InvalidArgumentException('Node not found');
            }

            $currentRevision = (int)$tree['revision'];
            if ($baseRevision !== $currentRevision) {
                throw new UnexpectedValueException('Tree changed elsewhere. Reload before saving this node.');
            }

            $newRevision = $currentRevision + 1;
            $this->updateNodeRow($nodeId, $title, $contentMarkdown, (int)$node['version'] + 1, $now);
            $this->updateTreeRevision((int)$tree['id'], $newRevision, $now);
            if ((int)$tree['root_node_id'] === $nodeId) {
                $this->updateTreeTitle((int)$tree['id'], $title);
            }
            $this->insertOperation((int)$tree['id'], $userId, $newRevision, 'updateNode', [
                'nodeId' => $nodeId,
                'title' => $title,
            ], $now);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $updated = $this->getTree($userId, (int)$tree['id']);
        if ($updated === null) {
            throw new InvalidArgumentException('Updated tree could not be loaded');
        }

        return $updated;
    }

    /**
     * @return array<string, mixed>
     */
    public function addNode(string $userId, int $parentId, int $baseRevision): array {
        $now = time();
        $this->db->beginTransaction();

        try {
            [$parent, $tree] = $this->nodeContext($userId, $parentId, $baseRevision);
            $treeId = (int)$tree['id'];
            $newRevision = (int)$tree['revision'] + 1;
            $nodeId = $this->insertChildNode($treeId, $parentId, -1, 'New node', $now);
            $siblings = $this->childRows($treeId, $parentId);
            usort($siblings, static function (array $left, array $right) use ($nodeId): int {
                if ((int)$left['id'] === $nodeId) {
                    return -1;
                }
                if ((int)$right['id'] === $nodeId) {
                    return 1;
                }
                return ((int)$left['sort_order'] <=> (int)$right['sort_order']) ?: ((int)$left['id'] <=> (int)$right['id']);
            });
            $this->writeSiblingOrder($siblings);
            $this->updateTreeRevision($treeId, $newRevision, $now);
            $this->insertOperation($treeId, $userId, $newRevision, 'addNode', [
                'nodeId' => $nodeId,
                'parentId' => (int)$parent['id'],
            ], $now);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->loadedTree($userId, $treeId);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteNode(string $userId, int $nodeId, int $baseRevision): array {
        $now = time();
        $this->db->beginTransaction();

        try {
            [$node, $tree] = $this->nodeContext($userId, $nodeId, $baseRevision);
            if ((int)$tree['root_node_id'] === $nodeId) {
                throw new InvalidArgumentException('The root node cannot be deleted');
            }

            $treeId = (int)$tree['id'];
            $newRevision = (int)$tree['revision'] + 1;
            $deletedIds = $this->descendantIds($treeId, $nodeId);
            array_unshift($deletedIds, $nodeId);
            foreach ($deletedIds as $id) {
                $this->softDeleteNode($id, $now);
            }
            $this->renumberChildren($treeId, $node['parent_id'] === null ? null : (int)$node['parent_id']);
            $this->updateTreeRevision($treeId, $newRevision, $now);
            $this->insertOperation($treeId, $userId, $newRevision, 'deleteNode', [
                'nodeId' => $nodeId,
                'deletedIds' => $deletedIds,
            ], $now);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->loadedTree($userId, $treeId);
    }

    /**
     * @return array<string, mixed>
     */
    public function sortChildren(string $userId, int $nodeId, string $direction, int $baseRevision): array {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $now = time();
        $this->db->beginTransaction();

        try {
            [$node, $tree] = $this->nodeContext($userId, $nodeId, $baseRevision);
            $treeId = (int)$tree['id'];
            $children = $this->childRows($treeId, (int)$node['id']);
            usort($children, static function (array $left, array $right) use ($direction): int {
                $result = strcasecmp((string)$left['title'], (string)$right['title']);
                return $direction === 'desc' ? -$result : $result;
            });
            $this->writeSiblingOrder($children);
            $newRevision = (int)$tree['revision'] + 1;
            $this->updateTreeRevision($treeId, $newRevision, $now);
            $this->insertOperation($treeId, $userId, $newRevision, $direction === 'desc' ? 'sortChildrenDesc' : 'sortChildrenAsc', [
                'nodeId' => $nodeId,
            ], $now);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->loadedTree($userId, $treeId);
    }

    /**
     * @return array<string, mixed>
     */
    public function moveNode(string $userId, int $nodeId, int $targetId, string $mode, int $baseRevision): array {
        $mode = in_array($mode, ['before', 'inside', 'after'], true) ? $mode : 'inside';
        $now = time();
        $this->db->beginTransaction();

        try {
            [$node, $tree] = $this->nodeContext($userId, $nodeId, $baseRevision);
            [$target, $targetTree] = $this->nodeContext($userId, $targetId, $baseRevision);
            $treeId = (int)$tree['id'];
            if ($treeId !== (int)$targetTree['id']) {
                throw new InvalidArgumentException('Target node is in another tree');
            }
            if ((int)$tree['root_node_id'] === $nodeId) {
                throw new InvalidArgumentException('The root node cannot be moved');
            }
            if ($nodeId === $targetId || in_array($targetId, $this->descendantIds($treeId, $nodeId), true)) {
                throw new InvalidArgumentException('Cannot move a node into itself');
            }

            $oldParentId = $node['parent_id'] === null ? null : (int)$node['parent_id'];
            if ($mode === 'inside') {
                $newParentId = $targetId;
                $newIndex = count($this->childRows($treeId, $newParentId));
            } else {
                $newParentId = $target['parent_id'] === null ? null : (int)$target['parent_id'];
                $siblings = $this->childRows($treeId, $newParentId);
                $targetIndex = 0;
                foreach ($siblings as $index => $sibling) {
                    if ((int)$sibling['id'] === $targetId) {
                        $targetIndex = $index;
                        break;
                    }
                }
                $newIndex = $mode === 'after' ? $targetIndex + 1 : $targetIndex;
            }

            $this->moveNodeRow($nodeId, $newParentId, 0);
            $this->renumberChildren($treeId, $oldParentId);
            $siblings = array_values(array_filter($this->childRows($treeId, $newParentId), static fn (array $sibling): bool => (int)$sibling['id'] !== $nodeId));
            $newIndex = max(0, min($newIndex, count($siblings)));
            array_splice($siblings, $newIndex, 0, [[
                'id' => $nodeId,
            ]]);
            $this->writeSiblingOrder($siblings);
            $newRevision = (int)$tree['revision'] + 1;
            $this->updateTreeRevision($treeId, $newRevision, $now);
            $this->insertOperation($treeId, $userId, $newRevision, 'moveNode', [
                'nodeId' => $nodeId,
                'targetId' => $targetId,
                'mode' => $mode,
            ], $now);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->loadedTree($userId, $treeId);
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

    private function insertChildNode(int $treeId, int $parentId, int $sortOrder, string $title, int $now): int {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('nxtree_nodes')
            ->values([
                'tree_id' => $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT),
                'parent_id' => $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT),
                'sort_order' => $qb->createNamedParameter($sortOrder, IQueryBuilder::PARAM_INT),
                'title' => $qb->createNamedParameter($title),
                'content_markdown' => $qb->createNamedParameter(''),
                'version' => $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT),
                'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
                'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
            ])
            ->executeStatement();

        return (int)$this->db->lastInsertId('nxtree_nodes');
    }

    /**
     * @param array<string, mixed> $node
     */
    private function insertImportedNode(int $treeId, ?int $parentId, int $sortOrder, array $node, int $now, int &$nodeCount): int {
        $title = $this->nodeTitle($node, 'Untitled node');
        $content = isset($node['contentMarkdown']) ? (string)$node['contentMarkdown'] : (string)($node['content'] ?? '');
        $qb = $this->db->getQueryBuilder();
        $qb->insert('nxtree_nodes')
            ->values([
                'tree_id' => $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT),
                'parent_id' => $parentId === null ? $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL) : $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT),
                'sort_order' => $qb->createNamedParameter($sortOrder, IQueryBuilder::PARAM_INT),
                'title' => $qb->createNamedParameter($title),
                'content_markdown' => $qb->createNamedParameter($content),
                'version' => $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT),
                'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
                'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
            ])
            ->executeStatement();

        $nodeId = (int)$this->db->lastInsertId('nxtree_nodes');
        $nodeCount++;
        $children = isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
        foreach ($children as $index => $child) {
            if (is_array($child)) {
                $this->insertImportedNode($treeId, $nodeId, $index, $child, $now, $nodeCount);
            }
        }

        return $nodeId;
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

    private function updateNodeRow(int $nodeId, string $title, string $contentMarkdown, int $version, int $now): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('nxtree_nodes')
            ->set('title', $qb->createNamedParameter($title))
            ->set('content_markdown', $qb->createNamedParameter($contentMarkdown))
            ->set('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT))
            ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    private function updateTreeRevision(int $treeId, int $revision, int $now): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('nxtree_trees')
            ->set('revision', $qb->createNamedParameter($revision, IQueryBuilder::PARAM_INT))
            ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    private function updateTreeTitle(int $treeId, string $title): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('nxtree_trees')
            ->set('title', $qb->createNamedParameter($title))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    private function softDeleteNode(int $nodeId, int $now): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('nxtree_nodes')
            ->set('deleted_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    private function moveNodeRow(int $nodeId, ?int $parentId, int $sortOrder): void {
        $qb = $this->db->getQueryBuilder();
        $parentParameter = $parentId === null ? $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL) : $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT);
        $qb->update('nxtree_nodes')
            ->set('parent_id', $parentParameter)
            ->set('sort_order', $qb->createNamedParameter($sortOrder, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    private function updateSortOrder(int $nodeId, int $sortOrder): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('nxtree_nodes')
            ->set('sort_order', $qb->createNamedParameter($sortOrder, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
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
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function nodeContext(string $userId, int $nodeId, int $baseRevision): array {
        $node = $this->nodeRow($nodeId);
        if ($node === null) {
            throw new InvalidArgumentException('Node not found');
        }

        $tree = $this->treeRow((int)$node['tree_id']);
        if ($tree === null || (string)$tree['owner_user_id'] !== $userId) {
            throw new InvalidArgumentException('Node not found');
        }

        if ($baseRevision !== (int)$tree['revision']) {
            throw new UnexpectedValueException('Tree changed elsewhere. Reload before changing this tree.');
        }

        return [$node, $tree];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadedTree(string $userId, int $treeId): array {
        $tree = $this->getTree($userId, $treeId);
        if ($tree === null) {
            throw new InvalidArgumentException('Tree could not be loaded');
        }

        return $tree;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function childRows(int $treeId, ?int $parentId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'parent_id', 'sort_order', 'title')
            ->from('nxtree_nodes')
            ->where($qb->expr()->eq('tree_id', $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->isNull('deleted_at'))
            ->orderBy('sort_order', 'ASC')
            ->addOrderBy('id', 'ASC');

        if ($parentId === null) {
            $qb->andWhere($qb->expr()->isNull('parent_id'));
        } else {
            $qb->andWhere($qb->expr()->eq('parent_id', $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT)));
        }

        $result = $qb->executeQuery();
        $children = [];
        while (($row = $result->fetch()) !== false) {
            $children[] = $row;
        }
        $result->closeCursor();

        return $children;
    }

    private function renumberChildren(int $treeId, ?int $parentId): void {
        $this->writeSiblingOrder($this->childRows($treeId, $parentId));
    }

    /**
     * @param array<int, array<string, mixed>> $siblings
     */
    private function writeSiblingOrder(array $siblings): void {
        foreach ($siblings as $index => $sibling) {
            $this->updateSortOrder((int)$sibling['id'], $index);
        }
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(int $treeId, int $nodeId): array {
        $ids = [];
        foreach ($this->childRows($treeId, $nodeId) as $child) {
            $childId = (int)$child['id'];
            $ids[] = $childId;
            array_push($ids, ...$this->descendantIds($treeId, $childId));
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nodeRow(int $nodeId): ?array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('id', 'tree_id', 'parent_id', 'sort_order', 'title', 'version')
            ->from('nxtree_nodes')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->isNull('deleted_at'))
            ->executeQuery();

        $row = $result->fetch();
        $result->closeCursor();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function treeRow(int $treeId): ?array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('id', 'owner_user_id', 'root_node_id', 'revision')
            ->from('nxtree_trees')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->isNull('deleted_at'))
            ->executeQuery();

        $row = $result->fetch();
        $result->closeCursor();

        return $row === false ? null : $row;
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

    /**
     * @param array<string, mixed> $node
     */
    private function nodeTitle(array $node, string $fallback): string {
        $title = trim((string)($node['title'] ?? $fallback));
        if ($title === '') {
            $title = $fallback;
        }

        return mb_substr($title, 0, 255);
    }
}
