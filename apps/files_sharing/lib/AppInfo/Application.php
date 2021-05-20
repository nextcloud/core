<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
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
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Files_Sharing\External\Manager;
use OCA\Files_Sharing\Listener\LegacyBeforeTemplateRenderedListener;
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
use OCA\Files_Sharing\ShareBackend\File;
use OCA\Files_Sharing\ShareBackend\Folder;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudIdManager;
use OCP\Group\Events\UserAddedEvent;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Util;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'files_sharing';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		/**
		 * Middleware
		 */
		$context->registerMiddleWare(SharingCheckMiddleware::class);
		$context->registerMiddleWare(OCSShareAPIMiddleware::class);
		$context->registerMiddleWare(ShareInfoMiddleware::class);

		/**
		 * Capabilities
		 */
		$context->registerCapability(Capabilities::class);

		/**
		 * Events
		 */
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, LegacyBeforeTemplateRenderedListener::class);
		$context->registerEventListener(LoadSidebar::class, LoadSidebarListener::class);
		$context->registerEventListener(ShareCreatedEvent::class, ShareInteractionListener::class);
		$context->registerEventListener(ShareCreatedEvent::class, UserShareAcceptanceListener::class);
		$context->registerEventListener(UserAddedEvent::class, UserAddedToGroupListener::class);
	}

	public function boot(IBootContext $context): void {
		\OCA\Files_Sharing\Helper::registerHooks();

		\OC\Share\Share::registerBackend('file', File::class);
		\OC\Share\Share::registerBackend('folder', Folder::class, 'file');

		$container = $context->getAppContainer();
		$server = $context->getServerContainer();

		/** @var IEventDispatcher $dispatcher */
		$dispatcher = $container->query(IEventDispatcher::class);
		$oldDispatcher = $container->getServer()->getEventDispatcher();
		$mountProviderCollection = $server->getMountProviderCollection();
		$notifications = $server->getNotificationManager();

		/**
		 * Core class wrappers
		 */
		$container->registerService(Manager::class, function (SimpleContainer $c) use ($server) {
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
				$uid,
				$server->query(IEventDispatcher::class)
			);
		});
		$container->registerService('ExternalMountProvider', function (ContainerInterface $c) {
			return new \OCA\Files_Sharing\External\MountProvider(
				$c->get(IDBConnection::class),
				function () use ($c) {
					return $c->get(Manager::class);
				},
				$c->get(ICloudIdManager::class)
			);
		});

		$notifications->registerNotifierService(Notifier::class);

		/**
		 * Mount provider
		 */
		$mountProviderCollection->registerProvider($container->query(MountProvider::class));
		$mountProviderCollection->registerProvider($container->query('ExternalMountProvider'));

		$this->registerEventsScripts($dispatcher, $oldDispatcher);
		$this->setupSharingMenus();

		/**
		 * Always add main sharing script
		 */
		Util::addScript(self::APP_ID, 'dist/main');
	}

	protected function registerEventsScripts(IEventDispatcher $dispatcher, EventDispatcherInterface $oldDispatcher) {
		// sidebar and files scripts
		$dispatcher->addListener('\OCP\Collaboration\Resources::loadAdditionalScripts', function () {
			\OCP\Util::addScript('files_sharing', 'dist/collaboration');
		});

		// notifications api to accept incoming user shares
		$oldDispatcher->addListener('OCP\Share::postShare', function (GenericEvent $event) {
			/** @var Listener $listener */
			$listener = $this->getContainer()->query(Listener::class);
			$listener->shareNotification($event);
		});
		$oldDispatcher->addListener(IGroup::class . '::postAddUser', function (GenericEvent $event) {
			/** @var Listener $listener */
			$listener = $this->getContainer()->query(Listener::class);
			$listener->userAddedToGroup($event);
		});
	}

	protected function setupSharingMenus() {
		$config = \OC::$server->getConfig();

		if ($config->getAppValue('core', 'shareapi_enabled', 'yes') !== 'yes' || !class_exists('\OCA\Files\App')) {
			return;
		}

		// show_Quick_Access stored as string
		\OCA\Files\App::getNavigationManager()->add(function () {
			$config = \OC::$server->getConfig();
			$l = \OC::$server->getL10N('files_sharing');

			$sharingSublistArray = [];

			if (\OCP\Util::isSharingDisabledForUser() === false) {
				$sharingSublistArray[] = [
					'id' => 'sharingout',
					'appname' => 'files_sharing',
					'script' => 'list.php',
					'order' => 16,
					'name' => $l->t('Shared with others'),
				];
			}

			$sharingSublistArray[] = [
				'id' => 'sharingin',
				'appname' => 'files_sharing',
				'script' => 'list.php',
				'order' => 15,
				'name' => $l->t('Shared with you'),
			];

			if (\OCP\Util::isSharingDisabledForUser() === false) {
				// Check if sharing by link is enabled
				if ($config->getAppValue('core', 'shareapi_allow_links', 'yes') === 'yes') {
					$sharingSublistArray[] = [
						'id' => 'sharinglinks',
						'appname' => 'files_sharing',
						'script' => 'list.php',
						'order' => 17,
						'name' => $l->t('Shared by link'),
					];
				}
			}

			$sharingSublistArray[] = [
				'id' => 'deletedshares',
				'appname' => 'files_sharing',
				'script' => 'list.php',
				'order' => 19,
				'name' => $l->t('Deleted shares'),
			];

			$sharingSublistArray[] = [
				'id' => 'pendingshares',
				'appname' => 'files_sharing',
				'script' => 'list.php',
				'order' => 19,
				'name' => $l->t('Pending shares'),
			];

			return [
				'id' => 'shareoverview',
				'appname' => 'files_sharing',
				'script' => 'list.php',
				'order' => 18,
				'name' => $l->t('Shares'),
				'classes' => 'collapsible',
				'sublist' => $sharingSublistArray,
				'expandedState' => 'show_sharing_menu'
			];
		});
	}
}
