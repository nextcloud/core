<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\TwoFactorBackupCodes\AppInfo;

use OCA\TwoFactorBackupCodes\Db\BackupCodeMapper;
use OCA\TwoFactorBackupCodes\Event\CodesGenerated;
use OCA\TwoFactorBackupCodes\Listener\ActivityPublisher;
use OCA\TwoFactorBackupCodes\Listener\ClearNotifications;
use OCA\TwoFactorBackupCodes\Listener\ProviderDisabled;
use OCA\TwoFactorBackupCodes\Listener\ProviderEnabled;
use OCA\TwoFactorBackupCodes\Listener\RegistryUpdater;
use OCA\TwoFactorBackupCodes\Notifications\Notifier;
use OCP\AppFramework\App;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Notification\IManager;
use OCP\Util;

class Application extends App {
	public function __construct() {
		parent::__construct('twofactor_backupcodes');
	}

	/**
	 * Register the different app parts
	 */
	public function register() {
		$this->registerHooksAndEvents();
		$this->registerNotification();
	}

	/**
	 * Register the hooks and events
	 */
	public function registerHooksAndEvents() {
		Util::connectHook('OC_User', 'post_deleteUser', $this, 'deleteUser');

		$container = $this->getContainer();

		/** @var IEventDispatcher $eventDispatcher */
		$eventDispatcher = $container->query(IEventDispatcher::class);
		$eventDispatcher->addServiceListener(CodesGenerated::class, ActivityPublisher::class);
		$eventDispatcher->addServiceListener(CodesGenerated::class, RegistryUpdater::class);
		$eventDispatcher->addServiceListener(CodesGenerated::class, ClearNotifications::class);
		$eventDispatcher->addServiceListener(IRegistry::EVENT_PROVIDER_ENABLED, ProviderEnabled::class);
		$eventDispatcher->addServiceListener(IRegistry::EVENT_PROVIDER_DISABLED, ProviderDisabled::class);
	}

	public function registerNotification() {
		$container = $this->getContainer();
		/** @var IManager $manager */
		$manager = $container->query(IManager::class);
		$manager->registerNotifierService(Notifier::class);
	}

	public function deleteUser($params) {
		/** @var BackupCodeMapper $mapper */
		$mapper = $this->getContainer()->query(BackupCodeMapper::class);
		$mapper->deleteCodesByUserId($params['uid']);
	}
}
