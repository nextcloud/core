<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OC\Core\Notification;

use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class RemoveLinkSharesNotifier implements INotifier {
	/** @var IFactory */
	private $l10nFactory;

	public function __construct(IFactory $factory) {
		$this->l10nFactory = $factory;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'core';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->l10nFactory->get('core')->t('Nextcloud Server');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if($notification->getApp() !== 'core') {
			throw new \InvalidArgumentException();
		}
		$l = $this->l10nFactory->get('core', $languageCode);

		if ($notification->getSubject() === 'repair_exposing_links') {
			$notification->setParsedSubject($l->t('Some of your link shares have been removed'));
			$notification->setParsedMessage($l->t('Due to a security bug we had to remove some of your link shares. Please see the link for more information.'));
			$notification->setLink('https://nextcloud.com/security/advisory/?id=NC-SA-2019-003');
			return $notification;
		}

		throw new \InvalidArgumentException('Invalid subject');
	}
}
