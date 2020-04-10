<?php
/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Comments\Tests\Unit\Activity;

use OCA\Comments\Activity\Listener;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\App\IAppManager;
use OCP\Comments\CommentsEvent;
use OCP\Comments\IComment;
use OCP\Files\Config\ICachedMountFileInfo;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\IShareHelper;
use Test\TestCase;

class ListenerTest extends TestCase {

	/** @var Listener */
	protected $listener;

	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $activityManager;

	/** @var IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	protected $session;

	/** @var IAppManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $appManager;

	/** @var IMountProviderCollection|\PHPUnit_Framework_MockObject_MockObject */
	protected $mountProviderCollection;

	/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	protected $rootFolder;

	/** @var IShareHelper|\PHPUnit_Framework_MockObject_MockObject */
	protected $shareHelper;

	protected function setUp(): void {
		parent::setUp();

		$this->activityManager = $this->createMock(IManager::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->mountProviderCollection = $this->createMock(IMountProviderCollection::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->shareHelper = $this->createMock(IShareHelper::class);

		$this->listener = new Listener(
			$this->activityManager,
			$this->session,
			$this->appManager,
			$this->mountProviderCollection,
			$this->rootFolder,
			$this->shareHelper
		);
	}

	public function testCommentEvent() {
		$this->appManager->expects($this->any())
			->method('isInstalled')
			->with('activity')
			->willReturn(true);

		$comment = $this->createMock(IComment::class);
		$comment->expects($this->any())
			->method('getObjectType')
			->willReturn('files');

		/** @var CommentsEvent|\PHPUnit_Framework_MockObject_MockObject $event */
		$event = $this->createMock(CommentsEvent::class);
		$event->expects($this->any())
			->method('getComment')
			->willReturn($comment);
		$event->expects($this->any())
			->method('getEvent')
			->willReturn(CommentsEvent::EVENT_ADD);

		/** @var IUser|\PHPUnit_Framework_MockObject_MockObject $ownerUser */
		$ownerUser = $this->createMock(IUser::class);
		$ownerUser->expects($this->any())
			->method('getUID')
			->willReturn('937393');

		/** @var \PHPUnit_Framework_MockObject_MockObject $mount */
		$mount = $this->createMock(ICachedMountFileInfo::class);
		$mount->expects($this->any())
			->method('getUser')
			->willReturn($ownerUser); // perhaps not the right user, but does not matter in this scenario

		$mounts = [ $mount, $mount ]; // to make sure duplicates are dealt with

		$userMountCache = $this->createMock(IUserMountCache::class);
		$userMountCache->expects($this->any())
			->method('getMountsForFileId')
			->willReturn($mounts);

		$this->mountProviderCollection->expects($this->any())
			->method('getMountCache')
			->willReturn($userMountCache);

		$node = $this->createMock(Node::class);
		$nodes = [ $node ];

		$ownerFolder = $this->createMock(Folder::class);
		$ownerFolder->expects($this->any())
			->method('getById')
			->willReturn($nodes);

		$this->rootFolder->expects($this->any())
			->method('getUserFolder')
			->willReturn($ownerFolder);

		$al = [ 'users' => [
			'873304' => 'i/got/it/here',
			'254342' => 'there/i/have/it',
			'sandra' => 'and/here/i/placed/it'
		]];
		$this->shareHelper->expects($this->any())
			->method('getPathsForAccessList')
			->willReturn($al);

		$this->session->expects($this->any())
			->method('getUser')
			->willReturn($ownerUser);

		/** @var \PHPUnit_Framework_MockObject_MockObject $activity */
		$activity = $this->createMock(IEvent::class);
		$activity->expects($this->exactly(count($al['users'])))
			->method('setAffectedUser');
		$activity->expects($this->once())
			->method('setApp')
			->with('comments')
			->willReturnSelf();
		$activity->expects($this->once())
			->method('setType')
			->with('comments')
			->willReturnSelf();
		$activity->expects($this->once())
			->method('setAuthor')
			->with($ownerUser->getUID())
			->willReturnSelf();
		$activity->expects($this->once())
			->method('setObject')
			->with('files', $this->anything())
			->willReturnSelf();
		$activity->expects($this->once())
			->method('setMessage')
			->with('add_comment_message', $this->anything())
			->willReturnSelf();

		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($activity);
		$this->activityManager->expects($this->exactly(count($al['users'])))
			->method('publish');

		$this->listener->commentEvent($event);
	}
}
