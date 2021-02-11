<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OC\Authentication\TwoFactorAuth;

use OC\Authentication\TwoFactorAuth\Db\ProviderUserAssignmentDao;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\Authentication\TwoFactorAuth\RegistryEvent;
use OCP\Authentication\TwoFactorAuth\TwoFactorProviderDisabled;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;

class Registry implements IRegistry {

	/** @var ProviderUserAssignmentDao */
	private $assignmentDao;

	/** @var IEventDispatcher */
	private $dispatcher;

	public function __construct(ProviderUserAssignmentDao $assignmentDao,
								IEventDispatcher $dispatcher) {
		$this->assignmentDao = $assignmentDao;
		$this->dispatcher = $dispatcher;
	}

	public function getProviderStates(IUser $user): array {
		return $this->assignmentDao->getState($user->getUID());
	}

	public function enableProviderFor(IProvider $provider, IUser $user) {
		$this->assignmentDao->persist($provider->getId(), $user->getUID(), 1);

		$event = new RegistryEvent($provider, $user);
		$this->dispatcher->dispatch(self::EVENT_PROVIDER_ENABLED, $event);
	}

	public function disableProviderFor(IProvider $provider, IUser $user) {
		$this->assignmentDao->persist($provider->getId(), $user->getUID(), 0);

		$event = new RegistryEvent($provider, $user);
		$this->dispatcher->dispatch(self::EVENT_PROVIDER_DISABLED, $event);
	}

	public function deleteUserData(IUser $user): void {
		foreach ($this->assignmentDao->deleteByUser($user->getUID()) as $provider) {
			$event = new TwoFactorProviderDisabled($provider['provider_id']);
			$this->dispatcher->dispatchTyped($event);
		}
	}

	public function cleanUp(string $providerId) {
		$this->assignmentDao->deleteAll($providerId);
	}
}
