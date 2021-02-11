<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\Files_Sharing\Listener;

use OCP\Contacts\Events\ContactInteractedWithEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\IShare;
use function in_array;

class ShareInteractionListener implements IEventListener {
	private const SUPPORTED_SHARE_TYPES = [
		IShare::TYPE_USER,
		IShare::TYPE_EMAIL,
		IShare::TYPE_REMOTE,
	];

	/** @var IEventDispatcher */
	private $dispatcher;

	/** @var IUserManager */
	private $userManager;

	/** @var ILogger */
	private $logger;

	public function __construct(IEventDispatcher $dispatcher,
								IUserManager $userManager,
								ILogger $logger) {
		$this->dispatcher = $dispatcher;
		$this->userManager = $userManager;
		$this->logger = $logger;
	}

	public function handle(Event $event): void {
		if (!($event instanceof ShareCreatedEvent)) {
			// Unrelated
			return;
		}

		$share = $event->getShare();
		if (!in_array($share->getShareType(), self::SUPPORTED_SHARE_TYPES, true)) {
			$this->logger->debug('Share type does not allow to emit interaction event');
			return;
		}
		$actor = $this->userManager->get($share->getSharedBy());
		$sharedWith = $this->userManager->get($share->getSharedWith());
		if ($actor === null) {
			$this->logger->warning('Share was not created by a user, can\'t emit interaction event');
			return;
		}
		$interactionEvent = new ContactInteractedWithEvent($actor);
		switch ($share->getShareType()) {
			case IShare::TYPE_USER:
				$interactionEvent->setUid($share->getSharedWith());
				if ($sharedWith !== null) {
					$interactionEvent->setFederatedCloudId($sharedWith->getCloudId());
				}
				break;
			case IShare::TYPE_EMAIL:
				$interactionEvent->setEmail($share->getSharedWith());
				break;
			case IShare::TYPE_REMOTE:
				$interactionEvent->setFederatedCloudId($share->getSharedWith());
				break;
		}

		$this->dispatcher->dispatchTyped($interactionEvent);
	}
}
