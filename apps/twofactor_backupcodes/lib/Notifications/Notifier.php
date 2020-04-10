<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
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

namespace OCA\TwoFactorBackupCodes\Notifications;

use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {

	/** @var IFactory */
	private $factory;

	/** @var IURLGenerator */
	private $url;

	public function __construct(IFactory $factory, IURLGenerator $url) {
		$this->factory = $factory;
		$this->url = $url;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'twofactor_backupcodes';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->factory->get('twofactor_backupcodes')->t('Second-factor backup codes');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'twofactor_backupcodes') {
			// Not my app => throw
			throw new \InvalidArgumentException();
		}

		// Read the language from the notification
		$l = $this->factory->get('twofactor_backupcodes', $languageCode);

		switch ($notification->getSubject()) {
			case 'create_backupcodes':
				$notification->setParsedSubject(
					$l->t('Generate backup codes')
				)->setParsedMessage(
					$l->t('You enabled two-factor authentication but did not generate backup codes yet. They are needed to restore access to your account in case you lose your second factor.')
				);

				$notification->setLink($this->url->linkToRouteAbsolute('settings.PersonalSettings.index', ['section' => 'security']));

				$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/password.svg')));

				return $notification;

			default:
				// Unknown subject => Unknown notification => throw
				throw new \InvalidArgumentException();
		}
	}
}
