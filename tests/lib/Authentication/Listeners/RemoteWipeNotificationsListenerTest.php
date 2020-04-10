<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

namespace Test\Authentication\Events;

use DateTime;
use OC\Authentication\Events\RemoteWipeFinished;
use OC\Authentication\Events\RemoteWipeStarted;
use OC\Authentication\Listeners\RemoteWipeNotificationsListener;
use OC\Authentication\Token\IToken;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class RemoteWipeNotificationListenerTests extends TestCase {

	/** @var INotificationManager|MockObject */
	private $notificationManager;

	/** @var ITimeFactory|MockObject */
	private $timeFactory;

	/** @var IEventListener */
	private $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);

		$this->listener = new RemoteWipeNotificationsListener(
			$this->notificationManager,
			$this->timeFactory
		);
	}

	public function testHandleUnrelated() {
		$event = new Event();

		$this->listener->handle($event);

		$this->addToAssertionCount(1);
	}

	public function testHandleRemoteWipeStarted() {
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeStarted($token);
		$notification = $this->createMock(INotification::class);
		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$notification->expects($this->once())
			->method('setApp')
			->with('auth')
			->willReturnSelf();
		$token->method('getUID')->willReturn('user123');
		$notification->expects($this->once())
			->method('setUser')
			->with('user123')
			->willReturnSelf();
		$now = new DateTime();
		$this->timeFactory->method('getDateTime')->willReturn($now);
		$notification->expects($this->once())
			->method('setDateTime')
			->with($now)
			->willReturnSelf();
		$token->method('getId')->willReturn(123);
		$notification->expects($this->once())
			->method('setObject')
			->with('token', '123')
			->willReturnSelf();
		$token->method('getName')->willReturn('Token 1');
		$notification->expects($this->once())
			->method('setSubject')
			->with('remote_wipe_start', [
				'name' => 'Token 1'
			])
			->willReturnSelf();
		$this->notificationManager->expects($this->once())
			->method('notify');

		$this->listener->handle($event);
	}

	public function testHandleRemoteWipeFinished() {
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeFinished($token);
		$notification = $this->createMock(INotification::class);
		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$notification->expects($this->once())
			->method('setApp')
			->with('auth')
			->willReturnSelf();
		$token->method('getUID')->willReturn('user123');
		$notification->expects($this->once())
			->method('setUser')
			->with('user123')
			->willReturnSelf();
		$now = new DateTime();
		$this->timeFactory->method('getDateTime')->willReturn($now);
		$notification->expects($this->once())
			->method('setDateTime')
			->with($now)
			->willReturnSelf();
		$token->method('getId')->willReturn(123);
		$notification->expects($this->once())
			->method('setObject')
			->with('token', '123')
			->willReturnSelf();
		$token->method('getName')->willReturn('Token 1');
		$notification->expects($this->once())
			->method('setSubject')
			->with('remote_wipe_finish', [
				'name' => 'Token 1'
			])
			->willReturnSelf();
		$this->notificationManager->expects($this->once())
			->method('notify');

		$this->listener->handle($event);
	}

}
