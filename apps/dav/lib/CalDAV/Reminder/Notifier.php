<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Thomas Citharel
 * @copyright Copyright (c) 2019, Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\DAV\CalDAV\Reminder;

use DateTime;
use OCA\DAV\AppInfo\Application;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

/**
 * Class Notifier
 *
 * @package OCA\DAV\CalDAV\Reminder
 */
class Notifier implements INotifier {

	/** @var IFactory */
	private $l10nFactory;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IL10N */
	private $l10n;

	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * Notifier constructor.
	 *
	 * @param IFactory $factory
	 * @param IURLGenerator $urlGenerator
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(IFactory $factory,
								IURLGenerator $urlGenerator,
								ITimeFactory $timeFactory) {
		$this->l10nFactory = $factory;
		$this->urlGenerator = $urlGenerator;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID():string {
		return Application::APP_ID;
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName():string {
		return $this->l10nFactory->get('dav')->t('Calendar');
	}

	/**
	 * Prepare sending the notification
	 *
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \Exception
	 */
	public function prepare(INotification $notification,
							string $languageCode):INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new \InvalidArgumentException('Notification not from this app');
		}

		// Read the language from the notification
		$this->l10n = $this->l10nFactory->get('dav', $languageCode);

		// Handle notifier subjects
		switch ($notification->getSubject()) {
			case 'calendar_reminder':
				return $this->prepareReminderNotification($notification);

			default:
				throw new \InvalidArgumentException('Unknown subject');

		}
	}

	/**
	 * @param INotification $notification
	 * @return INotification
	 */
	private function prepareReminderNotification(INotification $notification):INotification {
		$imagePath = $this->urlGenerator->imagePath('core', 'places/calendar.svg');
		$iconUrl = $this->urlGenerator->getAbsoluteURL($imagePath);
		$notification->setIcon($iconUrl);

		$this->prepareNotificationSubject($notification);
		$this->prepareNotificationMessage($notification);

		return $notification;
	}

	/**
	 * Sets the notification subject based on the parameters set in PushProvider
	 *
	 * @param INotification $notification
	 */
	private function prepareNotificationSubject(INotification $notification): void {
		$parameters = $notification->getSubjectParameters();

		$startTime = \DateTime::createFromFormat(\DateTime::ATOM, $parameters['start_atom']);
		$now = $this->timeFactory->getDateTime();
		$title = $this->getTitleFromParameters($parameters);

		$diff = $startTime->diff($now);
		if ($diff === false) {
			return;
		}

		$components = [];
		if ($diff->y) {
			$components[] = $this->l10n->n('%n year', '%n years', $diff->y);
		}
		if ($diff->m) {
			$components[] = $this->l10n->n('%n month', '%n months', $diff->m);
		}
		if ($diff->d) {
			$components[] = $this->l10n->n('%n day', '%n days', $diff->d);
		}
		if ($diff->h) {
			$components[] = $this->l10n->n('%n hour', '%n hours', $diff->h);
		}
		if ($diff->i) {
			$components[] = $this->l10n->n('%n minute', '%n minutes', $diff->i);
		}

		// Limiting to the first three components to prevent
		// the string from getting too long
		$firstThreeComponents = array_slice($components, 0, 2);
		$diffLabel = implode(', ', $firstThreeComponents);

		if ($diff->invert) {
			$title = $this->l10n->t('%s (in %s)', [$title, $diffLabel]);
		} else {
			$title = $this->l10n->t('%s (%s ago)', [$title, $diffLabel]);
		}

		$notification->setParsedSubject($title);
	}

	/**
	 * Sets the notification message based on the parameters set in PushProvider
	 *
	 * @param INotification $notification
	 */
	private function prepareNotificationMessage(INotification $notification): void {
		$parameters = $notification->getMessageParameters();

		$description = [
			$this->l10n->t('Calendar: %s', $parameters['calendar_displayname']),
			$this->l10n->t('Date: %s', $this->generateDateString($parameters)),
		];
		if ($parameters['description']) {
			$description[] = $this->l10n->t('Description: %s', $parameters['description']);
		}
		if ($parameters['location']) {
			$description[] = $this->l10n->t('Where: %s', $parameters['location']);
		}

		$message = implode("\r\n", $description);
		$notification->setParsedMessage($message);
	}

	/**
	 * @param array $parameters
	 * @return string
	 */
	private function getTitleFromParameters(array $parameters):string {
		return $parameters['title'] ?? $this->l10n->t('Untitled event');
	}

	/**
	 * @param array $parameters
	 * @return string
	 * @throws \Exception
	 */
	private function generateDateString(array $parameters):string {
		$startDateTime = DateTime::createFromFormat(\DateTime::ATOM, $parameters['start_atom']);
		$endDateTime = DateTime::createFromFormat(\DateTime::ATOM, $parameters['end_atom']);

		// If the event has already ended, dismiss the notification
		if ($endDateTime < $this->timeFactory->getDateTime()) {
			throw new AlreadyProcessedException();
		}

		$isAllDay = $parameters['all_day'];
		$diff = $startDateTime->diff($endDateTime);

		if ($isAllDay) {
			// One day event
			if ($diff->days === 1) {
				return $this->getDateString($startDateTime);
			}

			return implode(' - ', [
				$this->getDateString($startDateTime),
				$this->getDateString($endDateTime),
			]);
		}

		$startTimezone = $endTimezone = null;
		if (!$parameters['start_is_floating']) {
			$startTimezone = $parameters['start_timezone'];
			$endTimezone = $parameters['end_timezone'];
		}

		$localeStart = implode(', ', [
			$this->getWeekDayName($startDateTime),
			$this->getDateTimeString($startDateTime)
		]);

		// always show full date with timezone if timezones are different
		if ($startTimezone !== $endTimezone) {
			$localeEnd = implode(', ', [
				$this->getWeekDayName($endDateTime),
				$this->getDateTimeString($endDateTime)
			]);

			return $localeStart
				. ' (' . $startTimezone . ') '
				. ' - '
				. $localeEnd
				. ' (' . $endTimezone . ')';
		}

		// Show only the time if the day is the same
		$localeEnd = $this->isDayEqual($startDateTime, $endDateTime)
			? $this->getTimeString($endDateTime)
			: implode(', ', [
				$this->getWeekDayName($endDateTime),
				$this->getDateTimeString($endDateTime)
			]);

		return $localeStart
			. ' - '
			. $localeEnd
			. ' (' . $startTimezone . ')';
	}

	/**
	 * @param DateTime $dtStart
	 * @param DateTime $dtEnd
	 * @return bool
	 */
	private function isDayEqual(DateTime $dtStart,
								DateTime $dtEnd):bool {
		return $dtStart->format('Y-m-d') === $dtEnd->format('Y-m-d');
	}

	/**
	 * @param DateTime $dt
	 * @return string
	 */
	private function getWeekDayName(DateTime $dt):string {
		return $this->l10n->l('weekdayName', $dt, ['width' => 'abbreviated']);
	}

	/**
	 * @param DateTime $dt
	 * @return string
	 */
	private function getDateString(DateTime $dt):string {
		return $this->l10n->l('date', $dt, ['width' => 'medium']);
	}

	/**
	 * @param DateTime $dt
	 * @return string
	 */
	private function getDateTimeString(DateTime $dt):string {
		return $this->l10n->l('datetime', $dt, ['width' => 'medium|short']);
	}

	/**
	 * @param DateTime $dt
	 * @return string
	 */
	private function getTimeString(DateTime $dt):string {
		return $this->l10n->l('time', $dt, ['width' => 'short']);
	}
}
