<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Thomas Citharel
 * @copyright Copyright (c) 2019, Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\DAV\CalDAV\Reminder;

use DateTimeImmutable;
use OCA\DAV\CalDAV\CalDavBackend;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Sabre\VObject;
use Sabre\VObject\Component\VAlarm;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\ParseException;
use Sabre\VObject\Recur\EventIterator;
use Sabre\VObject\Recur\NoInstancesException;
use function strcasecmp;

class ReminderService {

	/** @var Backend */
	private $backend;

	/** @var NotificationProviderManager */
	private $notificationProviderManager;

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var CalDavBackend */
	private $caldavBackend;

	/** @var ITimeFactory */
	private $timeFactory;

	public const REMINDER_TYPE_EMAIL = 'EMAIL';
	public const REMINDER_TYPE_DISPLAY = 'DISPLAY';
	public const REMINDER_TYPE_AUDIO = 'AUDIO';

	/**
	 * @var String[]
	 *
	 * Official RFC5545 reminder types
	 */
	public const REMINDER_TYPES = [
		self::REMINDER_TYPE_EMAIL,
		self::REMINDER_TYPE_DISPLAY,
		self::REMINDER_TYPE_AUDIO
	];

	/**
	 * ReminderService constructor.
	 *
	 * @param Backend $backend
	 * @param NotificationProviderManager $notificationProviderManager
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param CalDavBackend $caldavBackend
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(Backend $backend,
								NotificationProviderManager $notificationProviderManager,
								IUserManager $userManager,
								IGroupManager $groupManager,
								CalDavBackend $caldavBackend,
								ITimeFactory $timeFactory) {
		$this->backend = $backend;
		$this->notificationProviderManager = $notificationProviderManager;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->caldavBackend = $caldavBackend;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * Process reminders to activate
	 *
	 * @throws NotificationProvider\ProviderNotAvailableException
	 * @throws NotificationTypeDoesNotExistException
	 */
	public function processReminders():void {
		$reminders = $this->backend->getRemindersToProcess();

		foreach ($reminders as $reminder) {
			$calendarData = is_resource($reminder['calendardata'])
				? stream_get_contents($reminder['calendardata'])
				: $reminder['calendardata'];

			if (!$calendarData) {
				continue;
			}

			$vcalendar = $this->parseCalendarData($calendarData);
			if (!$vcalendar) {
				$this->backend->removeReminder($reminder['id']);
				continue;
			}

			$vevent = $this->getVEventByRecurrenceId($vcalendar, $reminder['recurrence_id'], $reminder['is_recurrence_exception']);
			if (!$vevent) {
				$this->backend->removeReminder($reminder['id']);
				continue;
			}

			if ($this->wasEventCancelled($vevent)) {
				$this->deleteOrProcessNext($reminder, $vevent);
				continue;
			}

			if (!$this->notificationProviderManager->hasProvider($reminder['type'])) {
				$this->deleteOrProcessNext($reminder, $vevent);
				continue;
			}

			$users = $this->getAllUsersWithWriteAccessToCalendar($reminder['calendar_id']);
			$user = $this->getUserFromPrincipalURI($reminder['principaluri']);
			if ($user) {
				$users[] = $user;
			}

			$notificationProvider = $this->notificationProviderManager->getProvider($reminder['type']);
			$notificationProvider->send($vevent, $reminder['displayname'], $users);

			$this->deleteOrProcessNext($reminder, $vevent);
		}
	}

	/**
	 * @param array $objectData
	 * @throws VObject\InvalidDataException
	 */
	public function onCalendarObjectCreate(array $objectData):void {
		// We only support VEvents for now
		if (strcasecmp($objectData['component'], 'vevent') !== 0) {
			return;
		}

		$calendarData = is_resource($objectData['calendardata'])
			? stream_get_contents($objectData['calendardata'])
			: $objectData['calendardata'];

		if (!$calendarData) {
			return;
		}

		/** @var VObject\Component\VCalendar $vcalendar */
		$vcalendar = $this->parseCalendarData($calendarData);
		if (!$vcalendar) {
			return;
		}

		$vevents = $this->getAllVEventsFromVCalendar($vcalendar);
		if (count($vevents) === 0) {
			return;
		}

		$uid = (string) $vevents[0]->UID;
		$recurrenceExceptions = $this->getRecurrenceExceptionFromListOfVEvents($vevents);
		$masterItem = $this->getMasterItemFromListOfVEvents($vevents);
		$now = $this->timeFactory->getDateTime();
		$isRecurring = $masterItem ? $this->isRecurring($masterItem) : false;

		foreach ($recurrenceExceptions as $recurrenceException) {
			$eventHash = $this->getEventHash($recurrenceException);

			if (!isset($recurrenceException->VALARM)) {
				continue;
			}

			foreach ($recurrenceException->VALARM as $valarm) {
				/** @var VAlarm $valarm */
				$alarmHash = $this->getAlarmHash($valarm);
				$triggerTime = $valarm->getEffectiveTriggerTime();
				$diff = $now->diff($triggerTime);
				if ($diff->invert === 1) {
					continue;
				}

				$alarms = $this->getRemindersForVAlarm($valarm, $objectData,
					$eventHash, $alarmHash, true, true);
				$this->writeRemindersToDatabase($alarms);
			}
		}

		if ($masterItem) {
			$processedAlarms = [];
			$masterAlarms = [];
			$masterHash = $this->getEventHash($masterItem);

			if (!isset($masterItem->VALARM)) {
				return;
			}

			foreach ($masterItem->VALARM as $valarm) {
				$masterAlarms[] = $this->getAlarmHash($valarm);
			}

			try {
				$iterator = new EventIterator($vevents, $uid);
			} catch (NoInstancesException $e) {
				// This event is recurring, but it doesn't have a single
				// instance. We are skipping this event from the output
				// entirely.
				return;
			}

			while ($iterator->valid() && count($processedAlarms) < count($masterAlarms)) {
				$event = $iterator->getEventObject();

				// Recurrence-exceptions are handled separately, so just ignore them here
				if (\in_array($event, $recurrenceExceptions, true)) {
					$iterator->next();
					continue;
				}

				foreach ($event->VALARM as $valarm) {
					/** @var VAlarm $valarm */
					$alarmHash = $this->getAlarmHash($valarm);
					if (\in_array($alarmHash, $processedAlarms, true)) {
						continue;
					}

					if (!\in_array((string) $valarm->ACTION, self::REMINDER_TYPES, true)) {
						// Action allows x-name, we don't insert reminders
						// into the database if they are not standard
						$processedAlarms[] = $alarmHash;
						continue;
					}

					try {
						$triggerTime = $valarm->getEffectiveTriggerTime();
					} catch (InvalidDataException $e) {
						continue;
					}

					// If effective trigger time is in the past
					// just skip and generate for next event
					$diff = $now->diff($triggerTime);
					if ($diff->invert === 1) {
						// If an absolute alarm is in the past,
						// just add it to processedAlarms, so
						// we don't extend till eternity
						if (!$this->isAlarmRelative($valarm)) {
							$processedAlarms[] = $alarmHash;
						}

						continue;
					}

					$alarms = $this->getRemindersForVAlarm($valarm, $objectData, $masterHash, $alarmHash, $isRecurring, false);
					$this->writeRemindersToDatabase($alarms);
					$processedAlarms[] = $alarmHash;
				}

				$iterator->next();
			}
		}
	}

	/**
	 * @param array $objectData
	 * @throws VObject\InvalidDataException
	 */
	public function onCalendarObjectEdit(array $objectData):void {
		// TODO - this can be vastly improved
		//  - get cached reminders
		//  - ...

		$this->onCalendarObjectDelete($objectData);
		$this->onCalendarObjectCreate($objectData);
	}

	/**
	 * @param array $objectData
	 * @throws VObject\InvalidDataException
	 */
	public function onCalendarObjectDelete(array $objectData):void {
		// We only support VEvents for now
		if (strcasecmp($objectData['component'], 'vevent') !== 0) {
			return;
		}

		$this->backend->cleanRemindersForEvent((int) $objectData['id']);
	}

	/**
	 * @param VAlarm $valarm
	 * @param array $objectData
	 * @param string|null $eventHash
	 * @param string|null $alarmHash
	 * @param bool $isRecurring
	 * @param bool $isRecurrenceException
	 * @return array
	 */
	private function getRemindersForVAlarm(VAlarm $valarm,
										   array $objectData,
										   string $eventHash = null,
										   string $alarmHash = null,
										   bool $isRecurring = false,
										   bool $isRecurrenceException = false):array {
		if ($eventHash === null) {
			$eventHash = $this->getEventHash($valarm->parent);
		}
		if ($alarmHash === null) {
			$alarmHash = $this->getAlarmHash($valarm);
		}

		$recurrenceId = $this->getEffectiveRecurrenceIdOfVEvent($valarm->parent);
		$isRelative = $this->isAlarmRelative($valarm);
		/** @var DateTimeImmutable $notificationDate */
		$notificationDate = $valarm->getEffectiveTriggerTime();
		$clonedNotificationDate = new \DateTime('now', $notificationDate->getTimezone());
		$clonedNotificationDate->setTimestamp($notificationDate->getTimestamp());

		$alarms = [];

		$alarms[] = [
			'calendar_id' => $objectData['calendarid'],
			'object_id' => $objectData['id'],
			'uid' => (string) $valarm->parent->UID,
			'is_recurring' => $isRecurring,
			'recurrence_id' => $recurrenceId,
			'is_recurrence_exception' => $isRecurrenceException,
			'event_hash' => $eventHash,
			'alarm_hash' => $alarmHash,
			'type' => (string) $valarm->ACTION,
			'is_relative' => $isRelative,
			'notification_date' => $notificationDate->getTimestamp(),
			'is_repeat_based' => false,
		];

		$repeat = isset($valarm->REPEAT) ? (int) $valarm->REPEAT->getValue() : 0;
		for ($i = 0; $i < $repeat; $i++) {
			if ($valarm->DURATION === null) {
				continue;
			}

			$clonedNotificationDate->add($valarm->DURATION->getDateInterval());
			$alarms[] = [
				'calendar_id' => $objectData['calendarid'],
				'object_id' => $objectData['id'],
				'uid' => (string) $valarm->parent->UID,
				'is_recurring' => $isRecurring,
				'recurrence_id' => $recurrenceId,
				'is_recurrence_exception' => $isRecurrenceException,
				'event_hash' => $eventHash,
				'alarm_hash' => $alarmHash,
				'type' => (string) $valarm->ACTION,
				'is_relative' => $isRelative,
				'notification_date' => $clonedNotificationDate->getTimestamp(),
				'is_repeat_based' => true,
			];
		}

		return $alarms;
	}

	/**
	 * @param array $reminders
	 */
	private function writeRemindersToDatabase(array $reminders): void {
		foreach ($reminders as $reminder) {
			$this->backend->insertReminder(
				(int) $reminder['calendar_id'],
				(int) $reminder['object_id'],
				$reminder['uid'],
				$reminder['is_recurring'],
				(int) $reminder['recurrence_id'],
				$reminder['is_recurrence_exception'],
				$reminder['event_hash'],
				$reminder['alarm_hash'],
				$reminder['type'],
				$reminder['is_relative'],
				(int) $reminder['notification_date'],
				$reminder['is_repeat_based']
			);
		}
	}

	/**
	 * @param array $reminder
	 * @param VEvent $vevent
	 */
	private function deleteOrProcessNext(array $reminder,
										 VObject\Component\VEvent $vevent):void {
		if ($reminder['is_repeat_based'] ||
			!$reminder['is_recurring'] ||
			!$reminder['is_relative'] ||
			$reminder['is_recurrence_exception']) {
			$this->backend->removeReminder($reminder['id']);
			return;
		}

		$vevents = $this->getAllVEventsFromVCalendar($vevent->parent);
		$recurrenceExceptions = $this->getRecurrenceExceptionFromListOfVEvents($vevents);
		$now = $this->timeFactory->getDateTime();

		try {
			$iterator = new EventIterator($vevents, $reminder['uid']);
		} catch (NoInstancesException $e) {
			// This event is recurring, but it doesn't have a single
			// instance. We are skipping this event from the output
			// entirely.
			return;
		}

		while ($iterator->valid()) {
			$event = $iterator->getEventObject();

			// Recurrence-exceptions are handled separately, so just ignore them here
			if (\in_array($event, $recurrenceExceptions, true)) {
				$iterator->next();
				continue;
			}

			$recurrenceId = $this->getEffectiveRecurrenceIdOfVEvent($event);
			if ($reminder['recurrence_id'] >= $recurrenceId) {
				$iterator->next();
				continue;
			}

			foreach ($event->VALARM as $valarm) {
				/** @var VAlarm $valarm */
				$alarmHash = $this->getAlarmHash($valarm);
				if ($alarmHash !== $reminder['alarm_hash']) {
					continue;
				}

				$triggerTime = $valarm->getEffectiveTriggerTime();

				// If effective trigger time is in the past
				// just skip and generate for next event
				$diff = $now->diff($triggerTime);
				if ($diff->invert === 1) {
					continue;
				}

				$this->backend->removeReminder($reminder['id']);
				$alarms = $this->getRemindersForVAlarm($valarm, [
					'calendarid' => $reminder['calendar_id'],
					'id' => $reminder['object_id'],
				], $reminder['event_hash'], $alarmHash, true, false);
				$this->writeRemindersToDatabase($alarms);

				// Abort generating reminders after creating one successfully
				return;
			}

			$iterator->next();
		}

		$this->backend->removeReminder($reminder['id']);
	}

	/**
	 * @param int $calendarId
	 * @return IUser[]
	 */
	private function getAllUsersWithWriteAccessToCalendar(int $calendarId):array {
		$shares = $this->caldavBackend->getShares($calendarId);

		$users = [];
		$userIds = [];
		$groups = [];
		foreach ($shares as $share) {
			// Only consider writable shares
			if ($share['readOnly']) {
				continue;
			}

			$principal = explode('/', $share['{http://owncloud.org/ns}principal']);
			if ($principal[1] === 'users') {
				$user = $this->userManager->get($principal[2]);
				if ($user) {
					$users[] = $user;
					$userIds[] = $principal[2];
				}
			} elseif ($principal[1] === 'groups') {
				$groups[] = $principal[2];
			}
		}

		foreach ($groups as $gid) {
			$group = $this->groupManager->get($gid);
			if ($group instanceof IGroup) {
				foreach ($group->getUsers() as $user) {
					if (!\in_array($user->getUID(), $userIds, true)) {
						$users[] = $user;
						$userIds[] = $user->getUID();
					}
				}
			}
		}

		return $users;
	}

	/**
	 * Gets a hash of the event.
	 * If the hash changes, we have to update all relative alarms.
	 *
	 * @param VEvent $vevent
	 * @return string
	 */
	private function getEventHash(VEvent $vevent):string {
		$properties = [
			(string) $vevent->DTSTART->serialize(),
		];

		if ($vevent->DTEND) {
			$properties[] = (string) $vevent->DTEND->serialize();
		}
		if ($vevent->DURATION) {
			$properties[] = (string) $vevent->DURATION->serialize();
		}
		if ($vevent->{'RECURRENCE-ID'}) {
			$properties[] = (string) $vevent->{'RECURRENCE-ID'}->serialize();
		}
		if ($vevent->RRULE) {
			$properties[] = (string) $vevent->RRULE->serialize();
		}
		if ($vevent->EXDATE) {
			$properties[] = (string) $vevent->EXDATE->serialize();
		}
		if ($vevent->RDATE) {
			$properties[] = (string) $vevent->RDATE->serialize();
		}

		return md5(implode('::', $properties));
	}

	/**
	 * Gets a hash of the alarm.
	 * If the hash changes, we have to update oc_dav_reminders.
	 *
	 * @param VAlarm $valarm
	 * @return string
	 */
	private function getAlarmHash(VAlarm $valarm):string {
		$properties = [
			(string) $valarm->ACTION->serialize(),
			(string) $valarm->TRIGGER->serialize(),
		];

		if ($valarm->DURATION) {
			$properties[] = (string) $valarm->DURATION->serialize();
		}
		if ($valarm->REPEAT) {
			$properties[] = (string) $valarm->REPEAT->serialize();
		}

		return md5(implode('::', $properties));
	}

	/**
	 * @param VObject\Component\VCalendar $vcalendar
	 * @param int $recurrenceId
	 * @param bool $isRecurrenceException
	 * @return VEvent|null
	 */
	private function getVEventByRecurrenceId(VObject\Component\VCalendar $vcalendar,
											 int $recurrenceId,
											 bool $isRecurrenceException):?VEvent {
		$vevents = $this->getAllVEventsFromVCalendar($vcalendar);
		if (count($vevents) === 0) {
			return null;
		}

		$uid = (string) $vevents[0]->UID;
		$recurrenceExceptions = $this->getRecurrenceExceptionFromListOfVEvents($vevents);
		$masterItem = $this->getMasterItemFromListOfVEvents($vevents);

		// Handle recurrence-exceptions first, because recurrence-expansion is expensive
		if ($isRecurrenceException) {
			foreach ($recurrenceExceptions as $recurrenceException) {
				if ($this->getEffectiveRecurrenceIdOfVEvent($recurrenceException) === $recurrenceId) {
					return $recurrenceException;
				}
			}

			return null;
		}

		if ($masterItem) {
			try {
				$iterator = new EventIterator($vevents, $uid);
			} catch (NoInstancesException $e) {
				// This event is recurring, but it doesn't have a single
				// instance. We are skipping this event from the output
				// entirely.
				return null;
			}

			while ($iterator->valid()) {
				$event = $iterator->getEventObject();

				// Recurrence-exceptions are handled separately, so just ignore them here
				if (\in_array($event, $recurrenceExceptions, true)) {
					$iterator->next();
					continue;
				}

				if ($this->getEffectiveRecurrenceIdOfVEvent($event) === $recurrenceId) {
					return $event;
				}

				$iterator->next();
			}
		}

		return null;
	}

	/**
	 * @param VEvent $vevent
	 * @return string
	 */
	private function getStatusOfEvent(VEvent $vevent):string {
		if ($vevent->STATUS) {
			return (string) $vevent->STATUS;
		}

		// Doesn't say so in the standard,
		// but we consider events without a status
		// to be confirmed
		return 'CONFIRMED';
	}

	/**
	 * @param VObject\Component\VEvent $vevent
	 * @return bool
	 */
	private function wasEventCancelled(VObject\Component\VEvent $vevent):bool {
		return $this->getStatusOfEvent($vevent) === 'CANCELLED';
	}

	/**
	 * @param string $calendarData
	 * @return VObject\Component\VCalendar|null
	 */
	private function parseCalendarData(string $calendarData):?VObject\Component\VCalendar {
		try {
			return VObject\Reader::read($calendarData,
				VObject\Reader::OPTION_FORGIVING);
		} catch (ParseException $ex) {
			return null;
		}
	}

	/**
	 * @param string $principalUri
	 * @return IUser|null
	 */
	private function getUserFromPrincipalURI(string $principalUri):?IUser {
		if (!$principalUri) {
			return null;
		}

		if (stripos($principalUri, 'principals/users/') !== 0) {
			return null;
		}

		$userId = substr($principalUri, 17);
		return $this->userManager->get($userId);
	}

	/**
	 * @param VObject\Component\VCalendar $vcalendar
	 * @return VObject\Component\VEvent[]
	 */
	private function getAllVEventsFromVCalendar(VObject\Component\VCalendar $vcalendar):array {
		$vevents = [];

		foreach ($vcalendar->children() as $child) {
			if (!($child instanceof VObject\Component)) {
				continue;
			}

			if ($child->name !== 'VEVENT') {
				continue;
			}

			$vevents[] = $child;
		}

		return $vevents;
	}

	/**
	 * @param array $vevents
	 * @return VObject\Component\VEvent[]
	 */
	private function getRecurrenceExceptionFromListOfVEvents(array $vevents):array {
		return array_values(array_filter($vevents, function (VEvent $vevent) {
			return $vevent->{'RECURRENCE-ID'} !== null;
		}));
	}

	/**
	 * @param array $vevents
	 * @return VEvent|null
	 */
	private function getMasterItemFromListOfVEvents(array $vevents):?VEvent {
		$elements = array_values(array_filter($vevents, function (VEvent $vevent) {
			return $vevent->{'RECURRENCE-ID'} === null;
		}));

		if (count($elements) === 0) {
			return null;
		}
		if (count($elements) > 1) {
			throw new \TypeError('Multiple master objects');
		}

		return $elements[0];
	}

	/**
	 * @param VAlarm $valarm
	 * @return bool
	 */
	private function isAlarmRelative(VAlarm $valarm):bool {
		$trigger = $valarm->TRIGGER;
		return $trigger instanceof VObject\Property\ICalendar\Duration;
	}

	/**
	 * @param VEvent $vevent
	 * @return int
	 */
	private function getEffectiveRecurrenceIdOfVEvent(VEvent $vevent):int {
		if (isset($vevent->{'RECURRENCE-ID'})) {
			return $vevent->{'RECURRENCE-ID'}->getDateTime()->getTimestamp();
		}

		return $vevent->DTSTART->getDateTime()->getTimestamp();
	}

	/**
	 * @param VEvent $vevent
	 * @return bool
	 */
	private function isRecurring(VEvent $vevent):bool {
		return isset($vevent->RRULE) || isset($vevent->RDATE);
	}
}
