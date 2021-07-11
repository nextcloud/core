<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Morris Jobke <hey@morrisjobke.de>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Files_External\Listener;

use OCA\Files_External\Lib\Auth\Password\LoginCredentials;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Security\ICredentialsManager;
use OCP\User\Events\PasswordUpdatedEvent;
use OCP\User\Events\UserLoggedInEvent;

class StorePasswordListener implements IEventListener {
	/** @var ICredentialsManager */
	private $credentialsManager;

	public function __construct(ICredentialsManager $credentialsManager) {
		$this->credentialsManager = $credentialsManager;
	}

	public function handle(Event $event): void {
		if (!$event instanceof UserLoggedInEvent && !$event instanceof PasswordUpdatedEvent) {
			return;
		}

		if ($event instanceof UserLoggedInEvent && $event->isTokenLogin()) {
			return;
		}

		$stored = (array)$this->credentialsManager->retrieve($event->getUser()->getUID(), LoginCredentials::CREDENTIALS_IDENTIFIER);
		$credentials = $stored;

		/** Case 1: PasswordUpdateEvent with saved credentials => Update password */
		if ($event instanceof PasswordUpdatedEvent
			&& isset($credentials['user'], $credentials['password'])) {
			$credentials['password'] = $event->getPassword();
		}

		/** Case 2: UserLoggedInEvent => Update user or password */
		if ($event instanceof UserLoggedInEvent) {
			$credentials['user'] = $event->getLoginName();
			$credentials['password'] = $event->getPassword();
		}

		if ($stored !== $credentials) {
			$this->credentialsManager->store($event->getUser()->getUID(), LoginCredentials::CREDENTIALS_IDENTIFIER, $credentials);
		}
	}
}
