<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_Sharing\AppInfo;

use OC\AppFramework\Utility\SimpleContainer;
use OCA\Files_Sharing\Capabilities;
use OCA\Files_Sharing\Controller\ExternalSharesController;
use OCA\Files_Sharing\Controller\ShareController;
use OCA\Files_Sharing\External\Manager;
use OCA\Files_Sharing\Listener\LoadAdditionalListener;
use OCA\Files_Sharing\Listener\LoadSidebarListener;
use OCA\Files_Sharing\Listener\ShareInteractionListener;
use OCA\Files_Sharing\Listener\UserAddedToGroupListener;
use OCA\Files_Sharing\Listener\UserShareAcceptanceListener;
use OCA\Files_Sharing\Middleware\OCSShareAPIMiddleware;
use OCA\Files_Sharing\Middleware\ShareInfoMiddleware;
use OCA\Files_Sharing\Middleware\SharingCheckMiddleware;
use OCA\Files_Sharing\MountProvider;
use OCA\Files_Sharing\Notification\Listener;
use OCA\Files_Sharing\Notification\Notifier;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files\Event\LoadSidebar;
use OCP\AppFramework\App;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudIdManager;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Group\Events\UserAddedEvent;
use OCP\IContainer;
use OCP\IGroup;
use OCP\IServerContainer;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Util;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App {

	const APP_ID = 'files_sharing';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();

		/** @var IServerContainer $server */
		$server = $container->getServer();

		/** @var IEventDispatcher $dispatcher */
		$dispatcher = $container->query(IEventDispatcher::class);
		$mountProviderCollection = $server->getMountProviderCollection();
		$notifications = $server->getNotificationManager();

		/**
		 * Controllers
		 */
		$container->registerService('ShareController', function (SimpleContainer $c) use ($server) {
			$federatedSharingApp = new \OCA\FederatedFileSharing\AppInfo\Application();
			return new ShareController(
				$c->query('AppName'),
				$c->query('Request'),
				$server->getConfig(),
				$server->getURLGenerator(),
				$server->getUserManager(),
				$server->getLogger(),
				$server->getActivityManager(),
				$server->getShareManager(),
				$server->getSession(),
				$server->getPreviewManager(),
				$server->getRootFolder(),
				$federatedSharingApp->getFederatedShareProvider(),
				$server->getEventDispatcher(),
				$server->getL10N($c->query('AppName')),
				$server->query(Defaults::class)
			);
		});
		$container->registerService('ExternalSharesController', function (SimpleContainer $c) {
			return new ExternalSharesController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('ExternalManager'),
				$c->query('HttpClientService')
			);
		});

		/**
		 * Core class wrappers
		 */
		$container->registerService('HttpClientService', function (SimpleContainer $c) use ($server) {
			return $server->getHTTPClientService();
		});
		$container->registerService(ICloudIdManager::class, function (SimpleContainer $c) use ($server) {
			return $server->getCloudIdManager();
		});
		$container->registerService('ExternalManager', function (SimpleContainer $c) use ($server) {
			$user = $server->getUserSession()->getUser();
			$uid = $user ? $user->getUID() : null;
			return new \OCA\Files_Sharing\External\Manager(
				$server->getDatabaseConnection(),
				\OC\Files\Filesystem::getMountManager(),
				\OC\Files\Filesystem::getLoader(),
				$server->getHTTPClientService(),
				$server->getNotificationManager(),
				$server->query(\OCP\OCS\IDiscoveryService::class),
				$server->getCloudFederationProviderManager(),
				$server->getCloudFederationFactory(),
				$server->getGroupManager(),
				$server->getUserManager(),
				$uid
			);
		});
		$container->registerAlias(Manager::class, 'ExternalManager');

		/**
		 * Middleware
		 */
		$container->registerService('SharingCheckMiddleware', function (SimpleContainer $c) use ($server) {
			return new SharingCheckMiddleware(
				$c->query('AppName'),
				$server->getConfig(),
				$server->getAppManager(),
				$server->query(IControllerMethodReflector::class),
				$server->getShareManager(),
				$server->getRequest()
			);
		});

		$container->registerService(ShareInfoMiddleware::class, function () use ($server) {
			return new ShareInfoMiddleware(
				$server->getShareManager()
			);
		});

		// Execute middlewares
		$container->registerMiddleWare('SharingCheckMiddleware');
		$container->registerMiddleWare(OCSShareAPIMiddleware::class);
		$container->registerMiddleWare(ShareInfoMiddleware::class);

		$container->registerService('MountProvider', function (IContainer $c) {
			/** @var \OCP\IServerContainer $server */
			$server = $c->query('ServerContainer');
			return new MountProvider(
				$server->getConfig(),
				$server->getShareManager(),
				$server->getLogger()
			);
		});

		$container->registerService('ExternalMountProvider', function (IContainer $c) {
			/** @var \OCP\IServerContainer $server */
			$server = $c->query('ServerContainer');
			return new \OCA\Files_Sharing\External\MountProvider(
				$server->getDatabaseConnection(),
				function () use ($c) {
					return $c->query('ExternalManager');
				},
				$server->getCloudIdManager()
			);
		});

		/**
		 * Register capabilities
		 */
		$container->registerCapability(Capabilities::class);

		$notifications->registerNotifierService(Notifier::class);

		$this->registerMountProviders($mountProviderCollection);
		$this->registerEventsScripts($dispatcher);
		$this->setupSharingMenus();

		/**
		 * Always add main sharing script
		 */
		Util::addScript(self::APP_ID, 'dist/main');
	}

	protected function registerMountProviders(IMountProviderCollection $mountProviderCollection) {
		$mountProviderCollection->registerProvider($this->getContainer()->query('MountProvider'));
		$mountProviderCollection->registerProvider($this->getContainer()->query('ExternalMountProvider'));
	}

	protected function registerEventsScripts(IEventDispatcher $dispatcher) {
		// sidebar and files scripts
		$dispatcher->addServiceListener(LoadAdditionalScriptsEvent::class, LoadAdditionalListener::class);
		$dispatcher->addServiceListener(LoadSidebar::class, LoadSidebarListener::class);
		$dispatcher->addServiceListener(ShareCreatedEvent::class, ShareInteractionListener::class);
		$dispatcher->addListener('\OCP\Collaboration\Resources::loadAdditionalScripts', function () {
			\OCP\Util::addScript('files_sharing', 'dist/collaboration');
		});
		$dispatcher->addServiceListener(ShareCreatedEvent::class, UserShareAcceptanceListener::class);
		$dispatcher->addServiceListener(UserAddedEvent::class, UserAddedToGroupListener::class);

		// notifications api to accept incoming user shares
		$dispatcher->addListener('OCP\Share::postShare', function (GenericEvent $event) {
			/** @var Listener $listener */
			$listener = $this->getContainer()->query(Listener::class);
			$listener->shareNotification($event);
		});
		$dispatcher->addListener(IGroup::class . '::postAddUser', function (GenericEvent $event) {
			/** @var Listener $listener */
			$listener = $this->getContainer()->query(Listener::class);
			$listener->userAddedToGroup($event);
		});
	}

	protected function setupSharingMenus() {
		$config = \OC::$server->getConfig();
		$l = \OC::$server->getL10N('files_sharing');

		if ($config->getAppValue('core', 'shareapi_enabled', 'yes') !== 'yes') {
			return;
		}

		$sharingSublistArray = [];

		if (\OCP\Util::isSharingDisabledForUser() === false) {
			array_push($sharingSublistArray, [
				'id' => 'sharingout',
				'appname' => 'files_sharing',
				'script' => 'list.php',
				'order' => 16,
				'name' => $l->t('Shared with others'),
			]);
		}

		array_push($sharingSublistArray, [
			'id' => 'sharingin',
			'appname' => 'files_sharing',
			'script' => 'list.php',
			'order' => 15,
			'name' => $l->t('Shared with you'),
		]);

		if (\OCP\Util::isSharingDisabledForUser() === false) {
			// Check if sharing by link is enabled
			if ($config->getAppValue('core', 'shareapi_allow_links', 'yes') === 'yes') {
				array_push($sharingSublistArray, [
					'id' => 'sharinglinks',
					'appname' => 'files_sharing',
					'script' => 'list.php',
					'order' => 17,
					'name' => $l->t('Shared by link'),
				]);
			}
		}

		array_push($sharingSublistArray, [
			'id' => 'deletedshares',
			'appname' => 'files_sharing',
			'script' => 'list.php',
			'order' => 19,
			'name' => $l->t('Deleted shares'),
		]);

		array_push($sharingSublistArray, [
			'id' => 'pendingshares',
			'appname' => 'files_sharing',
			'script' => 'list.php',
			'order' => 19,
			'name' => $l->t('Pending shares'),
		]);


		// show_Quick_Access stored as string
		\OCA\Files\App::getNavigationManager()->add([
			'id' => 'shareoverview',
			'appname' => 'files_sharing',
			'script' => 'list.php',
			'order' => 18,
			'name' => $l->t('Shares'),
			'classes' => 'collapsible',
			'sublist' => $sharingSublistArray,
			'expandedState' => 'show_sharing_menu'
		]);
	}
}
