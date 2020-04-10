<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
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
use OCA\TwoFactorBackupCodes\Listener\ClearNotifications;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Test\TestCase;

class ClearNotificationsTest extends TestCase {

	/** @var IManager|\PHPUnit\Framework\MockObject\MockObject */
	private $notificationManager;

	/** @var ClearNotifications */
	private $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->notificationManager = $this->createMock(IManager::class);
		$this->notificationManager->method('createNotification')
			->willReturn(\OC::$server->query(IManager::class)->createNotification());

		$this->listener = new ClearNotifications($this->notificationManager);
	}

	public function testHandleGenericEvent() {
		$event = $this->createMock(Event::class);
		$this->notificationManager->expects($this->never())
			->method($this->anything());

		$this->listener->handle($event);
	}

	public function testHandleCodesGeneratedEvent() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('fritz');
		$event = new CodesGenerated($user);

		$this->notificationManager->expects($this->once())
			->method('markProcessed')
			->with($this->callback(function (INotification $n) {
				return $n->getUser() === 'fritz' &&
					$n->getApp() === 'twofactor_backupcodes' &&
					$n->getObjectType() === 'create' &&
					$n->getObjectId() === 'codes';
			}));

		$this->listener->handle($event);
	}
}
