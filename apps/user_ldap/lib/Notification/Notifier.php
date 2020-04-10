<?php
/**
 * @copyright Copyright (c) 2017 Roger Szabo <roger.szabo@web.de>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\User_LDAP\Notification;

use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {

	/** @var IFactory */
	protected $l10nFactory;

	/**
	 * @param IFactory $l10nFactory
	 */
	 public function __construct(\OCP\L10N\IFactory $l10nFactory) {
		$this->l10nFactory = $l10nFactory;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'user_ldap';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->l10nFactory->get('user_ldap')->t('LDAP User backend');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'user_ldap') {
			// Not my app => throw
			throw new \InvalidArgumentException();
		}

		// Read the language from the notification
		$l = $this->l10nFactory->get('user_ldap', $languageCode);

		switch ($notification->getSubject()) {
			// Deal with known subjects
			case 'pwd_exp_warn_days':
				$params = $notification->getSubjectParameters();
				$days = (int) $params[0];
				if ($days === 2) {
					$notification->setParsedSubject($l->t('Your password will expire tomorrow.'));
				} else if ($days === 1) {
					$notification->setParsedSubject($l->t('Your password will expire today.'));
				} else {
					$notification->setParsedSubject($l->n(
						'Your password will expire within %n day.',
						'Your password will expire within %n days.',
						$days
					));
				}
				return $notification;

			default:
				// Unknown subject => Unknown notification => throw
				throw new \InvalidArgumentException();
		}
	}
}
