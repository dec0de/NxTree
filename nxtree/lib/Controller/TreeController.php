<?php

declare(strict_types=1);

namespace OCA\NxTree\Controller;

use InvalidArgumentException;
use OCA\NxTree\Service\TreeService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

final class TreeController extends Controller {
    public function __construct(
        IRequest $request,
        private TreeService $treeService,
        private ?string $userId,
    ) {
        parent::__construct('nxtree', $request);
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
}
