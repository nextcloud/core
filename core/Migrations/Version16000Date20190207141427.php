<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Core\Migrations;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version16000Date20190207141427 extends SimpleMigrationStep {


	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('collres_collections')) {
			$table = $schema->createTable('collres_collections');

			$table->addColumn('id', Type::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('name', Type::STRING, [
				'notnull' => true,
				'length' => 64,
			]);

			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('collres_resources')) {
			$table = $schema->createTable('collres_resources');

			$table->addColumn('collection_id', Type::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('resource_type', Type::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('resource_id', Type::STRING, [
				'notnull' => true,
				'length' => 64,
			]);

			$table->addUniqueIndex(['collection_id', 'resource_type', 'resource_id'], 'collres_unique_res');
		}

		if (!$schema->hasTable('collres_accesscache')) {
			$table = $schema->createTable('collres_accesscache');

			$table->addColumn('user_id', Type::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('collection_id', Type::BIGINT, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('resource_type', Type::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('resource_id', Type::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('access', Type::SMALLINT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->addUniqueIndex(['user_id', 'collection_id', 'resource_type', 'resource_id'], 'collres_unique_user');
			$table->addIndex(['user_id', 'resource_type', 'resource_id'], 'collres_user_res');
			$table->addIndex(['user_id', 'collection_id'], 'collres_user_coll');
		}

		return $schema;
	}

}
