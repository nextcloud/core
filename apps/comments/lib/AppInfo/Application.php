<?php
/**
 * @copyright Copyright (c) 2016, Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\Comments\AppInfo;

use OCA\Comments\Controller\Notifications;
use OCA\Comments\EventHandler;
use OCA\Comments\JSSettingsHelper;
use OCA\Comments\Listener\LoadAdditionalScripts;
use OCA\Comments\Listener\LoadSidebarScripts;
use OCA\Comments\Notification\Notifier;
use OCA\Comments\Search\Provider;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files\Event\LoadSidebar;
use OCP\AppFramework\App;
use OCP\Comments\CommentsEntityEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Util;

class Application extends App {

	const APP_ID = 'comments';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
		$container = $this->getContainer();

		$container->registerAlias('NotificationsController', Notifications::class);

		$jsSettingsHelper = new JSSettingsHelper($container->getServer());
		Util::connectHook('\OCP\Config', 'js', $jsSettingsHelper, 'extend');

		$this->register();
	}

	private function register() {
		$server = $this->getContainer()->getServer();

		/** @var IEventDispatcher $newDispatcher */
		$dispatcher = $server->query(IEventDispatcher::class);

		$this->registerEventsScripts($dispatcher);
		$this->registerDavEntity($dispatcher);
		$this->registerNotifier();
		$this->registerCommentsEventHandler();

		$server->getSearch()->registerProvider(Provider::class, ['apps' => ['files']]);
	}

	protected function registerEventsScripts(IEventDispatcher $dispatcher) {
		$dispatcher->addServiceListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScripts::class);
		$dispatcher->addServiceListener(LoadSidebar::class, LoadSidebarScripts::class);
	}

	protected function registerDavEntity(IEventDispatcher $dispatcher) {
		$dispatcher->addListener(CommentsEntityEvent::EVENT_ENTITY, function (CommentsEntityEvent $event) {
			$event->addEntityCollection('files', function ($name) {
				$nodes = \OC::$server->getUserFolder()->getById((int)$name);
				return !empty($nodes);
			});
		});
	}

	protected function registerNotifier() {
		$this->getContainer()->getServer()->getNotificationManager()->registerNotifierService(Notifier::class);
	}

	protected function registerCommentsEventHandler() {
		$this->getContainer()->getServer()->getCommentsManager()->registerEventHandler(function () {
			return $this->getContainer()->query(EventHandler::class);
		});
	}
}
