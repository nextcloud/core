<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\UpdateNotification\AppInfo;

use OCA\UpdateNotification\Notification\Notifier;
use OCA\UpdateNotification\UpdateChecker;
use OCP\AppFramework\App;
use OCP\AppFramework\QueryException;
use OCP\IUser;
use OCP\Util;

class Application extends App {
	public function __construct() {
		parent::__construct('updatenotification', []);
	}

	public function register() {
		$server = $this->getContainer()->getServer();

		if ($server->getConfig()->getSystemValue('updatechecker', true) !== true) {
			// Updater check is disabled
			return;
		}

		// Always register the notifier, so background jobs (without a user) can send push notifications
		$this->registerNotifier();

		$user = $server->getUserSession()->getUser();
		if (!$user instanceof IUser) {
			// Nothing to do for guests
			return;
		}

		if (!$server->getAppManager()->isEnabledForUser('notifications') &&
			$server->getGroupManager()->isAdmin($user->getUID())) {
			try {
				$updateChecker = $this->getContainer()->query(UpdateChecker::class);
			} catch (QueryException $e) {
				$server->getLogger()->logException($e);
				return;
			}

			if ($updateChecker->getUpdateState() !== []) {
				Util::addScript('updatenotification', 'legacy-notification');
				\OC_Hook::connect('\OCP\Config', 'js', $updateChecker, 'populateJavaScriptVariables');
			}
		}
	}

	public function registerNotifier() {
		$notificationsManager = $this->getContainer()->getServer()->getNotificationManager();
		$notificationsManager->registerNotifierService(Notifier::class);
	}
}
