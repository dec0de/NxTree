<?php

declare(strict_types=1);

namespace OCA\NxTree\Controller;

use InvalidArgumentException;
use OCA\NxTree\Service\TreeService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use UnexpectedValueException;

final class TreeController extends Controller {
    public function __construct(
        private IRequest $appRequest,
        private TreeService $treeService,
        private ?string $userId,
    ) {
        parent::__construct('nxtree', $appRequest);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function index(): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        return new JSONResponse(['trees' => $this->treeService->listTrees($this->userId)]);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function create(string $title = 'Untitled tree'): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $tree = $this->treeService->createTree($this->userId, $title);
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        return new JSONResponse(['tree' => $tree], Http::STATUS_CREATED);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function import(): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        $file = $this->appRequest->getUploadedFile('file');
        if (!is_array($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new JSONResponse(['error' => 'No import file uploaded'], Http::STATUS_BAD_REQUEST);
        }

        $fileName = isset($file['name']) ? (string)$file['name'] : 'Imported tree.mtre';
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($extension !== 'mtre') {
            return new JSONResponse(['error' => 'Only .mtre files can be imported right now'], Http::STATUS_BAD_REQUEST);
        }

        $contents = file_get_contents($file['tmp_name']);
        if ($contents === false) {
            return new JSONResponse(['error' => 'Could not read import file'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $tree = $this->treeService->importMtre($this->userId, $contents, pathinfo($fileName, PATHINFO_FILENAME));
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        return new JSONResponse(['tree' => $tree], Http::STATUS_CREATED);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function importFromFiles(string $path = ''): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $tree = $this->treeService->importMtreFromFiles($this->userId, $path);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => $e->getMessage() ?: 'Could not import from Nextcloud Files'], Http::STATUS_BAD_REQUEST);
        }

        return new JSONResponse(['tree' => $tree], Http::STATUS_CREATED);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function browseFiles(string $path = '/', int $createFolder = 0): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            return new JSONResponse($this->treeService->browseFiles($this->userId, $path, $createFolder === 1));
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => $e->getMessage() ?: 'Could not browse Nextcloud Files'], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function show(int $treeId): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        $tree = $this->treeService->getTree($this->userId, $treeId);
        if ($tree === null) {
            return new JSONResponse(['error' => 'Tree not found'], Http::STATUS_NOT_FOUND);
        }

        return new JSONResponse(['tree' => $tree]);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function sync(int $treeId, int $sinceRevision = 0): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        $sync = $this->treeService->syncTree($this->userId, $treeId, $sinceRevision);
        if ($sync === null) {
            return new JSONResponse(['error' => 'Tree not found'], Http::STATUS_NOT_FOUND);
        }

        return new JSONResponse($sync);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function exportMtre(int $treeId, int $nodeId = 0): DataDownloadResponse|JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        $export = $this->treeService->exportMtre($this->userId, $treeId, $nodeId > 0 ? $nodeId : null);
        if ($export === null) {
            return new JSONResponse(['error' => 'Tree not found'], Http::STATUS_NOT_FOUND);
        }

        return new DataDownloadResponse(
            $export['contents'],
            $export['filename'],
            'application/json; charset=utf-8'
        );
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function exportMtreToFiles(int $treeId, int $nodeId = 0, string $folderPath = '', string $filename = ''): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $export = $this->treeService->exportMtreToFiles($this->userId, $treeId, $nodeId > 0 ? $nodeId : null, $folderPath, $filename);
        } catch (\Throwable $e) {
            return new JSONResponse(['error' => $e->getMessage() ?: 'Could not export to Nextcloud Files'], Http::STATUS_BAD_REQUEST);
        }
        if ($export === null) {
            return new JSONResponse(['error' => 'Tree not found'], Http::STATUS_NOT_FOUND);
        }

        return new JSONResponse($export, Http::STATUS_CREATED);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function updateNode(int $nodeId, string $title = '', string $contentMarkdown = '', int $baseRevision = 0): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $tree = $this->treeService->updateNode($this->userId, $nodeId, $title, $contentMarkdown, $baseRevision);
        } catch (UnexpectedValueException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT);
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        return new JSONResponse(['tree' => $tree]);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function addNode(int $parentId, int $baseRevision = 0): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $tree = $this->treeService->addNode($this->userId, $parentId, $baseRevision);
        } catch (UnexpectedValueException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT);
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        return new JSONResponse(['tree' => $tree], Http::STATUS_CREATED);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function deleteNode(int $nodeId, int $baseRevision = 0): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $tree = $this->treeService->deleteNode($this->userId, $nodeId, $baseRevision);
        } catch (UnexpectedValueException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT);
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        return new JSONResponse(['tree' => $tree]);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function sortChildren(int $nodeId, string $direction = 'asc', int $baseRevision = 0): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $tree = $this->treeService->sortChildren($this->userId, $nodeId, $direction, $baseRevision);
        } catch (UnexpectedValueException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT);
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        return new JSONResponse(['tree' => $tree]);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function moveNode(int $nodeId, int $targetId = 0, string $mode = 'inside', int $baseRevision = 0): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $tree = $this->treeService->moveNode($this->userId, $nodeId, $targetId, $mode, $baseRevision);
        } catch (UnexpectedValueException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT);
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        return new JSONResponse(['tree' => $tree]);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function restoreStructure(int $treeId, string $snapshot = '', int $baseRevision = 0): JSONResponse {
        if ($this->userId === null) {
            return new JSONResponse(['error' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        $decoded = json_decode($snapshot, true);
        if (!is_array($decoded)) {
            return new JSONResponse(['error' => 'Invalid undo snapshot'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $tree = $this->treeService->restoreStructure($this->userId, $treeId, $decoded, $baseRevision);
        } catch (UnexpectedValueException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT);
        } catch (InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        return new JSONResponse(['tree' => $tree]);
    }
}
