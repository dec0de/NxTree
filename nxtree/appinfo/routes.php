<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'tree#index', 'url' => '/trees', 'verb' => 'GET'],
        ['name' => 'tree#create', 'url' => '/trees', 'verb' => 'POST'],
        ['name' => 'tree#import', 'url' => '/import', 'verb' => 'POST'],
        ['name' => 'tree#importFromFiles', 'url' => '/import/files', 'verb' => 'POST'],
        ['name' => 'tree#browseFiles', 'url' => '/files/browse', 'verb' => 'GET'],
        ['name' => 'tree#show', 'url' => '/trees/{treeId}', 'verb' => 'GET'],
        ['name' => 'tree#sync', 'url' => '/trees/{treeId}/sync', 'verb' => 'GET'],
        ['name' => 'tree#exportMtre', 'url' => '/trees/{treeId}/export/mtre', 'verb' => 'GET'],
        ['name' => 'tree#exportMtreToFiles', 'url' => '/trees/{treeId}/export/files', 'verb' => 'POST'],
        ['name' => 'tree#organize', 'url' => '/trees/{treeId}/organize', 'verb' => 'POST'],
        ['name' => 'tree#restoreStructure', 'url' => '/trees/{treeId}/restore', 'verb' => 'POST'],
        ['name' => 'tree#updateNode', 'url' => '/nodes/{nodeId}', 'verb' => 'PUT'],
        ['name' => 'tree#addNode', 'url' => '/nodes/{parentId}/children', 'verb' => 'POST'],
        ['name' => 'tree#deleteNode', 'url' => '/nodes/{nodeId}/delete', 'verb' => 'POST'],
        ['name' => 'tree#sortChildren', 'url' => '/nodes/{nodeId}/sort', 'verb' => 'POST'],
        ['name' => 'tree#moveNode', 'url' => '/nodes/{nodeId}/move', 'verb' => 'POST'],
    ],
];
