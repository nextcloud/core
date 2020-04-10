<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\UpdateNotification\Notification;

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Util;

class Notifier implements INotifier {

	/** @var IURLGenerator */
	protected $url;

	/** @var IConfig */
	protected $config;

	/** @var IManager */
	protected $notificationManager;

	/** @var IFactory */
	protected $l10NFactory;

	/** @var IUserSession */
	protected $userSession;

	/** @var IGroupManager */
	protected $groupManager;

	/** @var string[] */
	protected $appVersions;

	/**
	 * Notifier constructor.
	 *
	 * @param IURLGenerator $url
	 * @param IConfig $config
	 * @param IManager $notificationManager
	 * @param IFactory $l10NFactory
	 * @param IUserSession $userSession
	 * @param IGroupManager $groupManager
	 */
	public function __construct(IURLGenerator $url, IConfig $config, IManager $notificationManager, IFactory $l10NFactory, IUserSession $userSession, IGroupManager $groupManager) {
		$this->url = $url;
		$this->notificationManager = $notificationManager;
		$this->config = $config;
		$this->l10NFactory = $l10NFactory;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->appVersions = $this->getAppVersions();
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'updatenotification';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->l10NFactory->get('updatenotification')->t('Update notifications');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @throws AlreadyProcessedException When the notification is not needed anymore and should be deleted
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'updatenotification') {
			throw new \InvalidArgumentException('Unknown app id');
		}

		$l = $this->l10NFactory->get('updatenotification', $languageCode);
		if ($notification->getSubject() === 'connection_error') {
			$errors = (int) $this->config->getAppValue('updatenotification', 'update_check_errors', 0);
			if ($errors === 0) {
				$this->notificationManager->markProcessed($notification);
				throw new \InvalidArgumentException('Update checked worked again');
			}

			$notification->setParsedSubject($l->t('The update server could not be reached since %d days to check for new updates.', [$errors]))
				->setParsedMessage($l->t('Please check the Nextcloud and server log files for errors.'));
		} elseif ($notification->getObjectType() === 'core') {
			$this->updateAlreadyInstalledCheck($notification, $this->getCoreVersions());

			$parameters = $notification->getSubjectParameters();
			$notification->setParsedSubject($l->t('Update to %1$s is available.', [$parameters['version']]));

			if ($this->isAdmin()) {
				$notification->setLink($this->url->linkToRouteAbsolute('settings.AdminSettings.index', ['section' => 'overview']) . '#version');
			}
		} else {
			$appInfo = $this->getAppInfo($notification->getObjectType());
			$appName = ($appInfo === null) ? $notification->getObjectType() : $appInfo['name'];

			if (isset($this->appVersions[$notification->getObjectType()])) {
				$this->updateAlreadyInstalledCheck($notification, $this->appVersions[$notification->getObjectType()]);
			}

			$notification->setParsedSubject($l->t('Update for %1$s to version %2$s is available.', [$appName, $notification->getObjectId()]))
				->setRichSubject($l->t('Update for {app} to version %s is available.', [$notification->getObjectId()]), [
					'app' => [
						'type' => 'app',
						'id' => $notification->getObjectType(),
						'name' => $appName,
					]
				]);

			if ($this->isAdmin()) {
				$notification->setLink($this->url->linkToRouteAbsolute('settings.AppSettings.viewApps', ['category' => 'updates']) . '#app-' . $notification->getObjectType());
			}
		}

		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('updatenotification', 'notification.svg')));

		return $notification;
	}

	/**
	 * Remove the notification and prevent rendering, when the update is installed
	 *
	 * @param INotification $notification
	 * @param string $installedVersion
	 * @throws AlreadyProcessedException When the update is already installed
	 */
	protected function updateAlreadyInstalledCheck(INotification $notification, $installedVersion) {
		if (version_compare($notification->getObjectId(), $installedVersion, '<=')) {
			throw new AlreadyProcessedException();
		}
	}

	/**
	 * @return bool
	 */
	protected function isAdmin(): bool {
		$user = $this->userSession->getUser();

		if ($user instanceof IUser) {
			return $this->groupManager->isAdmin($user->getUID());
		}

		return false;
	}

	protected function getCoreVersions(): string {
		return implode('.', Util::getVersion());
	}

	protected function getAppVersions(): array {
		return \OC_App::getAppVersions();
	}

	protected function getAppInfo($appId) {
		return \OC_App::getAppInfo($appId);
	}
}
