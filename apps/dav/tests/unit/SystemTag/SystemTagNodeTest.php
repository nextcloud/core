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

use OC\SystemTag\SystemTag;
use OCP\IUser;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\TagAlreadyExistsException;
use OCP\SystemTag\TagNotFoundException;
use Sabre\DAV\Exception\Forbidden;

class SystemTagNodeTest extends \Test\TestCase {

	/**
	 * @var \OCP\SystemTag\ISystemTagManager|\PHPUnit_Framework_MockObject_MockObject
	 */
	private $tagManager;

	/**
	 * @var \OCP\IUser
	 */
	private $user;

	protected function setUp(): void {
		parent::setUp();

		$this->tagManager = $this->getMockBuilder(ISystemTagManager::class)
			->getMock();
		$this->user = $this->getMockBuilder(IUser::class)
			->getMock();
	}

	protected function getTagNode($isAdmin = true, $tag = null) {
		if ($tag === null) {
			$tag = new SystemTag(1, 'Test', true, true);
		}
		return new \OCA\DAV\SystemTag\SystemTagNode(
			$tag,
			$this->user,
			$isAdmin,
			$this->tagManager
		);
	}

	public function adminFlagProvider() {
		return [[true], [false]];
	}

	/**
	 * @dataProvider adminFlagProvider
	 */
	public function testGetters($isAdmin) {
		$tag = new SystemTag('1', 'Test', true, true);
		$node = $this->getTagNode($isAdmin, $tag);
		$this->assertEquals('1', $node->getName());
		$this->assertEquals($tag, $node->getSystemTag());
	}

	
	public function testSetName() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->getTagNode()->setName('2');
	}

	public function tagNodeProvider() {
		return [
			// admin
			[
				true,
				new SystemTag(1, 'Original', true, true),
				['Renamed', true, true]
			],
			[
				true,
				new SystemTag(1, 'Original', true, true),
				['Original', false, false]
			],
			// non-admin
			[
				// renaming allowed
				false,
				new SystemTag(1, 'Original', true, true),
				['Rename', true, true]
			],
		];
	}

	/**
	 * @dataProvider tagNodeProvider
	 */
	public function testUpdateTag($isAdmin, ISystemTag $originalTag, $changedArgs) {
		$this->tagManager->expects($this->once())
			->method('canUserSeeTag')
			->with($originalTag)
			->willReturn($originalTag->isUserVisible() || $isAdmin);
		$this->tagManager->expects($this->once())
			->method('canUserAssignTag')
			->with($originalTag)
			->willReturn($originalTag->isUserAssignable() || $isAdmin);
		$this->tagManager->expects($this->once())
			->method('updateTag')
			->with(1, $changedArgs[0], $changedArgs[1], $changedArgs[2]);
		$this->getTagNode($isAdmin, $originalTag)
			->update($changedArgs[0], $changedArgs[1], $changedArgs[2]);
	}

	public function tagNodeProviderPermissionException() {
		return [
			[
				// changing permissions not allowed
				new SystemTag(1, 'Original', true, true),
				['Original', false, true],
				'Sabre\DAV\Exception\Forbidden',
			],
			[
				// changing permissions not allowed
				new SystemTag(1, 'Original', true, true),
				['Original', true, false],
				'Sabre\DAV\Exception\Forbidden',
			],
			[
				// changing permissions not allowed
				new SystemTag(1, 'Original', true, true),
				['Original', false, false],
				'Sabre\DAV\Exception\Forbidden',
			],
			[
				// changing non-assignable not allowed
				new SystemTag(1, 'Original', true, false),
				['Rename', true, false],
				'Sabre\DAV\Exception\Forbidden',
			],
			[
				// changing non-assignable not allowed
				new SystemTag(1, 'Original', true, false),
				['Original', true, true],
				'Sabre\DAV\Exception\Forbidden',
			],
			[
				// invisible tag does not exist
				new SystemTag(1, 'Original', false, false),
				['Rename', false, false],
				'Sabre\DAV\Exception\NotFound',
			],
		];
	}

	/**
	 * @dataProvider tagNodeProviderPermissionException
	 */
	public function testUpdateTagPermissionException(ISystemTag $originalTag, $changedArgs, $expectedException = null) {
		$this->tagManager->expects($this->any())
			->method('canUserSeeTag')
			->with($originalTag)
			->willReturn($originalTag->isUserVisible());
		$this->tagManager->expects($this->any())
			->method('canUserAssignTag')
			->with($originalTag)
			->willReturn($originalTag->isUserAssignable());
		$this->tagManager->expects($this->never())
			->method('updateTag');

		$thrown = null;

		try {
			$this->getTagNode(false, $originalTag)
				->update($changedArgs[0], $changedArgs[1], $changedArgs[2]);
		} catch (\Exception $e) {
			$thrown = $e;
		}

		$this->assertInstanceOf($expectedException, $thrown);
	}

	
	public function testUpdateTagAlreadyExists() {
		$this->expectException(\Sabre\DAV\Exception\Conflict::class);

		$tag = new SystemTag(1, 'tag1', true, true);
		$this->tagManager->expects($this->any())
			->method('canUserSeeTag')
			->with($tag)
			->willReturn(true);
		$this->tagManager->expects($this->any())
			->method('canUserAssignTag')
			->with($tag)
			->willReturn(true);
		$this->tagManager->expects($this->once())
			->method('updateTag')
			->with(1, 'Renamed', true, true)
			->will($this->throwException(new TagAlreadyExistsException()));
		$this->getTagNode(false, $tag)->update('Renamed', true, true);
	}

	
	public function testUpdateTagNotFound() {
		$this->expectException(\Sabre\DAV\Exception\NotFound::class);

		$tag = new SystemTag(1, 'tag1', true, true);
		$this->tagManager->expects($this->any())
			->method('canUserSeeTag')
			->with($tag)
			->willReturn(true);
		$this->tagManager->expects($this->any())
			->method('canUserAssignTag')
			->with($tag)
			->willReturn(true);
		$this->tagManager->expects($this->once())
			->method('updateTag')
			->with(1, 'Renamed', true, true)
			->will($this->throwException(new TagNotFoundException()));
		$this->getTagNode(false, $tag)->update('Renamed', true, true);
	}

	/**
	 * @dataProvider adminFlagProvider
	 */
	public function testDeleteTag($isAdmin) {
		$tag = new SystemTag(1, 'tag1', true, true);
		$this->tagManager->expects($isAdmin ? $this->once() : $this->never())
			->method('canUserSeeTag')
			->with($tag)
			->willReturn(true);
		$this->tagManager->expects($isAdmin ? $this->once() : $this->never())
			->method('deleteTags')
			->with('1');
		if (!$isAdmin) {
			$this->expectException(Forbidden::class);
		}
		$this->getTagNode($isAdmin, $tag)->delete();
	}

	public function tagNodeDeleteProviderPermissionException() {
		return [
			[
				// cannot delete invisible tag
				new SystemTag(1, 'Original', false, true),
				'Sabre\DAV\Exception\Forbidden',
			],
			[
				// cannot delete non-assignable tag
				new SystemTag(1, 'Original', true, false),
				'Sabre\DAV\Exception\Forbidden',
			],
		];
	}

	/**
	 * @dataProvider tagNodeDeleteProviderPermissionException
	 */
	public function testDeleteTagPermissionException(ISystemTag $tag, $expectedException) {
		$this->tagManager->expects($this->any())
			->method('canUserSeeTag')
			->with($tag)
			->willReturn($tag->isUserVisible());
		$this->tagManager->expects($this->never())
			->method('deleteTags');

		$this->expectException($expectedException);
		$this->getTagNode(false, $tag)->delete();
	}

	
	public function testDeleteTagNotFound() {
		$this->expectException(\Sabre\DAV\Exception\NotFound::class);

		$tag = new SystemTag(1, 'tag1', true, true);
		$this->tagManager->expects($this->any())
			->method('canUserSeeTag')
			->with($tag)
			->willReturn($tag->isUserVisible());
		$this->tagManager->expects($this->once())
			->method('deleteTags')
			->with('1')
			->will($this->throwException(new TagNotFoundException()));
		$this->getTagNode(true, $tag)->delete();
	}
}
