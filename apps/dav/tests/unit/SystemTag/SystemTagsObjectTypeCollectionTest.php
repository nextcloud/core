<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\DAV\Tests\unit\SystemTag;

use OCP\Files\Folder;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;

class SystemTagsObjectTypeCollectionTest extends \Test\TestCase {

	/**
	 * @var \OCA\DAV\SystemTag\SystemTagsObjectTypeCollection
	 */
	private $node;

	/**
	 * @var \OCP\SystemTag\ISystemTagManager
	 */
	private $tagManager;

	/**
	 * @var \OCP\SystemTag\ISystemTagObjectMapper
	 */
	private $tagMapper;

	/**
	 * @var \OCP\Files\Folder
	 */
	private $userFolder;

	protected function setUp(): void {
		parent::setUp();

		$this->tagManager = $this->getMockBuilder(ISystemTagManager::class)
			->getMock();
		$this->tagMapper = $this->getMockBuilder(ISystemTagObjectMapper::class)
			->getMock();

		$user = $this->getMockBuilder(IUser::class)
			->getMock();
		$user->expects($this->any())
			->method('getUID')
			->willReturn('testuser');
		$userSession = $this->getMockBuilder(IUserSession::class)
			->getMock();
		$userSession->expects($this->any())
			->method('getUser')
			->willReturn($user);
		$groupManager = $this->getMockBuilder(IGroupManager::class)
			->getMock();
		$groupManager->expects($this->any())
			->method('isAdmin')
			->with('testuser')
			->willReturn(true);

		$this->userFolder = $this->getMockBuilder(Folder::class)
			->getMock();
		$userFolder = $this->userFolder;

		$closure = function ($name) use ($userFolder) {
			$nodes = $userFolder->getById(intval($name));
			return !empty($nodes);
		};

		$this->node = new \OCA\DAV\SystemTag\SystemTagsObjectTypeCollection(
			'files',
			$this->tagManager,
			$this->tagMapper,
			$userSession,
			$groupManager,
			$closure
		);
	}

	
	public function testForbiddenCreateFile() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->node->createFile('555');
	}

	
	public function testForbiddenCreateDirectory() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->node->createDirectory('789');
	}

	public function testGetChild() {
		$this->userFolder->expects($this->once())
			->method('getById')
			->with('555')
			->willReturn([true]);
		$childNode = $this->node->getChild('555');

		$this->assertInstanceOf('\OCA\DAV\SystemTag\SystemTagsObjectMappingCollection', $childNode);
		$this->assertEquals('555', $childNode->getName());
	}

	
	public function testGetChildWithoutAccess() {
		$this->expectException(\Sabre\DAV\Exception\NotFound::class);

		$this->userFolder->expects($this->once())
			->method('getById')
			->with('555')
			->willReturn([]);
		$this->node->getChild('555');
	}

	
	public function testGetChildren() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->node->getChildren();
	}

	public function testChildExists() {
		$this->userFolder->expects($this->once())
			->method('getById')
			->with('123')
			->willReturn([true]);
		$this->assertTrue($this->node->childExists('123'));
	}

	public function testChildExistsWithoutAccess() {
		$this->userFolder->expects($this->once())
			->method('getById')
			->with('555')
			->willReturn([]);
		$this->assertFalse($this->node->childExists('555'));
	}

	
	public function testDelete() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->node->delete();
	}

	
	public function testSetName() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$this->node->setName('somethingelse');
	}

	public function testGetName() {
		$this->assertEquals('files', $this->node->getName());
	}
}
