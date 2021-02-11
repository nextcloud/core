<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Core\Command\Db;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class AddMissingPrimaryKeys
 *
 * if you added primary keys to the database, this is the right place to add
 * your update routine for existing instances
 *
 * @package OC\Core\Command\Db
 */
class AddMissingPrimaryKeys extends Command {

	/** @var Connection */
	private $connection;

	/** @var EventDispatcherInterface */
	private $dispatcher;

	public function __construct(Connection $connection, EventDispatcherInterface $dispatcher) {
		parent::__construct();

		$this->connection = $connection;
		$this->dispatcher = $dispatcher;
	}

	protected function configure() {
		$this
			->setName('db:add-missing-primary-keys')
			->setDescription('Add missing primary keys to the database tables');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->addCorePrimaryKeys($output);

		// Dispatch event so apps can also update indexes if needed
		$event = new GenericEvent($output);
		$this->dispatcher->dispatch(IDBConnection::ADD_MISSING_PRIMARY_KEYS_EVENT, $event);
		return 0;
	}

	/**
	 * add missing indices to the share table
	 *
	 * @param OutputInterface $output
	 * @throws \Doctrine\DBAL\Schema\SchemaException
	 */
	private function addCorePrimaryKeys(OutputInterface $output) {
		$output->writeln('<info>Check primary keys.</info>');

		$schema = new SchemaWrapper($this->connection);
		$updated = false;

		if ($schema->hasTable('federated_reshares')) {
			$table = $schema->getTable('federated_reshares');
			if (!$table->hasPrimaryKey()) {
				$output->writeln('<info>Adding primary key to the federated_reshares table, this can take some time...</info>');
				$table->setPrimaryKey(['share_id'], 'federated_res_pk');
				if ($table->hasIndex('share_id_index')) {
					$table->dropIndex('share_id_index');
				}
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>federated_reshares table updated successfully.</info>');
			}
		}

		if ($schema->hasTable('systemtag_object_mapping')) {
			$table = $schema->getTable('systemtag_object_mapping');
			if (!$table->hasPrimaryKey()) {
				$output->writeln('<info>Adding primary key to the systemtag_object_mapping table, this can take some time...</info>');
				$table->setPrimaryKey(['objecttype', 'objectid', 'systemtagid'], 'som_pk');
				if ($table->hasIndex('mapping')) {
					$table->dropIndex('mapping');
				}
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>systemtag_object_mapping table updated successfully.</info>');
			}
		}

		if ($schema->hasTable('comments_read_markers')) {
			$table = $schema->getTable('comments_read_markers');
			if (!$table->hasPrimaryKey()) {
				$output->writeln('<info>Adding primary key to the comments_read_markers table, this can take some time...</info>');
				$table->setPrimaryKey(['user_id', 'object_type', 'object_id'], 'crm_pk');
				if ($table->hasIndex('comments_marker_index')) {
					$table->dropIndex('comments_marker_index');
				}
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>comments_read_markers table updated successfully.</info>');
			}
		}

		if ($schema->hasTable('collres_resources')) {
			$table = $schema->getTable('collres_resources');
			if (!$table->hasPrimaryKey()) {
				$output->writeln('<info>Adding primary key to the collres_resources table, this can take some time...</info>');
				$table->setPrimaryKey(['collection_id', 'resource_type', 'resource_id'], 'crr_pk');
				if ($table->hasIndex('collres_unique_res')) {
					$table->dropIndex('collres_unique_res');
				}
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>collres_resources table updated successfully.</info>');
			}
		}

		if ($schema->hasTable('collres_accesscache')) {
			$table = $schema->getTable('collres_accesscache');
			if (!$table->hasPrimaryKey()) {
				$output->writeln('<info>Adding primary key to the collres_accesscache table, this can take some time...</info>');
				$table->setPrimaryKey(['user_id', 'collection_id', 'resource_type', 'resource_id'], 'cra_pk');
				if ($table->hasIndex('collres_unique_user')) {
					$table->dropIndex('collres_unique_user');
				}
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>collres_accesscache table updated successfully.</info>');
			}
		}

		if ($schema->hasTable('filecache_extended')) {
			$table = $schema->getTable('filecache_extended');
			if (!$table->hasPrimaryKey()) {
				$output->writeln('<info>Adding primary key to the filecache_extended table, this can take some time...</info>');
				$table->setPrimaryKey(['fileid'], 'fce_pk');
				if ($table->hasIndex('fce_fileid_idx')) {
					$table->dropIndex('fce_fileid_idx');
				}
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>filecache_extended table updated successfully.</info>');
			}
		}

		if (!$updated) {
			$output->writeln('<info>Done.</info>');
		}
	}
}
