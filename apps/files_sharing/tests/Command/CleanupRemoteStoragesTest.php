<?php
/**
 * @copyright Copyright (c) 2016, ownCloud GmbH.
 *
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_Sharing\Tests\Command;

use OCA\Files_Sharing\Command\CleanupRemoteStorages;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

/**
 * Class CleanupRemoteStoragesTest
 *
 * @group DB
 *
 * @package OCA\Files_Sharing\Tests\Command
 */
class CleanupRemoteStoragesTest extends TestCase {

	/**
	 * @var CleanupRemoteStorages
	 */
	private $command;

	/**
	 * @var \OCP\IDBConnection
	 */
	private $connection;

	private $storages = [
		['id' => 'shared::7b4a322b22f9d0047c38d77d471ce3cf', 'share_token' => 'f2c69dad1dc0649f26976fd210fc62e1', 'remote' => 'https://hostname.tld/owncloud1', 'user' => 'user1'],
		['id' => 'shared::efe3b456112c3780da6155d3a9b9141c', 'share_token' => 'f2c69dad1dc0649f26976fd210fc62e2', 'remote' => 'https://hostname.tld/owncloud2', 'user' => 'user2'],
		['notExistingId' => 'shared::33323d9f4ca416a9e3525b435354bc6f', 'share_token' => 'f2c69dad1dc0649f26976fd210fc62e3', 'remote' => 'https://hostname.tld/owncloud3', 'user' => 'user3'],
		['id' => 'shared::7fe41a07d3f517a923f4b2b599e72cbb', 'files_count' => 2],
		['id' => 'shared::de4aeb2f378d222b6d2c5fd8f4e42f8e', 'share_token' => 'f2c69dad1dc0649f26976fd210fc62e5', 'remote' => 'https://hostname.tld/owncloud5', 'user' => 'user5'],
		['id' => 'shared::af712293ab5eb9e6a1745a13818b99fe', 'files_count' => 3],
		['notExistingId' => 'shared::c34568c143cdac7d2f06e0800b5280f9', 'share_token' => 'f2c69dad1dc0649f26976fd210fc62e7', 'remote' => 'https://hostname.tld/owncloud7', 'user' => 'user7'],
	];

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();

		$storageQuery = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$storageQuery->insert('storages')
			->setValue('id', '?');

		$shareExternalQuery = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$shareExternalQuery->insert('share_external')
			->setValue('share_token', '?')
			->setValue('remote', '?')
			->setValue('name', '?')->setParameter(2, 'irrelevant')
			->setValue('owner', '?')->setParameter(3, 'irrelevant')
			->setValue('user', '?')
			->setValue('mountpoint', '?')->setParameter(5, 'irrelevant')
			->setValue('mountpoint_hash', '?')->setParameter(6, 'irrelevant');

		$filesQuery = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$filesQuery->insert('filecache')
			->setValue('storage', '?')
			->setValue('path', '?')
			->setValue('path_hash', '?');

		foreach ($this->storages as &$storage) {
			if (isset($storage['id'])) {
				$storageQuery->setParameter(0, $storage['id']);
				$storageQuery->execute();
				$storage['numeric_id'] = $this->connection->lastInsertId('*PREFIX*storages');
			}

			if (isset($storage['share_token'])) {
				$shareExternalQuery
					->setParameter(0, $storage['share_token'])
					->setParameter(1, $storage['remote'])
					->setParameter(4, $storage['user']);
				$shareExternalQuery->execute();
			}

			if (isset($storage['files_count'])) {
				for ($i = 0; $i < $storage['files_count']; $i++) {
					$filesQuery->setParameter(0, $storage['numeric_id']);
					$filesQuery->setParameter(1, 'file' . $i);
					$filesQuery->setParameter(2, md5('file' . $i));
					$filesQuery->execute();
				}
			}
		}

		$this->command = new CleanupRemoteStorages($this->connection);
	}

	protected function tearDown(): void {
		$storageQuery = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$storageQuery->delete('storages')
			->where($storageQuery->expr()->eq('id', $storageQuery->createParameter('id')));

		$shareExternalQuery = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$shareExternalQuery->delete('share_external')
			->where($shareExternalQuery->expr()->eq('share_token', $shareExternalQuery->createParameter('share_token')))
			->andWhere($shareExternalQuery->expr()->eq('remote', $shareExternalQuery->createParameter('remote')));

		foreach ($this->storages as $storage) {
			if (isset($storage['id'])) {
				$storageQuery->setParameter('id', $storage['id']);
				$storageQuery->execute();
			}

			if (isset($storage['share_token'])) {
				$shareExternalQuery->setParameter('share_token', $storage['share_token']);
				$shareExternalQuery->setParameter('remote', $storage['remote']);
				$shareExternalQuery->execute();
			}
		}

		parent::tearDown();
	}

	private function doesStorageExist($numericId) {
		$qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$qb->select('*')
			->from('storages')
			->where($qb->expr()->eq('numeric_id', $qb->createNamedParameter($numericId)));
		$result = $qb->execute()->fetchAll();
		if (!empty($result)) {
			return true;
		}

		$qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$qb->select('*')
			->from('filecache')
			->where($qb->expr()->eq('storage', $qb->createNamedParameter($numericId)));
		$result = $qb->execute()->fetchAll();
		if (!empty($result)) {
			return true;
		}

		return false;
	}

	/**
	 * Test cleanup of orphaned storages
	 */
	public function testCleanup() {
		$input = $this->getMockBuilder(InputInterface::class)
			->disableOriginalConstructor()
			->getMock();
		$output = $this->getMockBuilder(OutputInterface::class)
			->disableOriginalConstructor()
			->getMock();

		//

		// parent folder, `files`, ´test` and `welcome.txt` => 4 elements

		$at = 0;
		$output
			->expects($this->at($at++))
			->method('writeln')
			->with('5 remote storage(s) need(s) to be checked');
		$output
			->expects($this->at($at++))
			->method('writeln')
			->with('5 remote share(s) exist');

		$this->command->execute($input, $output);

		$this->assertTrue($this->doesStorageExist($this->storages[0]['numeric_id']));
		$this->assertTrue($this->doesStorageExist($this->storages[1]['numeric_id']));
		$this->assertFalse($this->doesStorageExist($this->storages[3]['numeric_id']));
		$this->assertTrue($this->doesStorageExist($this->storages[4]['numeric_id']));
		$this->assertFalse($this->doesStorageExist($this->storages[5]['numeric_id']));

	}
}
