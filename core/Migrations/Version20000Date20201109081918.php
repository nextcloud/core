<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
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
use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20000Date20201109081918 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('storages_credentials')) {
			$table = $schema->createTable('storages_credentials');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('user', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('identifier', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('credentials', Types::TEXT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user', 'identifier'], 'stocred_ui');
			$table->addIndex(['user'], 'stocred_user');
		}

		return $schema;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 13.0.0
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		if (!$this->connection->tableExists('credentials')) {
			return;
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('credentials');

		$insert = $this->connection->getQueryBuilder();
		$insert->insert('storages_credentials')
			->setValue('user', $insert->createParameter('user'))
			->setValue('identifier', $insert->createParameter('identifier'))
			->setValue('credentials', $insert->createParameter('credentials'));

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$insert->setParameter('user', (string) $row['user'])
				->setParameter('identifier', (string) $row['identifier'])
				->setParameter('credentials', (string) $row['credentials']);
			$insert->execute();
		}
		$result->closeCursor();
	}
}
