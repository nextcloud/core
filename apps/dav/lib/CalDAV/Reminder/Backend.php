<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Thomas Citharel
 * @copyright Copyright (c) 2019, Georg Ehrke
 *
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\DAV\CalDAV\Reminder;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;

/**
 * Class Backend
 *
 * @package OCA\DAV\CalDAV\Reminder
 */
class Backend {

	/** @var IDBConnection */
	protected $db;

	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * Backend constructor.
	 *
	 * @param IDBConnection $db
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(IDBConnection $db,
								ITimeFactory $timeFactory) {
		$this->db = $db;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * Get all reminders with a notification date before now
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getRemindersToProcess():array {
		$query = $this->db->getQueryBuilder();
		$query->select(['cr.*', 'co.calendardata', 'c.displayname', 'c.principaluri'])
			->from('calendar_reminders', 'cr')
			->where($query->expr()->lte('cr.notification_date', $query->createNamedParameter($this->timeFactory->getTime())))
			->join('cr', 'calendarobjects', 'co', $query->expr()->eq('cr.object_id', 'co.id'))
			->join('cr', 'calendars', 'c', $query->expr()->eq('cr.calendar_id', 'c.id'));
		$stmt = $query->execute();

		return array_map(
			[$this, 'fixRowTyping'],
			$stmt->fetchAll()
		);
	}

	/**
	 * Get all scheduled reminders for an event
	 *
	 * @param int $objectId
	 * @return array
	 */
	public function getAllScheduledRemindersForEvent(int $objectId):array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('calendar_reminders')
			->where($query->expr()->eq('object_id', $query->createNamedParameter($objectId)));
		$stmt = $query->execute();

		return array_map(
			[$this, 'fixRowTyping'],
			$stmt->fetchAll()
		);
	}

	/**
	 * Insert a new reminder into the database
	 *
	 * @param int $calendarId
	 * @param int $objectId
	 * @param string $uid
	 * @param bool $isRecurring
	 * @param int $recurrenceId
	 * @param bool $isRecurrenceException
	 * @param string $eventHash
	 * @param string $alarmHash
	 * @param string $type
	 * @param bool $isRelative
	 * @param int $notificationDate
	 * @param bool $isRepeatBased
	 * @return int The insert id
	 */
	public function insertReminder(int $calendarId,
								   int $objectId,
								   string $uid,
								   bool $isRecurring,
								   int $recurrenceId,
								   bool $isRecurrenceException,
								   string $eventHash,
								   string $alarmHash,
								   string $type,
								   bool $isRelative,
								   int $notificationDate,
								   bool $isRepeatBased):int {
		$query = $this->db->getQueryBuilder();
		$query->insert('calendar_reminders')
			->values([
				'calendar_id' => $query->createNamedParameter($calendarId),
				'object_id' => $query->createNamedParameter($objectId),
				'uid' => $query->createNamedParameter($uid),
				'is_recurring' => $query->createNamedParameter($isRecurring ? 1 : 0),
				'recurrence_id' => $query->createNamedParameter($recurrenceId),
				'is_recurrence_exception' => $query->createNamedParameter($isRecurrenceException ? 1 : 0),
				'event_hash' => $query->createNamedParameter($eventHash),
				'alarm_hash' => $query->createNamedParameter($alarmHash),
				'type' => $query->createNamedParameter($type),
				'is_relative' => $query->createNamedParameter($isRelative ? 1 : 0),
				'notification_date' => $query->createNamedParameter($notificationDate),
				'is_repeat_based' => $query->createNamedParameter($isRepeatBased ? 1 : 0),
			])
			->execute();

		return $query->getLastInsertId();
	}

	/**
	 * Sets a new notificationDate on an existing reminder
	 *
	 * @param int $reminderId
	 * @param int $newNotificationDate
	 */
	public function updateReminder(int $reminderId,
								   int $newNotificationDate):void {
		$query = $this->db->getQueryBuilder();
		$query->update('calendar_reminders')
			->set('notification_date', $query->createNamedParameter($newNotificationDate))
			->where($query->expr()->eq('id', $query->createNamedParameter($reminderId)))
			->execute();
	}

	/**
	 * Remove a reminder by it's id
	 *
	 * @param integer $reminderId
	 * @return void
	 */
	public function removeReminder(int $reminderId):void {
		$query = $this->db->getQueryBuilder();

		$query->delete('calendar_reminders')
			->where($query->expr()->eq('id', $query->createNamedParameter($reminderId)))
			->execute();
	}

	/**
	 * Cleans reminders in database
	 *
	 * @param int $objectId
	 */
	public function cleanRemindersForEvent(int $objectId):void {
		$query = $this->db->getQueryBuilder();

		$query->delete('calendar_reminders')
			->where($query->expr()->eq('object_id', $query->createNamedParameter($objectId)))
			->execute();
	}

	/**
	 * Remove all reminders for a calendar
	 *
	 * @param int $calendarId
	 * @return void
	 */
	public function cleanRemindersForCalendar(int $calendarId):void {
		$query = $this->db->getQueryBuilder();

		$query->delete('calendar_reminders')
			->where($query->expr()->eq('calendar_id', $query->createNamedParameter($calendarId)))
			->execute();
	}

	/**
	 * @param array $row
	 * @return array
	 */
	private function fixRowTyping(array $row): array {
		$row['id'] = (int) $row['id'];
		$row['calendar_id'] = (int) $row['calendar_id'];
		$row['object_id'] = (int) $row['object_id'];
		$row['is_recurring'] = (bool) $row['is_recurring'];
		$row['recurrence_id'] = (int) $row['recurrence_id'];
		$row['is_recurrence_exception'] = (bool) $row['is_recurrence_exception'];
		$row['is_relative'] = (bool) $row['is_relative'];
		$row['notification_date'] = (int) $row['notification_date'];
		$row['is_repeat_based'] = (bool) $row['is_repeat_based'];

		return $row;
	}
}
