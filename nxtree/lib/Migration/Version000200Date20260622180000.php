<?php

declare(strict_types=1);

namespace OCA\NxTree\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000200Date20260622180000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('nxtree_trees')) {
            $table = $schema->getTable('nxtree_trees');
            if (!$table->hasColumn('source_file_path')) {
                $table->addColumn('source_file_path', 'string', [
                    'notnull' => false,
                    'length' => 1024,
                ]);
            }
            if (!$table->hasColumn('last_export_folder_path')) {
                $table->addColumn('last_export_folder_path', 'string', [
                    'notnull' => false,
                    'length' => 1024,
                ]);
            }
        }

        return $schema;
    }
}
