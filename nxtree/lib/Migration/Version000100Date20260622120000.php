<?php

declare(strict_types=1);

namespace OCA\NxTree\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000100Date20260622120000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('nxtree_trees')) {
            $table = $schema->createTable('nxtree_trees');
            $table->addColumn('id', 'bigint', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('owner_user_id', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('title', 'string', [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('root_node_id', 'bigint', [
                'notnull' => false,
                'unsigned' => true,
            ]);
            $table->addColumn('revision', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
                'default' => 0,
            ]);
            $table->addColumn('created_at', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
                'default' => 0,
            ]);
            $table->addColumn('updated_at', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
                'default' => 0,
            ]);
            $table->addColumn('deleted_at', 'bigint', [
                'notnull' => false,
                'unsigned' => true,
            ]);
            $table->addColumn('library_path', 'string', [
                'notnull' => false,
                'length' => 1024,
            ]);
            $table->addColumn('library_name', 'string', [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['owner_user_id'], 'nxtree_trees_owner_idx');
            $table->addIndex(['deleted_at'], 'nxtree_trees_deleted_idx');
        }

        if (!$schema->hasTable('nxtree_nodes')) {
            $table = $schema->createTable('nxtree_nodes');
            $table->addColumn('id', 'bigint', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('tree_id', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('parent_id', 'bigint', [
                'notnull' => false,
                'unsigned' => true,
            ]);
            $table->addColumn('sort_order', 'integer', [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->addColumn('title', 'string', [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('content_markdown', 'text', [
                'notnull' => false,
            ]);
            $table->addColumn('version', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
                'default' => 1,
            ]);
            $table->addColumn('created_at', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
                'default' => 0,
            ]);
            $table->addColumn('updated_at', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
                'default' => 0,
            ]);
            $table->addColumn('deleted_at', 'bigint', [
                'notnull' => false,
                'unsigned' => true,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['tree_id', 'parent_id', 'sort_order'], 'nxtree_nodes_tree_parent_idx');
            $table->addIndex(['tree_id', 'deleted_at'], 'nxtree_nodes_tree_deleted_idx');
        }

        if (!$schema->hasTable('nxtree_operations')) {
            $table = $schema->createTable('nxtree_operations');
            $table->addColumn('id', 'bigint', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('tree_id', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('revision', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('type', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('payload_json', 'text', [
                'notnull' => false,
            ]);
            $table->addColumn('created_at', 'bigint', [
                'notnull' => true,
                'unsigned' => true,
                'default' => 0,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['tree_id', 'revision'], 'nxtree_ops_tree_rev_uniq');
            $table->addIndex(['tree_id', 'created_at'], 'nxtree_ops_tree_created_idx');
        }

        return $schema;
    }
}
