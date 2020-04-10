<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCA\Files_External\Tests\Service;

use OCA\Files_External\Service\DBConfigService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
class DBConfigServiceTest extends TestCase {
	/**
	 * @var DBConfigService
	 */
	private $dbConfig;

	/**
	 * @var IDBConnection
	 */
	private $connection;

	private $mounts = [];

	protected function setUp(): void {
		parent::setUp();
		$this->connection = \OC::$server->getDatabaseConnection();
		$this->dbConfig = new DBConfigService($this->connection, \OC::$server->getCrypto());
	}

	protected function tearDown(): void {
		foreach ($this->mounts as $mount) {
			$this->dbConfig->removeMount($mount);
		}
		$this->mounts = [];
	}

	private function addMount($mountPoint, $storageBackend, $authBackend, $priority, $type) {
		$id = $this->dbConfig->addMount($mountPoint, $storageBackend, $authBackend, $priority, $type);
		$this->mounts[] = $id;
		return $id;
	}

	public function testAddSimpleMount() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals('/test', $mount['mount_point']);
		$this->assertEquals('foo', $mount['storage_backend']);
		$this->assertEquals('bar', $mount['auth_backend']);
		$this->assertEquals(100, $mount['priority']);
		$this->assertEquals(DBConfigService::MOUNT_TYPE_ADMIN, $mount['type']);
		$this->assertEquals([], $mount['applicable']);
		$this->assertEquals([], $mount['config']);
		$this->assertEquals([], $mount['options']);
	}

	public function testAddApplicable() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, 'test');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals([
			['type' => DBConfigService::APPLICABLE_TYPE_USER, 'value' => 'test', 'mount_id' => $id]
		], $mount['applicable']);

		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_GROUP, 'bar');
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_GLOBAL, null);

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals([
			['type' => DBConfigService::APPLICABLE_TYPE_USER, 'value' => 'test', 'mount_id' => $id],
			['type' => DBConfigService::APPLICABLE_TYPE_GROUP, 'value' => 'bar', 'mount_id' => $id],
			['type' => DBConfigService::APPLICABLE_TYPE_GLOBAL, 'value' => null, 'mount_id' => $id]
		], $mount['applicable']);
	}

	public function testAddApplicableDouble() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, 'test');
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, 'test');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals([
			['type' => DBConfigService::APPLICABLE_TYPE_USER, 'value' => 'test', 'mount_id' => $id]
		], $mount['applicable']);
	}

	public function testDeleteMount() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);

		$this->dbConfig->removeMount($id);

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(null, $mount);
	}

	public function testRemoveApplicable() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, 'test');
		$this->dbConfig->removeApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, 'test');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals([], $mount['applicable']);
	}

	public function testRemoveApplicableGlobal() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_GLOBAL, null);
		$this->dbConfig->removeApplicable($id, DBConfigService::APPLICABLE_TYPE_GLOBAL, null);
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, 'test');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals([
			['type' => DBConfigService::APPLICABLE_TYPE_USER, 'value' => 'test', 'mount_id' => $id]
		], $mount['applicable']);
	}

	public function testSetConfig() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->setConfig($id, 'foo', 'bar');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(['foo' => 'bar'], $mount['config']);

		$this->dbConfig->setConfig($id, 'foo2', 'bar2');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(['foo' => 'bar', 'foo2' => 'bar2'], $mount['config']);
	}

	public function testSetConfigOverwrite() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->setConfig($id, 'foo', 'bar');
		$this->dbConfig->setConfig($id, 'asd', '1');
		$this->dbConfig->setConfig($id, 'foo', 'qwerty');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(['foo' => 'qwerty', 'asd' => '1'], $mount['config']);
	}

	public function testSetOption() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->setOption($id, 'foo', 'bar');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(['foo' => 'bar'], $mount['options']);

		$this->dbConfig->setOption($id, 'foo2', 'bar2');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(['foo' => 'bar', 'foo2' => 'bar2'], $mount['options']);
	}

	public function testSetOptionOverwrite() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->setOption($id, 'foo', 'bar');
		$this->dbConfig->setOption($id, 'asd', '1');
		$this->dbConfig->setOption($id, 'foo', 'qwerty');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(['foo' => 'qwerty', 'asd' => '1'], $mount['options']);
	}

	public function testGetMountsFor() {
		$mounts = $this->dbConfig->getMountsFor(DBConfigService::APPLICABLE_TYPE_USER, 'test');
		$this->assertEquals([], $mounts);

		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, 'test');

		$mounts = $this->dbConfig->getMountsFor(DBConfigService::APPLICABLE_TYPE_USER, 'test');
		$this->assertCount(1, $mounts);
		$this->assertEquals($id, $mounts[0]['mount_id']);
		$this->assertEquals([['type' => DBConfigService::APPLICABLE_TYPE_USER, 'value' => 'test', 'mount_id' => $id]], $mounts[0]['applicable']);
	}

	public function testGetAdminMounts() {
		$id1 = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->addMount('/test2', 'foo2', 'bar2', 100, DBConfigService::MOUNT_TYPE_PERSONAl);

		$mounts = $this->dbConfig->getAdminMounts();
		$this->assertCount(1, $mounts);
		$this->assertEquals($id1, $mounts[0]['mount_id']);
	}

	public function testGetAdminMountsFor() {
		$id1 = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->addMount('/test2', 'foo2', 'bar2', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$id3 = $this->addMount('/test3', 'foo3', 'bar3', 100, DBConfigService::MOUNT_TYPE_PERSONAl);

		$this->dbConfig->addApplicable($id1, DBConfigService::APPLICABLE_TYPE_USER, 'test');
		$this->dbConfig->addApplicable($id3, DBConfigService::APPLICABLE_TYPE_USER, 'test');

		$mounts = $this->dbConfig->getAdminMountsFor(DBConfigService::APPLICABLE_TYPE_USER, 'test');
		$this->assertCount(1, $mounts);
		$this->assertEquals($id1, $mounts[0]['mount_id']);
		$this->assertEquals([['type' => DBConfigService::APPLICABLE_TYPE_USER, 'value' => 'test', 'mount_id' => $id1]], $mounts[0]['applicable']);
	}

	public function testGetUserMountsFor() {
		$id1 = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->addMount('/test2', 'foo2', 'bar2', 100, DBConfigService::MOUNT_TYPE_PERSONAl);
		$id3 = $this->addMount('/test3', 'foo3', 'bar3', 100, DBConfigService::MOUNT_TYPE_PERSONAl);

		$this->dbConfig->addApplicable($id1, DBConfigService::APPLICABLE_TYPE_USER, 'test');
		$this->dbConfig->addApplicable($id3, DBConfigService::APPLICABLE_TYPE_USER, 'test');

		$mounts = $this->dbConfig->getUserMountsFor(DBConfigService::APPLICABLE_TYPE_USER, 'test');
		$this->assertCount(1, $mounts);
		$this->assertEquals($id3, $mounts[0]['mount_id']);
		$this->assertEquals([['type' => DBConfigService::APPLICABLE_TYPE_USER, 'value' => 'test', 'mount_id' => $id3]], $mounts[0]['applicable']);
	}

	public function testGetAdminMountsForGlobal() {
		$id1 = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);

		$this->dbConfig->addApplicable($id1, DBConfigService::APPLICABLE_TYPE_GLOBAL, null);

		$mounts = $this->dbConfig->getAdminMountsFor(DBConfigService::APPLICABLE_TYPE_GLOBAL, null);
		$this->assertCount(1, $mounts);
		$this->assertEquals($id1, $mounts[0]['mount_id']);
		$this->assertEquals([['type' => DBConfigService::APPLICABLE_TYPE_GLOBAL, 'value' => null, 'mount_id' => $id1]], $mounts[0]['applicable']);
	}

	public function testSetMountPoint() {
		$id1 = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$id2 = $this->addMount('/foo', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);

		$this->dbConfig->setMountPoint($id1, '/asd');

		$mount = $this->dbConfig->getMountById($id1);
		$this->assertEquals('/asd', $mount['mount_point']);

		// remains unchanged
		$mount = $this->dbConfig->getMountById($id2);
		$this->assertEquals('/foo', $mount['mount_point']);
	}

	public function testSetAuthBackend() {
		$id1 = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$id2 = $this->addMount('/foo', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);

		$this->dbConfig->setAuthBackend($id1, 'none');

		$mount = $this->dbConfig->getMountById($id1);
		$this->assertEquals('none', $mount['auth_backend']);

		// remains unchanged
		$mount = $this->dbConfig->getMountById($id2);
		$this->assertEquals('bar', $mount['auth_backend']);
	}

	public function testGetMountsForDuplicateByGroup() {
		$id1 = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);

		$this->dbConfig->addApplicable($id1, DBConfigService::APPLICABLE_TYPE_GROUP, 'group1');
		$this->dbConfig->addApplicable($id1, DBConfigService::APPLICABLE_TYPE_GROUP, 'group2');

		$mounts = $this->dbConfig->getAdminMountsForMultiple(DBConfigService::APPLICABLE_TYPE_GROUP, ['group1', 'group2']);
		$this->assertCount(1, $mounts);
		$this->assertEquals($id1, $mounts[0]['mount_id']);
	}

	public function testGetAllMounts() {
		$id1 = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$id2 = $this->addMount('/test2', 'foo2', 'bar2', 100, DBConfigService::MOUNT_TYPE_PERSONAl);

		$mounts = $this->dbConfig->getAllMounts();
		$this->assertCount(2, $mounts);
		$this->assertEquals($id1, $mounts[0]['mount_id']);
		$this->assertEquals($id2, $mounts[1]['mount_id']);
	}
}
