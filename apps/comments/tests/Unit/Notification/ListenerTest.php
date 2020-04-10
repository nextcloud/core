<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Comments\Tests\Unit\Notification;

use OCA\Comments\Notification\Listener;
use OCP\Comments\CommentsEvent;
use OCP\Comments\IComment;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Test\TestCase;

class ListenerTest extends TestCase {
	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $notificationManager;

	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	protected $urlGenerator;

	/** @var  Listener */
	protected $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->notificationManager = $this->createMock(\OCP\Notification\IManager::class);
		$this->userManager = $this->createMock(\OCP\IUserManager::class);

		$this->listener = new Listener(
			$this->notificationManager,
			$this->userManager
		);
	}

	public function eventProvider() {
		return [
			[CommentsEvent::EVENT_ADD, 'notify'],
			[CommentsEvent::EVENT_UPDATE, 'notify'],
			[CommentsEvent::EVENT_PRE_UPDATE, 'markProcessed'],
			[CommentsEvent::EVENT_DELETE, 'markProcessed']
		];
	}

	/**
	 * @dataProvider eventProvider
	 * @param string $eventType
	 * @param string $notificationMethod
	 */
	public function testEvaluate($eventType, $notificationMethod) {
		/** @var IComment|\PHPUnit_Framework_MockObject_MockObject $comment */
		$comment = $this->getMockBuilder(IComment::class)->getMock();
		$comment->expects($this->any())
			->method('getObjectType')
			->willReturn('files');
		$comment->expects($this->any())
			->method('getCreationDateTime')
			->willReturn(new \DateTime());
		$comment->expects($this->once())
			->method('getMentions')
			->willReturn([
				[ 'type' => 'user', 'id' => 'foobar'],
				[ 'type' => 'user', 'id' => 'barfoo'],
				[ 'type' => 'user', 'id' => 'foo@bar.com'],
				[ 'type' => 'user', 'id' => 'bar@foo.org@foobar.io'],
				[ 'type' => 'user', 'id' => '23452-4333-54353-2342'],
				[ 'type' => 'user', 'id' => 'yolo'],
			]);
		$comment->expects($this->atLeastOnce())
			->method('getId')
			->willReturn('1234');

		/** @var CommentsEvent|\PHPUnit_Framework_MockObject_MockObject $event */
		$event = $this->getMockBuilder(CommentsEvent::class)
			->disableOriginalConstructor()
			->getMock();
		$event->expects($this->once())
			->method('getComment')
			->willReturn($comment);
		$event->expects(($this->any()))
			->method(('getEvent'))
			->willReturn($eventType);

		/** @var INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder(INotification::class)->getMock();
		$notification->expects($this->any())
			->method($this->anything())
			->willReturn($notification);
		$notification->expects($this->exactly(6))
			->method('setUser');

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->exactly(6))
			->method($notificationMethod)
			->with($this->isInstanceOf('\OCP\Notification\INotification'));

		$this->userManager->expects($this->exactly(6))
			->method('userExists')
			->withConsecutive(
				['foobar'],
				['barfoo'],
				['foo@bar.com'],
				['bar@foo.org@foobar.io'],
				['23452-4333-54353-2342'],
				['yolo']
			)
			->willReturn(true);

		$this->listener->evaluate($event);
	}

	/**
	 * @dataProvider eventProvider
	 * @param string $eventType
	 */
	public function testEvaluateNoMentions($eventType) {
		/** @var IComment|\PHPUnit_Framework_MockObject_MockObject $comment */
		$comment = $this->getMockBuilder(IComment::class)->getMock();
		$comment->expects($this->any())
			->method('getObjectType')
			->willReturn('files');
		$comment->expects($this->any())
			->method('getCreationDateTime')
			->willReturn(new \DateTime());
		$comment->expects($this->once())
			->method('getMentions')
			->willReturn([]);

		/** @var CommentsEvent|\PHPUnit_Framework_MockObject_MockObject $event */
		$event = $this->getMockBuilder(CommentsEvent::class)
			->disableOriginalConstructor()
			->getMock();
		$event->expects($this->once())
			->method('getComment')
			->willReturn($comment);
		$event->expects(($this->any()))
			->method(('getEvent'))
			->willReturn($eventType);

		$this->notificationManager->expects($this->never())
			->method('createNotification');
		$this->notificationManager->expects($this->never())
			->method('notify');
		$this->notificationManager->expects($this->never())
			->method('markProcessed');

		$this->userManager->expects($this->never())
			->method('userExists');

		$this->listener->evaluate($event);
	}

	public function testEvaluateUserDoesNotExist() {
		/** @var IComment|\PHPUnit_Framework_MockObject_MockObject $comment */
		$comment = $this->getMockBuilder(IComment::class)->getMock();
		$comment->expects($this->any())
			->method('getObjectType')
			->willReturn('files');
		$comment->expects($this->any())
			->method('getCreationDateTime')
			->willReturn(new \DateTime());
		$comment->expects($this->once())
			->method('getMentions')
			->willReturn([[ 'type' => 'user', 'id' => 'foobar']]);
		$comment->expects($this->atLeastOnce())
			->method('getId')
			->willReturn('1234');

		/** @var CommentsEvent|\PHPUnit_Framework_MockObject_MockObject $event */
		$event = $this->getMockBuilder(CommentsEvent::class)
			->disableOriginalConstructor()
			->getMock();
		$event->expects($this->once())
			->method('getComment')
			->willReturn($comment);
		$event->expects(($this->any()))
			->method(('getEvent'))
			->willReturn(CommentsEvent::EVENT_ADD);

		/** @var INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder(INotification::class)->getMock();
		$notification->expects($this->any())
			->method($this->anything())
			->willReturn($notification);
		$notification->expects($this->never())
			->method('setUser');

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->never())
			->method('notify');

		$this->userManager->expects($this->once())
			->method('userExists')
			->withConsecutive(
				['foobar']
			)
			->willReturn(false);

		$this->listener->evaluate($event);
	}
}
