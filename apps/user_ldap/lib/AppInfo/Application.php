<?php
/**
 * @copyright Copyright (c) 2017 Roger Szabo <roger.szabo@web.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roger Szabo <roger.szabo@web.de>
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

namespace OCA\User_LDAP\AppInfo;

use Closure;
use OCA\Files_External\Service\BackendService;
use OCA\User_LDAP\Controller\RenewPasswordController;
use OCA\User_LDAP\Events\GroupBackendRegistered;
use OCA\User_LDAP\Events\UserBackendRegistered;
use OCA\User_LDAP\Group_Proxy;
use OCA\User_LDAP\GroupPluginManager;
use OCA\User_LDAP\Handler\ExtStorageConfigHandler;
use OCA\User_LDAP\Helper;
use OCA\User_LDAP\ILDAPWrapper;
use OCA\User_LDAP\LDAP;
use OCA\User_LDAP\Notification\Notifier;
use OCA\User_LDAP\User_Proxy;
use OCA\User_LDAP\UserPluginManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IServerContainer;
use OCP\Notification\IManager as INotificationManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Application extends App implements IBootstrap {
	public function __construct() {
		parent::__construct('user_ldap');
		$container = $this->getContainer();

		/**
		 * Controller
		 */
		$container->registerService('RenewPasswordController', function (IAppContainer $appContainer) {
			/** @var IServerContainer $server */
			$server = $appContainer->get(IServerContainer::class);

			return new RenewPasswordController(
				$appContainer->get('AppName'),
				$server->getRequest(),
				$appContainer->get('UserManager'),
				$server->getConfig(),
				$appContainer->get(IL10N::class),
				$appContainer->get('Session'),
				$server->getURLGenerator()
			);
		});

		$container->registerService(ILDAPWrapper::class, function () {
			return new LDAP();
		});
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (
			INotificationManager $notificationManager,
			IAppContainer $appContainer,
			EventDispatcherInterface $legacyDispatcher,
			IEventDispatcher $dispatcher,
			IGroupManager $groupManager,
			User_Proxy $userBackend,
			Group_Proxy $groupBackend,
			Helper $helper
		) {
			$configPrefixes = $helper->getServerConfigurationPrefixes(true);
			if (count($configPrefixes) > 0) {
				$notificationManager->registerNotifierService(Notifier::class);

				$userPluginManager = $appContainer->get(UserPluginManager::class);
				$groupPluginManager = $appContainer->get(GroupPluginManager::class);

				\OC_User::useBackend($userBackend);
				$groupManager->addBackend($groupBackend);

				$userBackendRegisteredEvent = new UserBackendRegistered($userBackend, $userPluginManager);
				$legacyDispatcher->dispatch('OCA\\User_LDAP\\User\\User::postLDAPBackendAdded', $userBackendRegisteredEvent);
				$dispatcher->dispatchTyped($userBackendRegisteredEvent);
				$groupBackendRegisteredEvent = new GroupBackendRegistered($groupBackend, $groupPluginManager);
				$dispatcher->dispatchTyped($groupBackendRegisteredEvent);
			}
		});

		$context->injectFn(Closure::fromCallable([$this, 'registerBackendDependents']));

		\OCP\Util::connectHook(
			'\OCA\Files_Sharing\API\Server2Server',
			'preLoginNameUsedAsUserName',
			'\OCA\User_LDAP\Helper',
			'loginName2UserName'
		);
	}

	private function registerBackendDependents(IAppContainer $appContainer, EventDispatcherInterface $dispatcher) {
		$dispatcher->addListener(
			'OCA\\Files_External::loadAdditionalBackends',
			function () use ($appContainer) {
				$storagesBackendService = $appContainer->get(BackendService::class);
				$storagesBackendService->registerConfigHandler('home', function () use ($appContainer) {
					return $appContainer->get(ExtStorageConfigHandler::class);
				});
			}
		);
	}
}
