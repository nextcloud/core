<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Tests\unit\Comments;

use OCA\DAV\Comments\EntityCollection as EntityCollectionImplemantation;
use OCP\Comments\ICommentsManager;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IUserSession;

class EntityTypeCollectionTest extends \Test\TestCase {

	/** @var ICommentsManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $commentsManager;
	/** @var \OCP\IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;
	/** @var \OCP\ILogger|\PHPUnit_Framework_MockObject_MockObject */
	protected $logger;
	/** @var \OCA\DAV\Comments\EntityTypeCollection */
	protected $collection;
	/** @var IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	protected $userSession;

	protected $childMap = [];

	protected function setUp(): void {
		parent::setUp();

		$this->commentsManager = $this->getMockBuilder(ICommentsManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession = $this->getMockBuilder(IUserSession::class)
			->disableOriginalConstructor()
			->getMock();
		$this->logger = $this->getMockBuilder(ILogger::class)
			->disableOriginalConstructor()
			->getMock();

		$instance = $this;

		$this->collection = new \OCA\DAV\Comments\EntityTypeCollection(
			'files',
			$this->commentsManager,
			$this->userManager,
			$this->userSession,
			$this->logger,
			function ($child) use ($instance) {
				return !empty($instance->childMap[$child]);
			}
		);
	}

	public function testChildExistsYes() {
		$this->childMap[17] = true;
		$this->assertTrue($this->collection->childExists('17'));
	}

	public function testChildExistsNo() {
		$this->assertFalse($this->collection->childExists('17'));
	}

	public function testGetChild() {
		$this->childMap[17] = true;

		$ec = $this->collection->getChild('17');
		$this->assertTrue($ec instanceof EntityCollectionImplemantation);
	}

	
	public function testGetChildException() {
		$this->expectException(\Sabre\DAV\Exception\NotFound::class);

		$this->collection->getChild('17');
	}

	
	public function testGetChildren() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->collection->getChildren();
	}
}
