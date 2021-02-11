<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Authentication\Listeners;

use OC\Authentication\Events\RemoteWipeFinished;
use OC\Authentication\Events\RemoteWipeStarted;
use OC\Authentication\Token\IToken;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Notification\IManager as INotificationManager;

/**
 * @template-implements IEventListener<\OC\Authentication\Events\ARemoteWipeEvent>
 */
class RemoteWipeNotificationsListener implements IEventListener {

	/** @var INotificationManager */
	private $notificationManager;

	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(INotificationManager $notificationManager,
								ITimeFactory $timeFactory) {
		$this->notificationManager = $notificationManager;
		$this->timeFactory = $timeFactory;
	}

	public function handle(Event $event): void {
		if ($event instanceof RemoteWipeStarted) {
			$this->sendNotification('remote_wipe_start', $event->getToken());
		} elseif ($event instanceof RemoteWipeFinished) {
			$this->sendNotification('remote_wipe_finish', $event->getToken());
		}
	}

	private function sendNotification(string $event, IToken $token): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('auth')
			->setUser($token->getUID())
			->setDateTime($this->timeFactory->getDateTime())
			->setObject('token', (string) $token->getId())
			->setSubject($event, [
				'name' => $token->getName(),
			]);
		$this->notificationManager->notify($notification);
	}
}
