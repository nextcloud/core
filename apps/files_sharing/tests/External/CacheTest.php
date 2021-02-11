<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\Files_Sharing\Tests\External;

use OC\Federation\CloudIdManager;
use OCA\Files_Sharing\Tests\TestCase;
use OCP\Contacts\IManager;
use OCP\Federation\ICloudIdManager;

/**
 * Class Cache
 *
 * @group DB
 *
 * @package OCA\Files_Sharing\Tests\External
 */
class CacheTest extends TestCase {
	/** @var IManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $contactsManager;

	/**
	 * @var \OC\Files\Storage\Storage
	 **/
	private $storage;

	/**
	 * @var \OCA\Files_Sharing\External\Cache
	 */
	private $cache;

	/**
	 * @var string
	 */
	private $remoteUser;

	/** @var  ICloudIdManager */
	private $cloudIdManager;

	protected function setUp(): void {
		parent::setUp();

		$this->contactsManager = $this->createMock(IManager::class);

		$this->cloudIdManager = new CloudIdManager($this->contactsManager);
		$this->remoteUser = $this->getUniqueID('remoteuser');

		$this->storage = $this->getMockBuilder('\OCA\Files_Sharing\External\Storage')
			->disableOriginalConstructor()
			->getMock();
		$this->storage
			->expects($this->any())
			->method('getId')
			->willReturn('dummystorage::');

		$this->contactsManager->expects($this->any())
			->method('search')
			->willReturn([]);

		$this->cache = new \OCA\Files_Sharing\External\Cache(
			$this->storage,
			$this->cloudIdManager->getCloudId($this->remoteUser, 'http://example.com/owncloud')
		);
		$this->cache->put(
			'test.txt',
			[
				'mimetype' => 'text/plain',
				'size' => 5,
				'mtime' => 123,
			]
		);
	}

	protected function tearDown(): void {
		if ($this->cache) {
			$this->cache->clear();
		}
		parent::tearDown();
	}

	public function testGetInjectsOwnerDisplayName() {
		$info = $this->cache->get('test.txt');
		$this->assertEquals(
			$this->remoteUser . '@example.com/owncloud',
			$info['displayname_owner']
		);
	}

	public function testGetReturnsFalseIfNotFound() {
		$info = $this->cache->get('unexisting-entry.txt');
		$this->assertFalse($info);
	}

	public function testGetFolderPopulatesOwner() {
		$dirId = $this->cache->put(
			'subdir',
			[
				'mimetype' => 'httpd/unix-directory',
				'size' => 5,
				'mtime' => 123,
			]
		);
		$this->cache->put(
			'subdir/contents.txt',
			[
				'mimetype' => 'text/plain',
				'size' => 5,
				'mtime' => 123,
			]
		);

		$results = $this->cache->getFolderContentsById($dirId);
		$this->assertEquals(1, count($results));
		$this->assertEquals(
			$this->remoteUser . '@example.com/owncloud',
			$results[0]['displayname_owner']
		);
	}
}
