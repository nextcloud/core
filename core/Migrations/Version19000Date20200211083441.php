<?php

declare(strict_types=1);

namespace OC\Core\Migrations;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version19000Date20200211083441 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('webauthn')) {
			$table = $schema->createTable('webauthn');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('uid', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('public_key_credential_id', 'string', [
				'notnull' => true,
				'length' => 255
			]);
			$table->addColumn('data', 'text', [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['uid'], 'webauthn_uid');
			$table->addIndex(['public_key_credential_id'], 'webauthn_publicKeyCredentialId');
		}
		return $schema;
	}
}
