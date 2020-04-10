<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OC\Authentication\Notifications;

use InvalidArgumentException;
use OCP\L10N\IFactory as IL10nFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {

	/** @var IL10nFactory */
	private $factory;

	public function __construct(IL10nFactory $l10nFactory) {
		$this->factory = $l10nFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'auth') {
			// Not my app => throw
			throw new InvalidArgumentException();
		}

		// Read the language from the notification
		$l = $this->factory->get('lib', $languageCode);

		switch ($notification->getSubject()) {
			case 'remote_wipe_start':
				$notification->setParsedSubject(
					$l->t('Remote wipe started')
				)->setParsedMessage(
					$l->t('A remote wipe was started on device %s', $notification->getSubjectParameters())
				);

				return $notification;
			case 'remote_wipe_finish':
				$notification->setParsedSubject(
					$l->t('Remote wipe finished')
				)->setParsedMessage(
					$l->t('The remote wipe on %s has finished', $notification->getSubjectParameters())
				);

				return $notification;
			default:
				// Unknown subject => Unknown notification => throw
				throw new InvalidArgumentException();
		}
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'auth';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->factory->get('lib')->t('Authentication');
	}
}
