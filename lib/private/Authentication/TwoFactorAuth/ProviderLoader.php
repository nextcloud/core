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

use Exception;
use OC;
use OC_App;
use OCP\App\IAppManager;
use OCP\AppFramework\QueryException;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\IUser;

class ProviderLoader {

	const BACKUP_CODES_APP_ID = 'twofactor_backupcodes';

	/** @var IAppManager */
	private $appManager;

	public function __construct(IAppManager $appManager) {
		$this->appManager = $appManager;
	}

	/**
	 * Get the list of 2FA providers for the given user
	 *
	 * @return IProvider[]
	 * @throws Exception
	 */
	public function getProviders(IUser $user): array {
		$allApps = $this->appManager->getEnabledAppsForUser($user);
		$providers = [];

		foreach ($allApps as $appId) {
			$info = $this->appManager->getAppInfo($appId);
			if (isset($info['two-factor-providers'])) {
				/** @var string[] $providerClasses */
				$providerClasses = $info['two-factor-providers'];
				foreach ($providerClasses as $class) {
					try {
						$this->loadTwoFactorApp($appId);
						$provider = OC::$server->query($class);
						$providers[$provider->getId()] = $provider;
					} catch (QueryException $exc) {
						// Provider class can not be resolved
						throw new Exception("Could not load two-factor auth provider $class");
					}
				}
			}
		}

		return $providers;
	}

	/**
	 * Load an app by ID if it has not been loaded yet
	 *
	 * @param string $appId
	 */
	protected function loadTwoFactorApp(string $appId) {
		if (!OC_App::isAppLoaded($appId)) {
			OC_App::loadApp($appId);
		}
	}

}
