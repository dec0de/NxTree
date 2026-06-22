<?php

declare(strict_types=1);

namespace OCA\NxTree\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000500Date20260623000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('nxtree_nodes')) {
            $table = $schema->getTable('nxtree_nodes');
            if (!$table->hasColumn('node_kind')) {
                $table->addColumn('node_kind', 'string', [
                    'notnull' => true,
                    'length' => 32,
                    'default' => 'note',
                ]);
            }
            if (!$table->hasColumn('linked_tree_id')) {
                $table->addColumn('linked_tree_id', 'bigint', [
                    'notnull' => false,
                    'unsigned' => true,
                ]);
            }
        }

        return $schema;
    }
}
