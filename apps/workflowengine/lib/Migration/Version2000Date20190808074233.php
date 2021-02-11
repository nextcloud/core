<?php

declare(strict_types=1);

namespace OCA\WorkflowEngine\Migration;

use Closure;
use Doctrine\DBAL\Schema\Table;
use OCP\DB\Types;
use OCA\WorkflowEngine\Entity\File;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2000Date20190808074233 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('flow_checks')) {
			$table = $schema->createTable('flow_checks');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('class', Types::STRING, [
				'notnull' => true,
				'length' => 256,
				'default' => '',
			]);
			$table->addColumn('operator', Types::STRING, [
				'notnull' => true,
				'length' => 16,
				'default' => '',
			]);
			$table->addColumn('value', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('hash', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => '',
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['hash'], 'flow_unique_hash');
		}

		if (!$schema->hasTable('flow_operations')) {
			$table = $schema->createTable('flow_operations');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('class', Types::STRING, [
				'notnull' => true,
				'length' => 256,
				'default' => '',
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 256,
				'default' => '',
			]);
			$table->addColumn('checks', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('operation', Types::TEXT, [
				'notnull' => false,
			]);
			$this->ensureEntityColumns($table);
			$table->setPrimaryKey(['id']);
		} else {
			$table = $schema->getTable('flow_operations');
			$this->ensureEntityColumns($table);
		}

		if (!$schema->hasTable('flow_operations_scope')) {
			$table = $schema->createTable('flow_operations_scope');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('operation_id', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('type', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('value', Types::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => '',
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['operation_id', 'type', 'value'], 'flow_unique_scope');
		}

		return $schema;
	}

	protected function ensureEntityColumns(Table $table) {
		if (!$table->hasColumn('entity')) {
			$table->addColumn('entity', Types::STRING, [
				'notnull' => true,
				'length' => 256,
				'default' => File::class,
			]);
		}
		if (!$table->hasColumn('events')) {
			$table->addColumn('events', Types::TEXT, [
				'notnull' => true,
				'default' => '[]',
			]);
		}
	}
}
