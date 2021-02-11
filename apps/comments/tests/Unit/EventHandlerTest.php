<?php
/**
 * @copyright Copyright (c) 2016 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Comments\Tests\Unit\Notification;

use OCA\Comments\Activity\Listener as ActivityListener;
use OCA\Comments\EventHandler;
use OCA\Comments\Notification\Listener as NotificationListener;
use OCP\Comments\CommentsEvent;
use OCP\Comments\IComment;
use Test\TestCase;

class EventHandlerTest extends TestCase {
	/** @var  EventHandler */
	protected $eventHandler;

	/** @var ActivityListener|\PHPUnit\Framework\MockObject\MockObject */
	protected $activityListener;

	/** @var NotificationListener|\PHPUnit\Framework\MockObject\MockObject */
	protected $notificationListener;

	protected function setUp(): void {
		parent::setUp();

		$this->activityListener = $this->getMockBuilder(ActivityListener::class)
			->disableOriginalConstructor()
			->getMock();

		$this->notificationListener = $this->getMockBuilder(NotificationListener::class)
			->disableOriginalConstructor()
			->getMock();

		$this->eventHandler = new EventHandler($this->activityListener, $this->notificationListener);
	}

	public function testNotFiles() {
		/** @var IComment|\PHPUnit\Framework\MockObject\MockObject $comment */
		$comment = $this->getMockBuilder(IComment::class)->getMock();
		$comment->expects($this->once())
			->method('getObjectType')
			->willReturn('smiles');

		/** @var CommentsEvent|\PHPUnit\Framework\MockObject\MockObject $event */
		$event = $this->getMockBuilder(CommentsEvent::class)
			->disableOriginalConstructor()
			->getMock();
		$event->expects($this->once())
			->method('getComment')
			->willReturn($comment);
		$event->expects($this->never())
			->method('getEvent');

		$this->eventHandler->handle($event);
	}

	public function handledProvider() {
		return [
			[CommentsEvent::EVENT_DELETE],
			[CommentsEvent::EVENT_UPDATE],
			[CommentsEvent::EVENT_PRE_UPDATE],
			[CommentsEvent::EVENT_ADD]
		];
	}

	/**
	 * @dataProvider handledProvider
	 * @param string $eventType
	 */
	public function testHandled($eventType) {
		/** @var IComment|\PHPUnit\Framework\MockObject\MockObject $comment */
		$comment = $this->getMockBuilder(IComment::class)->getMock();
		$comment->expects($this->once())
			->method('getObjectType')
			->willReturn('files');

		/** @var CommentsEvent|\PHPUnit\Framework\MockObject\MockObject $event */
		$event = $this->getMockBuilder(CommentsEvent::class)
			->disableOriginalConstructor()
			->getMock();
		$event->expects($this->atLeastOnce())
			->method('getComment')
			->willReturn($comment);
		$event->expects($this->atLeastOnce())
			->method('getEvent')
			->willReturn($eventType);

		$this->notificationListener->expects($this->once())
			->method('evaluate')
			->with($event);

		$this->activityListener->expects($this->any())
			->method('commentEvent')
			->with($event);

		$this->eventHandler->handle($event);
	}
}
