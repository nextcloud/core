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

use BadMethodCallException;
use OC\Authentication\Events\RemoteWipeFinished;
use OC\Authentication\Events\RemoteWipeStarted;
use OC\Authentication\Token\IToken;
use OCP\Activity\IManager as IActvityManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<\OC\Authentication\Events\ARemoteWipeEvent>
 */
class RemoteWipeActivityListener implements IEventListener {

	/** @var IActvityManager */
	private $activityManager;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(IActvityManager $activityManager,
								LoggerInterface $logger) {
		$this->activityManager = $activityManager;
		$this->logger = $logger;
	}

	public function handle(Event $event): void {
		if ($event instanceof RemoteWipeStarted) {
			$this->publishActivity('remote_wipe_start', $event->getToken());
		} elseif ($event instanceof RemoteWipeFinished) {
			$this->publishActivity('remote_wipe_finish', $event->getToken());
		}
	}

	private function publishActivity(string $event, IToken $token): void {
		$activity = $this->activityManager->generateEvent();
		$activity->setApp('core')
			->setType('security')
			->setAuthor($token->getUID())
			->setAffectedUser($token->getUID())
			->setSubject($event, [
				'name' => $token->getName(),
			]);
		try {
			$this->activityManager->publish($activity);
		} catch (BadMethodCallException $e) {
			$this->logger->warning('could not publish activity', [
				'app' => 'core',
				'exception' => $e,
			]);
		}
	}
}
