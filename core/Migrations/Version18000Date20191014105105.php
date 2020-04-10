<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version18000Date20191014105105 extends SimpleMigrationStep {

	/** @var IDBConnection */
	protected $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->createTable('direct_edit');

		$table->addColumn('id', Type::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('editor_id', Type::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('token', Type::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('file_id', Type::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('user_id', Type::STRING, [
			'notnull' => false,
			'length' => 64,
		]);
		$table->addColumn('share_id', Type::BIGINT, [
			'notnull' => false
		]);
		$table->addColumn('timestamp', Type::BIGINT, [
			'notnull' => true,
			'length' => 20,
			'unsigned' => true,
		]);
		$table->addColumn('accessed', Type::BOOLEAN, [
			'notnull' => true,
			'default' => false
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['token']);

		return $schema;
	}

}
