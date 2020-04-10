<?php
/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OC\Contacts\ContactsMenu;

use Exception;
use OC\App\AppManager;
use OC\Contacts\ContactsMenu\Providers\EMailProvider;
use OCP\AppFramework\QueryException;
use OCP\Contacts\ContactsMenu\IProvider;
use OCP\ILogger;
use OCP\IServerContainer;
use OCP\IUser;

class ActionProviderStore {

	/** @var IServerContainer */
	private $serverContainer;

	/** @var AppManager */
	private $appManager;

	/** @var ILogger */
	private $logger;

	/**
	 * @param IServerContainer $serverContainer
	 * @param AppManager $appManager
	 * @param ILogger $logger
	 */
	public function __construct(IServerContainer $serverContainer, AppManager $appManager, ILogger $logger) {
		$this->serverContainer = $serverContainer;
		$this->appManager = $appManager;
		$this->logger = $logger;
	}

	/**
	 * @param IUser $user
	 * @return IProvider[]
	 * @throws Exception
	 */
	public function getProviders(IUser $user) {
		$appClasses = $this->getAppProviderClasses($user);
		$providerClasses = $this->getServerProviderClasses();
		$allClasses = array_merge($providerClasses, $appClasses);
		$providers = [];

		foreach ($allClasses as $class) {
			try {
				$providers[] = $this->serverContainer->query($class);
			} catch (QueryException $ex) {
				$this->logger->logException($ex, [
					'message' => "Could not load contacts menu action provider $class",
					'app' => 'core',
				]);
				throw new Exception("Could not load contacts menu action provider");
			}
		}

		return $providers;
	}

	/**
	 * @return string[]
	 */
	private function getServerProviderClasses() {
		return [
			EMailProvider::class,
		];
	}

	/**
	 * @param IUser $user
	 * @return string[]
	 */
	private function getAppProviderClasses(IUser $user) {
		return array_reduce($this->appManager->getEnabledAppsForUser($user), function ($all, $appId) {
			$info = $this->appManager->getAppInfo($appId);

			if (!isset($info['contactsmenu']) || !isset($info['contactsmenu'])) {
				// Nothing to add
				return $all;
			}

			$providers = array_reduce($info['contactsmenu'], function ($all, $provider) {
				return array_merge($all, [$provider]);
			}, []);

			return array_merge($all, $providers);
		}, []);
	}

}
