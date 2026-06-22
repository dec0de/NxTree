<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'tree#index', 'url' => '/trees', 'verb' => 'GET'],
        ['name' => 'tree#create', 'url' => '/trees', 'verb' => 'POST'],
        ['name' => 'tree#import', 'url' => '/import', 'verb' => 'POST'],
        ['name' => 'tree#show', 'url' => '/trees/{treeId}', 'verb' => 'GET'],
        ['name' => 'tree#updateNode', 'url' => '/nodes/{nodeId}', 'verb' => 'PUT'],
    ],
];
