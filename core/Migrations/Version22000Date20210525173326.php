<?php

declare(strict_types=1);

namespace OC\Core\Migrations;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version22000Date20210525173326 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('previously_used_userids')) {
			$table = $schema->createTable('previously_used_userids');
			$table->addColumn('user_id_hash', \OCP\DB\Types::STRING, [
				'notnull' => true,
				'length' => 128,
			]);
			$table->setPrimaryKey(['user_id_hash'], 'uid_hash_idx');
		}

		return $schema;
	}
}
