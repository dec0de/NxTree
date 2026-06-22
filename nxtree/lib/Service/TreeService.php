<?php

declare(strict_types=1);

namespace OCA\NxTree\Service;

use InvalidArgumentException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use UnexpectedValueException;

final class TreeService {
    private const DEFAULT_EXPORT_FOLDER = '/NxTree';

    public function __construct(
        private IDBConnection $db,
        private IRootFolder $rootFolder,
    ) {
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    public function listTrees(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('id', 'title', 'root_node_id', 'revision', 'created_at', 'updated_at', 'source_file_path', 'last_export_folder_path', 'library_path', 'library_name')
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
    public function importMtre(string $userId, string $contents, string $fallbackTitle, ?string $sourceFilePath = null): array {
        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Import file is not valid MeeTree JSON');
        }

        $root = $decoded['root'] ?? $decoded;
        if (!is_array($root)) {
            throw new InvalidArgumentException('Import file does not contain a root node');
        }

        $title = $this->nodeTitle($root, $fallbackTitle !== '' ? $fallbackTitle : 'Imported tree');
        $sourceFilePath = $sourceFilePath === null ? null : $this->normalisePath($sourceFilePath);
        $lastExportFolderPath = $sourceFilePath === null ? null : $this->parentPath($sourceFilePath);
        $now = time();
        $nodeCount = 0;
        $this->db->beginTransaction();

        try {
            $treeId = $this->insertTree($userId, $title, $now, $sourceFilePath, $lastExportFolderPath);
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
     * @return array<string, mixed>
     */
    public function importMtreFromFiles(string $userId, string $path): array {
        $path = $this->normalisePath($path);
        if (!str_ends_with(strtolower($path), '.mtre')) {
            throw new InvalidArgumentException('Only .mtre files can be imported from Nextcloud Files');
        }

        $file = $this->getUserFile($userId, $path);
        return $this->importMtre($userId, $file->getContent(), pathinfo($file->getName(), PATHINFO_FILENAME), $path);
    }

    /**
     * @return array<string, mixed>
     */
    public function browseFiles(string $userId, string $path, bool $createFolder = false): array {
        $path = $this->normalisePath($path);
        try {
            $folder = $createFolder ? $this->getOrCreateUserFolder($userId, $path) : ($path === '/' ? $this->getUserFolder($userId) : $this->getUserFolderAtPath($userId, $path));
        } catch (NotFoundException) {
            $path = '/';
            $folder = $this->getUserFolder($userId);
        }
        $entries = [];

        foreach ($folder->getDirectoryListing() as $node) {
            if ($node instanceof Folder) {
                $entries[] = [
                    'name' => $node->getName(),
                    'path' => $this->joinPath($path, $node->getName()),
                    'type' => 'folder',
                ];
            } elseif ($node instanceof File && str_ends_with(strtolower($node->getName()), '.mtre')) {
                $entries[] = [
                    'name' => $node->getName(),
                    'path' => $this->joinPath($path, $node->getName()),
                    'type' => 'file',
                ];
            }
        }

        usort($entries, static function (array $left, array $right): int {
            if ($left['type'] !== $right['type']) {
                return $left['type'] === 'folder' ? -1 : 1;
            }

            return strcasecmp((string)$left['name'], (string)$right['name']);
        });

        return [
            'path' => $path,
            'parent' => $path === '/' ? null : $this->parentPath($path),
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTree(string $userId, int $treeId): ?array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('id', 'title', 'root_node_id', 'revision', 'created_at', 'updated_at', 'source_file_path', 'last_export_folder_path', 'library_path', 'library_name')
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
    public function saveTreeToLibrary(string $userId, int $treeId, string $libraryPath, string $libraryName, int $baseRevision): array {
        $libraryPath = $this->normalisePath($libraryPath);
        $libraryName = $this->normaliseLibraryName($libraryName);
        $now = time();
        $this->db->beginTransaction();

        try {
            $tree = $this->treeRow($treeId);
            if ($tree === null || (string)$tree['owner_user_id'] !== $userId) {
                throw new InvalidArgumentException('Tree not found');
            }
            if ($baseRevision !== (int)$tree['revision']) {
                throw new UnexpectedValueException('Tree changed elsewhere. Reload before saving.');
            }
            if ($libraryName === '') {
                $libraryName = $this->normaliseLibraryName((string)($tree['library_name'] ?? '') ?: $this->treeTitle($treeId));
            }

            $newRevision = (int)$tree['revision'] + 1;
            $this->updateTreeLibraryFile($treeId, $libraryPath, $libraryName);
            $this->updateTreeRevision($treeId, $newRevision, $now);
            $this->insertOperation($treeId, $userId, $newRevision, 'saveToLibrary', [
                'libraryPath' => $libraryPath,
                'libraryName' => $libraryName,
            ], $now);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->loadedTree($userId, $treeId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function syncTree(string $userId, int $treeId, int $sinceRevision): ?array {
        $tree = $this->getTree($userId, $treeId);
        if ($tree === null) {
            return null;
        }

        $currentRevision = (int)$tree['revision'];
        if ($sinceRevision >= $currentRevision) {
            return [
                'changed' => false,
                'revision' => $currentRevision,
                'operations' => [],
            ];
        }

        return [
            'changed' => true,
            'revision' => $currentRevision,
            'operations' => $this->listOperationsAfter($treeId, max(0, $sinceRevision)),
            'tree' => $tree,
        ];
    }

    /**
     * @return array{filename: string, contents: string}|null
     */
    public function exportMtre(string $userId, int $treeId, ?int $nodeId = null): ?array {
        $tree = $this->getTree($userId, $treeId);
        if ($tree === null) {
            return null;
        }

        $root = $nodeId === null ? $this->rootNode($tree['nodes']) : $this->findLoadedNode($tree['nodes'], $nodeId);
        if ($root === null) {
            return null;
        }

        $document = [
            'version' => 1,
            'root' => $this->exportNode($tree['nodes'], $root),
        ];
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return [
            'filename' => $this->exportFilename((string)$root['title']),
            'contents' => $json . "\n",
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function exportMtreToFiles(string $userId, int $treeId, ?int $nodeId = null, string $folderPath = '', string $filename = ''): ?array {
        $tree = $this->getTree($userId, $treeId);
        if ($tree === null) {
            return null;
        }

        $export = $this->exportMtre($userId, $treeId, $nodeId);
        if ($export === null) {
            return null;
        }

        $folderPath = trim($folderPath) === '' ? $this->defaultExportFolder($tree) : $this->normalisePath($folderPath);
        $filename = trim($filename) === '' ? (string)$export['filename'] : $this->normaliseFilename($filename);
        $folder = $this->getOrCreateUserFolder($userId, $folderPath);
        $path = $this->uniqueFilePath($folder, $folderPath, $filename);
        $file = $folder->newFile(basename($path));
        $file->putContent((string)$export['contents']);
        $this->updateTreeFilePaths($treeId, null, $folderPath);

        $updated = $this->getTree($userId, $treeId);

        return [
            'path' => $path,
            'folderPath' => $folderPath,
            'filename' => basename($path),
            'tree' => $updated,
        ];
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

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public function restoreStructure(string $userId, int $treeId, array $snapshot, int $baseRevision): array {
        $nodes = isset($snapshot['nodes']) && is_array($snapshot['nodes']) ? $snapshot['nodes'] : [];
        if ($nodes === []) {
            throw new InvalidArgumentException('Undo snapshot contains no nodes');
        }

        $now = time();
        $this->db->beginTransaction();

        try {
            $tree = $this->treeRow($treeId);
            if ($tree === null || (string)$tree['owner_user_id'] !== $userId) {
                throw new InvalidArgumentException('Tree not found');
            }
            if ($baseRevision !== (int)$tree['revision']) {
                throw new UnexpectedValueException('Tree changed elsewhere. Reload before undoing.');
            }

            $snapshotNodes = [];
            foreach ($nodes as $node) {
                if (!is_array($node) || !isset($node['id'])) {
                    continue;
                }
                $nodeId = (int)$node['id'];
                $snapshotNodes[$nodeId] = $node;
            }
            if ($snapshotNodes === []) {
                throw new InvalidArgumentException('Undo snapshot contains no valid nodes');
            }

            $rootNodeId = (int)($snapshot['rootNodeId'] ?? $tree['root_node_id']);
            if (!isset($snapshotNodes[$rootNodeId])) {
                throw new InvalidArgumentException('Undo snapshot does not contain the root node');
            }

            foreach ($this->nodeIdsIncludingDeleted($treeId) as $nodeId) {
                if (!isset($snapshotNodes[$nodeId])) {
                    $this->softDeleteNode($nodeId, $now);
                }
            }

            foreach ($snapshotNodes as $nodeId => $node) {
                $this->restoreSnapshotNode($treeId, $nodeId, $node, $now);
            }

            $newRevision = (int)$tree['revision'] + 1;
            $this->updateTreeRevision($treeId, $newRevision, $now);
            $this->updateTreeTitle($treeId, (string)($snapshotNodes[$rootNodeId]['title'] ?? 'Untitled tree'));
            $this->insertOperation($treeId, $userId, $newRevision, 'undoStructure', [
                'nodeCount' => count($snapshotNodes),
            ], $now);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->loadedTree($userId, $treeId);
    }

    private function insertTree(string $userId, string $title, int $now, ?string $sourceFilePath = null, ?string $lastExportFolderPath = null): int {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('nxtree_trees')
            ->values([
                'owner_user_id' => $qb->createNamedParameter($userId),
                'title' => $qb->createNamedParameter($title),
                'revision' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
                'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
                'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
                'source_file_path' => $sourceFilePath === null ? $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL) : $qb->createNamedParameter($sourceFilePath),
                'last_export_folder_path' => $lastExportFolderPath === null ? $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL) : $qb->createNamedParameter($lastExportFolderPath),
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

    private function updateTreeFilePaths(int $treeId, ?string $sourceFilePath, ?string $lastExportFolderPath): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('nxtree_trees')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT)));
        if ($sourceFilePath !== null) {
            $qb->set('source_file_path', $qb->createNamedParameter($sourceFilePath));
        }
        if ($lastExportFolderPath !== null) {
            $qb->set('last_export_folder_path', $qb->createNamedParameter($lastExportFolderPath));
        }
        $qb->executeStatement();
    }

    private function updateTreeLibraryFile(int $treeId, string $libraryPath, string $libraryName): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('nxtree_trees')
            ->set('library_path', $qb->createNamedParameter($libraryPath))
            ->set('library_name', $qb->createNamedParameter($libraryName))
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

    /**
     * @param array<string, mixed> $node
     */
    private function restoreSnapshotNode(int $treeId, int $nodeId, array $node, int $now): void {
        $parentId = array_key_exists('parentId', $node) && $node['parentId'] !== null ? (int)$node['parentId'] : null;
        $qb = $this->db->getQueryBuilder();
        $qb->update('nxtree_nodes')
            ->set('parent_id', $parentId === null ? $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL) : $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT))
            ->set('sort_order', $qb->createNamedParameter((int)($node['sortOrder'] ?? 0), IQueryBuilder::PARAM_INT))
            ->set('title', $qb->createNamedParameter(mb_substr(trim((string)($node['title'] ?? 'Untitled node')) ?: 'Untitled node', 0, 255)))
            ->set('content_markdown', $qb->createNamedParameter((string)($node['contentMarkdown'] ?? '')))
            ->set('version', $qb->createNamedParameter(((int)($node['version'] ?? 1)) + 1, IQueryBuilder::PARAM_INT))
            ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
            ->set('deleted_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('tree_id', $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT)))
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
     * @return array<int, array<string, mixed>>
     */
    private function listOperationsAfter(int $treeId, int $revision): array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('revision', 'user_id', 'type', 'payload_json', 'created_at')
            ->from('nxtree_operations')
            ->where($qb->expr()->eq('tree_id', $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->gt('revision', $qb->createNamedParameter($revision, IQueryBuilder::PARAM_INT)))
            ->orderBy('revision', 'ASC')
            ->executeQuery();

        $operations = [];
        while (($row = $result->fetch()) !== false) {
            $payload = json_decode((string)$row['payload_json'], true);
            $operations[] = [
                'revision' => (int)$row['revision'],
                'userId' => (string)$row['user_id'],
                'type' => (string)$row['type'],
                'payload' => is_array($payload) ? $payload : [],
                'createdAt' => (int)$row['created_at'],
            ];
        }
        $result->closeCursor();

        return $operations;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    private function nestedNodes(array $nodes, ?int $parentId = null): array {
        $children = array_values(array_filter($nodes, static function (array $node) use ($parentId): bool {
            return $node['parentId'] === $parentId;
        }));
        usort($children, static function (array $left, array $right): int {
            return ((int)$left['sortOrder'] <=> (int)$right['sortOrder']) ?: ((int)$left['id'] <=> (int)$right['id']);
        });

        return array_map(function (array $node) use ($nodes): array {
            return [
                'title' => (string)$node['title'],
                'contentMarkdown' => (string)$node['contentMarkdown'],
                'children' => $this->nestedNodes($nodes, (int)$node['id']),
            ];
        }, $children);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<string, mixed>|null
     */
    private function rootNode(array $nodes): ?array {
        foreach ($nodes as $node) {
            if ($node['parentId'] === null) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<string, mixed>|null
     */
    private function findLoadedNode(array $nodes, int $nodeId): ?array {
        foreach ($nodes as $node) {
            if ((int)$node['id'] === $nodeId) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function exportNode(array $nodes, array $node): array {
        return [
            'title' => (string)$node['title'],
            'contentMarkdown' => (string)$node['contentMarkdown'],
            'children' => $this->nestedNodes($nodes, (int)$node['id']),
        ];
    }

    private function exportFilename(string $title): string {
        $base = trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $title) ?? '');
        $base = trim($base, '.-_');
        if ($base === '') {
            $base = 'nxtree';
        }

        return $base . '.mtre';
    }

    /**
     * @param array<string, mixed> $tree
     */
    private function defaultExportFolder(array $tree): string {
        $libraryPath = trim((string)($tree['libraryPath'] ?? ''));
        if ($libraryPath !== '') {
            return $this->normalisePath($libraryPath);
        }

        $lastExportFolder = trim((string)($tree['lastExportFolderPath'] ?? ''));
        if ($lastExportFolder !== '') {
            return $this->normalisePath($lastExportFolder);
        }

        $sourceFilePath = trim((string)($tree['sourceFilePath'] ?? ''));
        if ($sourceFilePath !== '') {
            return $this->parentPath($sourceFilePath);
        }

        return self::DEFAULT_EXPORT_FOLDER;
    }

    private function normalisePath(string $path): string {
        $path = trim(str_replace('\\', '/', $path));
        $path = preg_replace('#/+#', '/', $path) ?? '/';
        if ($path === '' || $path === '.') {
            return '/';
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return '/' . implode('/', $parts);
    }

    private function parentPath(string $path): string {
        $path = $this->normalisePath($path);
        $parent = dirname($path);
        return $parent === '\\' || $parent === '.' ? '/' : $this->normalisePath($parent);
    }

    private function normaliseFilename(string $filename): string {
        $filename = basename(str_replace('\\', '/', trim($filename)));
        $filename = preg_replace('/[^A-Za-z0-9._ -]+/', '-', $filename) ?: 'nxtree';
        if (!str_ends_with(strtolower($filename), '.mtre')) {
            $filename = preg_replace('/\.(mtre|json|hjt|ctd)$/i', '', $filename) . '.mtre';
        }

        return $filename;
    }

    private function getUserFolder(string $userId): Folder {
        return $this->rootFolder->getUserFolder($userId);
    }

    private function getUserFile(string $userId, string $path): File {
        $node = $this->getUserFolder($userId)->get(ltrim($path, '/'));
        if (!$node instanceof File) {
            throw new InvalidArgumentException('Nextcloud Files path is not a file');
        }

        return $node;
    }

    private function getUserFolderAtPath(string $userId, string $path): Folder {
        if ($this->normalisePath($path) === '/') {
            return $this->getUserFolder($userId);
        }

        $node = $this->getUserFolder($userId)->get(ltrim($path, '/'));
        if (!$node instanceof Folder) {
            throw new InvalidArgumentException('Nextcloud Files path is not a folder');
        }

        return $node;
    }

    private function getOrCreateUserFolder(string $userId, string $path): Folder {
        $path = $this->normalisePath($path);
        $folder = $this->getUserFolder($userId);
        if ($path === '/') {
            return $folder;
        }

        foreach (explode('/', trim($path, '/')) as $part) {
            try {
                $node = $folder->get($part);
                if (!$node instanceof Folder) {
                    throw new InvalidArgumentException($path . ' contains a file where a folder is required');
                }
                $folder = $node;
            } catch (NotFoundException) {
                $folder = $folder->newFolder($part);
            }
        }

        return $folder;
    }

    private function uniqueFilePath(Folder $folder, string $folderPath, string $filename): string {
        $base = preg_replace('/\.mtre$/i', '', $filename) ?: 'nxtree';
        $candidate = $filename;
        $counter = 2;
        while ($folder->nodeExists($candidate)) {
            $candidate = $base . '-' . $counter . '.mtre';
            $counter++;
        }

        return rtrim($this->normalisePath($folderPath), '/') . '/' . $candidate;
    }

    private function joinPath(string $parent, string $name): string {
        return rtrim($this->normalisePath($parent), '/') . '/' . ltrim($name, '/');
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

    /**
     * @return array<int, int>
     */
    private function nodeIdsIncludingDeleted(int $treeId): array {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('id')
            ->from('nxtree_nodes')
            ->where($qb->expr()->eq('tree_id', $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT)))
            ->executeQuery();

        $ids = [];
        while (($row = $result->fetch()) !== false) {
            $ids[] = (int)$row['id'];
        }
        $result->closeCursor();

        return $ids;
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
        $result = $qb->select('id', 'owner_user_id', 'root_node_id', 'revision', 'source_file_path', 'last_export_folder_path', 'library_path', 'library_name')
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
            'sourceFilePath' => $row['source_file_path'] === null ? null : (string)$row['source_file_path'],
            'lastExportFolderPath' => $row['last_export_folder_path'] === null ? null : (string)$row['last_export_folder_path'],
            'libraryPath' => $row['library_path'] === null ? null : (string)$row['library_path'],
            'libraryName' => $row['library_name'] === null ? null : (string)$row['library_name'],
        ];
    }

    private function treeTitle(int $treeId): string {
        $qb = $this->db->getQueryBuilder();
        $result = $qb->select('title')
            ->from('nxtree_trees')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($treeId, IQueryBuilder::PARAM_INT)))
            ->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return $row === false ? 'Untitled tree' : (string)$row['title'];
    }

    private function normaliseLibraryName(string $name): string {
        $name = trim(preg_replace('/[\\\/]+/', '-', $name) ?? '');
        $name = preg_replace('/\.(nxtree|mtre)$/i', '', $name) ?? $name;
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        return mb_substr($name, 0, 255);
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
