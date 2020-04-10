<?php
/**
 * @copyright 2016 Joas Schilling <coding@schilljs.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Repair\NC11;

use OC\Repair\NC11\FixMountStorages;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * Class FixMountStoragesTest
 *
 * @package Test\Repair\NC11
 * @group DB
 */
class FixMountStoragesTest extends TestCase {

	/** @var IDBConnection */
	private $db;

	/** @var FixMountStorages */
	private $repair;

	protected function setUp(): void {
		parent::setUp();

		$this->db = \OC::$server->getDatabaseConnection();

		$this->repair = new FixMountStorages(
			$this->db
		);
	}

	public function testGetName() {
		$this->assertSame('Fix potential broken mount points', $this->repair->getName());
	}

	public function testRun() {
		// Valid mount
		$file1 = $this->createFile(42);
		$mount1 = $this->createMount($file1, 42);
		$this->assertStorage($mount1, 42);

		// Broken mount
		$file2 = $this->createFile(23);
		$mount2 = $this->createMount($file2, 1337);
		$this->assertStorage($mount2, 1337);

		/** @var IOutput|\PHPUnit_Framework_MockObject_MockObject $output */
		$output = $this->createMock(IOutput::class);
		$output->expects($this->at(0))
			->method('info')
			->with('1 mounts updated');

		$this->repair->run($output);
		$this->assertStorage($mount1, 42);
		$this->assertStorage($mount2, 23);

		$output->expects($this->at(0))
			->method('info')
			->with('No mounts updated');

		$this->repair->run($output);
		$this->assertStorage($mount1, 42);
		$this->assertStorage($mount2, 23);
	}


	protected function createFile($storage) {
		$query = $this->db->getQueryBuilder();

		$query->insert('filecache')
			->values([
				'storage' => $query->createNamedParameter($storage, IQueryBuilder::PARAM_INT),
				'path_hash' => $query->createNamedParameter(static::getUniqueID(), IQueryBuilder::PARAM_STR),
				'encrypted' => $query->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'size' => $query->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'unencrypted_size' => $query->createNamedParameter(0, IQueryBuilder::PARAM_INT),
			]);
		$query->execute();

		return $query->getLastInsertId();
	}

	protected function createMount($fileId, $storage) {
		$query = $this->db->getQueryBuilder();

		$query->insert('mounts')
			->values([
				'storage_id' => $query->createNamedParameter($storage, IQueryBuilder::PARAM_INT),
				'root_id' => $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT),
				'user_id' => $query->createNamedParameter(static::getUniqueID(), IQueryBuilder::PARAM_STR),
				'mount_point' => $query->createNamedParameter(static::getUniqueID(), IQueryBuilder::PARAM_STR),
			]);
		$query->execute();

		return $query->getLastInsertId();
	}

	protected function assertStorage($mount, $storage) {
		$query = $this->db->getQueryBuilder();
		$query->select('storage_id')
			->from('mounts')
			->where($query->expr()->eq('id', $query->createNamedParameter($mount, IQueryBuilder::PARAM_INT)));
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		$this->assertEquals($storage, $row['storage_id']);
	}
}
