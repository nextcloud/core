<?php

declare(strict_types=1);

/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\TwoFactorBackupCodes\Tests\Unit\Listener;

use OCA\TwoFactorBackupCodes\Event\CodesGenerated;
use OCA\TwoFactorBackupCodes\Listener\ActivityPublisher;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\EventDispatcher\Event;
use OCP\ILogger;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ActivityPublisherTest extends TestCase {

	/** @var IManager|MockObject */
	private $activityManager;

	/** @var ILogger */

	private $logger;

	/** @var ActivityPublisher */
	private $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->activityManager = $this->createMock(IManager::class);
		$this->logger = $this->createMock(ILogger::class);

		$this->listener = new ActivityPublisher($this->activityManager, $this->logger);
	}

	public function testHandleGenericEvent() {
		$event = $this->createMock(Event::class);
		$this->activityManager->expects($this->never())
			->method('publish');

		$this->listener->handle($event);
	}

	public function testHandleCodesGeneratedEvent() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('fritz');
		$event = new CodesGenerated($user);
		$activityEvent = $this->createMock(IEvent::class);
		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($activityEvent);
		$activityEvent->expects($this->once())
			->method('setApp')
			->with('twofactor_backupcodes')
			->willReturnSelf();
		$activityEvent->expects($this->once())
			->method('setType')
			->with('security')
			->willReturnSelf();
		$activityEvent->expects($this->once())
			->method('setAuthor')
			->with('fritz')
			->willReturnSelf();
		$activityEvent->expects($this->once())
			->method('setAffectedUser')
			->with('fritz')
			->willReturnSelf();
		$this->activityManager->expects($this->once())
			->method('publish')
			->willReturn($activityEvent);

		$this->listener->handle($event);
	}

}
