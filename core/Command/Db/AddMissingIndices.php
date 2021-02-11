<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Mario Danic <mario@lovelyhq.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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
 * Class AddMissingIndices
 *
 * if you added any new indices to the database, this is the right place to add
 * your update routine for existing instances
 *
 * @package OC\Core\Command\Db
 */
class AddMissingIndices extends Command {

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
			->setName('db:add-missing-indices')
			->setDescription('Add missing indices to the database tables');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->addCoreIndexes($output);

		// Dispatch event so apps can also update indexes if needed
		$event = new GenericEvent($output);
		$this->dispatcher->dispatch(IDBConnection::ADD_MISSING_INDEXES_EVENT, $event);
		return 0;
	}

	/**
	 * add missing indices to the share table
	 *
	 * @param OutputInterface $output
	 * @throws \Doctrine\DBAL\Schema\SchemaException
	 */
	private function addCoreIndexes(OutputInterface $output) {
		$output->writeln('<info>Check indices of the share table.</info>');

		$schema = new SchemaWrapper($this->connection);
		$updated = false;

		if ($schema->hasTable('share')) {
			$table = $schema->getTable('share');
			if (!$table->hasIndex('share_with_index')) {
				$output->writeln('<info>Adding additional share_with index to the share table, this can take some time...</info>');
				$table->addIndex(['share_with'], 'share_with_index');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>Share table updated successfully.</info>');
			}

			if (!$table->hasIndex('parent_index')) {
				$output->writeln('<info>Adding additional parent index to the share table, this can take some time...</info>');
				$table->addIndex(['parent'], 'parent_index');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>Share table updated successfully.</info>');
			}

			if (!$table->hasIndex('owner_index')) {
				$output->writeln('<info>Adding additional owner index to the share table, this can take some time...</info>');
				$table->addIndex(['uid_owner'], 'owner_index');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>Share table updated successfully.</info>');
			}

			if (!$table->hasIndex('initiator_index')) {
				$output->writeln('<info>Adding additional initiator index to the share table, this can take some time...</info>');
				$table->addIndex(['uid_initiator'], 'initiator_index');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>Share table updated successfully.</info>');
			}
		}

		$output->writeln('<info>Check indices of the filecache table.</info>');
		if ($schema->hasTable('filecache')) {
			$table = $schema->getTable('filecache');
			if (!$table->hasIndex('fs_mtime')) {
				$output->writeln('<info>Adding additional mtime index to the filecache table, this can take some time...</info>');
				$table->addIndex(['mtime'], 'fs_mtime');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>Filecache table updated successfully.</info>');
			}
			if (!$table->hasIndex('fs_size')) {
				$output->writeln('<info>Adding additional size index to the filecache table, this can take some time...</info>');
				$table->addIndex(['size'], 'fs_size');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>Filecache table updated successfully.</info>');
			}
		}

		$output->writeln('<info>Check indices of the twofactor_providers table.</info>');
		if ($schema->hasTable('twofactor_providers')) {
			$table = $schema->getTable('twofactor_providers');
			if (!$table->hasIndex('twofactor_providers_uid')) {
				$output->writeln('<info>Adding additional twofactor_providers_uid index to the twofactor_providers table, this can take some time...</info>');
				$table->addIndex(['uid'], 'twofactor_providers_uid');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>Twofactor_providers table updated successfully.</info>');
			}
		}

		$output->writeln('<info>Check indices of the login_flow_v2 table.</info>');
		if ($schema->hasTable('login_flow_v2')) {
			$table = $schema->getTable('login_flow_v2');
			if (!$table->hasIndex('poll_token')) {
				$output->writeln('<info>Adding additional indeces to the login_flow_v2 table, this can take some time...</info>');

				foreach ($table->getIndexes() as $index) {
					$columns = $index->getColumns();
					if ($columns === ['poll_token'] ||
						$columns === ['login_token'] ||
						$columns === ['timestamp']) {
						$table->dropIndex($index->getName());
					}
				}

				$table->addUniqueIndex(['poll_token'], 'poll_token');
				$table->addUniqueIndex(['login_token'], 'login_token');
				$table->addIndex(['timestamp'], 'timestamp');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>login_flow_v2 table updated successfully.</info>');
			}
		}

		$output->writeln('<info>Check indices of the whats_new table.</info>');
		if ($schema->hasTable('whats_new')) {
			$table = $schema->getTable('whats_new');
			if (!$table->hasIndex('version')) {
				$output->writeln('<info>Adding version index to the whats_new table, this can take some time...</info>');

				foreach ($table->getIndexes() as $index) {
					if ($index->getColumns() === ['version']) {
						$table->dropIndex($index->getName());
					}
				}

				$table->addUniqueIndex(['version'], 'version');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>whats_new table updated successfully.</info>');
			}
		}

		$output->writeln('<info>Check indices of the cards table.</info>');
		$cardsUpdated = false;
		if ($schema->hasTable('cards')) {
			$table = $schema->getTable('cards');

			if ($table->hasIndex('addressbookid_uri_index')) {
				$output->writeln('<info>Renaming addressbookid_uri_index index to  to the cards table, this can take some time...</info>');

				foreach ($table->getIndexes() as $index) {
					if ($index->getColumns() === ['addressbookid', 'uri']) {
						$table->renameIndex('addressbookid_uri_index', 'cards_abiduri');
					}
				}

				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$cardsUpdated = true;
			}

			if (!$table->hasIndex('cards_abid')) {
				$output->writeln('<info>Adding cards_abid index to the cards table, this can take some time...</info>');

				foreach ($table->getIndexes() as $index) {
					if ($index->getColumns() === ['addressbookid']) {
						$table->dropIndex($index->getName());
					}
				}

				$table->addIndex(['addressbookid'], 'cards_abid');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$cardsUpdated = true;
			}

			if (!$table->hasIndex('cards_abiduri')) {
				$output->writeln('<info>Adding cards_abiduri index to the cards table, this can take some time...</info>');

				foreach ($table->getIndexes() as $index) {
					if ($index->getColumns() === ['addressbookid', 'uri']) {
						$table->dropIndex($index->getName());
					}
				}

				$table->addIndex(['addressbookid', 'uri'], 'cards_abiduri');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$cardsUpdated = true;
			}

			if ($cardsUpdated) {
				$updated = true;
				$output->writeln('<info>cards table updated successfully.</info>');
			}
		}

		$output->writeln('<info>Check indices of the cards_properties table.</info>');
		if ($schema->hasTable('cards_properties')) {
			$table = $schema->getTable('cards_properties');
			if (!$table->hasIndex('cards_prop_abid')) {
				$output->writeln('<info>Adding cards_prop_abid index to the cards_properties table, this can take some time...</info>');

				foreach ($table->getIndexes() as $index) {
					if ($index->getColumns() === ['addressbookid']) {
						$table->dropIndex($index->getName());
					}
				}

				$table->addIndex(['addressbookid'], 'cards_prop_abid');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>cards_properties table updated successfully.</info>');
			}
		}

		$output->writeln('<info>Check indices of the calendarobjects_props table.</info>');
		if ($schema->hasTable('calendarobjects_props')) {
			$table = $schema->getTable('calendarobjects_props');
			if (!$table->hasIndex('calendarobject_calid_index')) {
				$output->writeln('<info>Adding calendarobject_calid_index index to the calendarobjects_props table, this can take some time...</info>');

				$table->addIndex(['calendarid', 'calendartype'], 'calendarobject_calid_index');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>calendarobjects_props table updated successfully.</info>');
			}
		}

		$output->writeln('<info>Check indices of the schedulingobjects table.</info>');
		if ($schema->hasTable('schedulingobjects')) {
			$table = $schema->getTable('schedulingobjects');
			if (!$table->hasIndex('schedulobj_principuri_index')) {
				$output->writeln('<info>Adding schedulobj_principuri_index index to the schedulingobjects table, this can take some time...</info>');

				$table->addIndex(['principaluri'], 'schedulobj_principuri_index');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>schedulingobjects table updated successfully.</info>');
			}
		}

		$output->writeln('<info>Check indices of the oc_properties table.</info>');
		if ($schema->hasTable('properties')) {
			$table = $schema->getTable('properties');
			if (!$table->hasIndex('properties_path_index')) {
				$output->writeln('<info>Adding properties_path_index index to the oc_properties table, this can take some time...</info>');

				$table->addIndex(['userid', 'propertypath'], 'properties_path_index');
				$this->connection->migrateToSchema($schema->getWrappedSchema());
				$updated = true;
				$output->writeln('<info>oc_properties table updated successfully.</info>');
			}
		}

		if (!$updated) {
			$output->writeln('<info>Done.</info>');
		}
	}
}
