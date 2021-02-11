<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OC\Authentication\Token\Manager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @template-implements IEventListener<\OCP\User\Events\UserDeletedEvent>
 */
class UserDeletedTokenCleanupListener implements IEventListener {

	/** @var Manager */
	private $manager;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(Manager $manager,
								LoggerInterface $logger) {
		$this->manager = $manager;
		$this->logger = $logger;
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserDeletedEvent)) {
			// Unrelated
			return;
		}

		/**
		 * Catch any exception during this process as any failure here shouldn't block the
		 * user deletion.
		 */
		try {
			$uid = $event->getUser()->getUID();
			$tokens = $this->manager->getTokenByUser($uid);
			foreach ($tokens as $token) {
				$this->manager->invalidateTokenById($uid, $token->getId());
			}
		} catch (Throwable $e) {
			$this->logger->error('Could not clean up auth tokens after user deletion: ' . $e->getMessage(), [
				'exception' => $e,
			]);
		}
	}
}
