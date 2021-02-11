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

namespace OCA\DAV\CalDAV\Reminder\NotificationProvider;

use OCA\DAV\AppInfo\Application;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\L10N\IFactory as L10NFactory;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property;

/**
 * Class PushProvider
 *
 * @package OCA\DAV\CalDAV\Reminder\NotificationProvider
 */
class PushProvider extends AbstractProvider {

	/** @var string */
	public const NOTIFICATION_TYPE = 'DISPLAY';

	/** @var IManager */
	private $manager;

	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * @param IConfig $config
	 * @param IManager $manager
	 * @param ILogger $logger
	 * @param L10NFactory $l10nFactory
	 * @param IUrlGenerator $urlGenerator
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(IConfig $config,
								IManager $manager,
								ILogger $logger,
								L10NFactory $l10nFactory,
								IURLGenerator $urlGenerator,
								ITimeFactory $timeFactory) {
		parent::__construct($logger, $l10nFactory, $urlGenerator, $config);
		$this->manager = $manager;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * Send push notification to all users.
	 *
	 * @param VEvent $vevent
	 * @param string $calendarDisplayName
	 * @param IUser[] $users
	 * @throws \Exception
	 */
	public function send(VEvent $vevent,
						 string $calendarDisplayName = null,
						 array $users = []):void {
		if ($this->config->getAppValue('dav', 'sendEventRemindersPush', 'no') !== 'yes') {
			return;
		}

		$eventDetails = $this->extractEventDetails($vevent);
		$eventDetails['calendar_displayname'] = $calendarDisplayName;
		$eventUUID = (string) $vevent->UID;
		if (!$eventUUID) {
			return;
		};
		$eventUUIDHash = hash('sha256', $eventUUID, false);

		foreach ($users as $user) {
			/** @var INotification $notification */
			$notification = $this->manager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($user->getUID())
				->setDateTime($this->timeFactory->getDateTime())
				->setObject(Application::APP_ID, $eventUUIDHash)
				->setSubject('calendar_reminder', [
					'title' => $eventDetails['title'],
					'start_atom' => $eventDetails['start_atom']
				])
				->setMessage('calendar_reminder', $eventDetails);

			$this->manager->notify($notification);
		}
	}

	/**
	 * @var VEvent $vevent
	 * @return array
	 * @throws \Exception
	 */
	protected function extractEventDetails(VEvent $vevent):array {
		/** @var Property\ICalendar\DateTime $start */
		$start = $vevent->DTSTART;
		$end = $this->getDTEndFromEvent($vevent);

		return [
			'title' => isset($vevent->SUMMARY)
				? ((string) $vevent->SUMMARY)
				: null,
			'description' => isset($vevent->DESCRIPTION)
				? ((string) $vevent->DESCRIPTION)
				: null,
			'location' => isset($vevent->LOCATION)
				? ((string) $vevent->LOCATION)
				: null,
			'all_day' => $start instanceof Property\ICalendar\Date,
			'start_atom' => $start->getDateTime()->format(\DateTime::ATOM),
			'start_is_floating' => $start->isFloating(),
			'start_timezone' => $start->getDateTime()->getTimezone()->getName(),
			'end_atom' => $end->getDateTime()->format(\DateTime::ATOM),
			'end_is_floating' => $end->isFloating(),
			'end_timezone' => $end->getDateTime()->getTimezone()->getName(),
		];
	}
}
