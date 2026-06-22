<?php

declare(strict_types=1);

namespace OCA\NxTree\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000400Date20260622233000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('nxtree_trees')) {
            $table = $schema->getTable('nxtree_trees');
            if (!$table->hasColumn('library_name')) {
                $table->addColumn('library_name', 'string', [
                    'notnull' => false,
                    'length' => 255,
                ]);
            }
        }

        return $schema;
    }
}
