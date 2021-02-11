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

use OC\Activity\Event as IActivityEvent;
use OC\Authentication\Events\RemoteWipeFinished;
use OC\Authentication\Events\RemoteWipeStarted;
use OC\Authentication\Listeners\RemoteWipeActivityListener;
use OC\Authentication\Token\IToken;
use OCP\Activity\IManager as IActivityManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class RemoteWipeActivityListenerTests extends TestCase {

	/** @var IActivityManager|MockObject */
	private $activityManager;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var IEventListener */
	private $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->activityManager = $this->createMock(IActivityManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new RemoteWipeActivityListener(
			$this->activityManager,
			$this->logger
		);
	}

	public function testHandleUnrelated() {
		$event = new Event();

		$this->listener->handle($event);

		$this->addToAssertionCount(1);
	}

	public function testHandleRemoteWipeStarted() {
		/** @var IToken|MockObject $token */
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeStarted($token);
		$activityEvent = $this->createMock(IActivityEvent::class);
		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($activityEvent);
		$activityEvent->expects($this->once())
			->method('setApp')
			->with('core')
			->willReturnSelf();
		$activityEvent->expects($this->once())
			->method('setType')
			->with('security')
			->willReturnSelf();
		$token->method('getUID')->willReturn('user123');
		$activityEvent->expects($this->once())
			->method('setAuthor')
			->with('user123')
			->willReturnSelf();
		$activityEvent->expects($this->once())
			->method('setAffectedUser')
			->with('user123')
			->willReturnSelf();
		$token->method('getName')->willReturn('Token 1');
		$activityEvent->expects($this->once())
			->method('setSubject')
			->with('remote_wipe_start', ['name' => 'Token 1'])
			->willReturnSelf();
		$this->activityManager->expects($this->once())
			->method('publish');

		$this->listener->handle($event);
	}

	public function testHandleRemoteWipeStartedCanNotPublish() {
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeStarted($token);
		$this->activityManager->expects($this->once())
			->method('generateEvent');
		$this->activityManager->expects($this->once())
			->method('publish')
			->willThrowException(new \BadMethodCallException());

		$this->listener->handle($event);
	}

	public function testHandleRemoteWipeFinished() {
		/** @var IToken|MockObject $token */
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeFinished($token);
		$activityEvent = $this->createMock(IActivityEvent::class);
		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($activityEvent);
		$activityEvent->expects($this->once())
			->method('setApp')
			->with('core')
			->willReturnSelf();
		$activityEvent->expects($this->once())
			->method('setType')
			->with('security')
			->willReturnSelf();
		$token->method('getUID')->willReturn('user123');
		$activityEvent->expects($this->once())
			->method('setAuthor')
			->with('user123')
			->willReturnSelf();
		$activityEvent->expects($this->once())
			->method('setAffectedUser')
			->with('user123')
			->willReturnSelf();
		$token->method('getName')->willReturn('Token 1');
		$activityEvent->expects($this->once())
			->method('setSubject')
			->with('remote_wipe_finish', ['name' => 'Token 1'])
			->willReturnSelf();
		$this->activityManager->expects($this->once())
			->method('publish');

		$this->listener->handle($event);
	}
}
