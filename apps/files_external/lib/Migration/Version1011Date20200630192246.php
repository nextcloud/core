<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Vincent Petry <vincent@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files_External\Migration;

use Closure;
use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1011Date20200630192246 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('external_mounts')) {
			$table = $schema->createTable('external_mounts');
			$table->addColumn('mount_id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('mount_point', Types::STRING, [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('storage_backend', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('auth_backend', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('priority', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
				'default' => 100,
			]);
			$table->addColumn('type', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			$table->setPrimaryKey(['mount_id']);
		}

		if (!$schema->hasTable('external_applicable')) {
			$table = $schema->createTable('external_applicable');
			$table->addColumn('applicable_id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('mount_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('type', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('value', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->setPrimaryKey(['applicable_id']);
			$table->addIndex(['mount_id'], 'applicable_mount');
			$table->addIndex(['type', 'value'], 'applicable_type_value');
			$table->addUniqueIndex(['type', 'value', 'mount_id'], 'applicable_type_value_mount');
		}

		if (!$schema->hasTable('external_config')) {
			$table = $schema->createTable('external_config');
			$table->addColumn('config_id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('mount_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('key', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('value', Types::STRING, [
				'notnull' => false,
				'length' => 4096,
			]);
			$table->setPrimaryKey(['config_id']);
			$table->addIndex(['mount_id'], 'config_mount');
			$table->addUniqueIndex(['mount_id', 'key'], 'config_mount_key');
		} else {
			$table = $schema->getTable('external_config');
			$table->changeColumn('value', [
				'notnull' => false,
				'length' => 4096,
			]);
		}

		if (!$schema->hasTable('external_options')) {
			$table = $schema->createTable('external_options');
			$table->addColumn('option_id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('mount_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('key', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('value', Types::STRING, [
				'notnull' => true,
				'length' => 256,
			]);
			$table->setPrimaryKey(['option_id']);
			$table->addIndex(['mount_id'], 'option_mount');
			$table->addUniqueIndex(['mount_id', 'key'], 'option_mount_key');
		}
		return $schema;
	}
}
