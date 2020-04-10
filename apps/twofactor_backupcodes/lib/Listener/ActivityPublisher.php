<?php

declare(strict_types=1);

/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\TwoFactorBackupCodes\Listener;

use BadMethodCallException;
use OCA\TwoFactorBackupCodes\Event\CodesGenerated;
use OCP\Activity\IManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\ILogger;

class ActivityPublisher implements IEventListener {

	/** @var IManager */
	private $activityManager;

	/** @var ILogger */
	private $logger;

	public function __construct(IManager $activityManager,
								ILogger $logger) {
		$this->activityManager = $activityManager;
		$this->logger = $logger;
	}

	/**
	 * Push an event to the user's activity stream
	 */
	public function handle(Event $event): void {
		if ($event instanceof CodesGenerated) {
			$activity = $this->activityManager->generateEvent();
			$activity->setApp('twofactor_backupcodes')
				->setType('security')
				->setAuthor($event->getUser()->getUID())
				->setAffectedUser($event->getUser()->getUID())
				->setSubject('codes_generated');
			try {
				$this->activityManager->publish($activity);
			} catch (BadMethodCallException $e) {
				$this->logger->warning('could not publish backup code creation activity', ['app' => 'twofactor_backupcodes']);
				$this->logger->logException($e, ['app' => 'twofactor_backupcodes']);
			}
		}
	}

}
