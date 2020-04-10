<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OC\Authentication\TwoFactorAuth;

use OC\Authentication\Exceptions\InvalidProviderException;
use OCP\Authentication\TwoFactorAuth\IActivatableByAdmin;
use OCP\Authentication\TwoFactorAuth\IDeactivatableByAdmin;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\IUser;

class ProviderManager {

	/** @var ProviderLoader */
	private $providerLoader;

	/** @var IRegistry */
	private $providerRegistry;

	public function __construct(ProviderLoader $providerLoader, IRegistry $providerRegistry) {
		$this->providerLoader = $providerLoader;
		$this->providerRegistry = $providerRegistry;
	}

	private function getProvider(string $providerId, IUser $user): IProvider {
		$providers = $this->providerLoader->getProviders($user);

		if (!isset($providers[$providerId])) {
			throw new InvalidProviderException($providerId);
		}

		return $providers[$providerId];
	}

	/**
	 * Try to enable the provider with the given id for the given user
	 *
	 * @param IUser $user
	 *
	 * @return bool whether the provider supports this operation
	 */
	public function tryEnableProviderFor(string $providerId, IUser $user): bool {
		$provider = $this->getProvider($providerId, $user);

		if ($provider instanceof IActivatableByAdmin) {
			$provider->enableFor($user);
			$this->providerRegistry->enableProviderFor($provider, $user);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Try to disable the provider with the given id for the given user
	 *
	 * @param IUser $user
	 *
	 * @return bool whether the provider supports this operation
	 */
	public function tryDisableProviderFor(string $providerId, IUser $user): bool {
		$provider = $this->getProvider($providerId, $user);

		if ($provider instanceof IDeactivatableByAdmin) {
			$provider->disableFor($user);
			$this->providerRegistry->disableProviderFor($provider, $user);
			return true;
		} else {
			return false;
		}
	}

}
