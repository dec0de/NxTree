<?php

declare(strict_types=1);

namespace OCA\NxTree\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000300Date20260622230000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('nxtree_trees')) {
            $table = $schema->getTable('nxtree_trees');
            if (!$table->hasColumn('library_path')) {
                $table->addColumn('library_path', 'string', [
                    'notnull' => false,
                    'length' => 1024,
                ]);
            }
        }

        return $schema;
    }
}
