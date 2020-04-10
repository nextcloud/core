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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\DAV\Tests\unit\CalDAV\Reminder;

use OCA\DAV\CalDAV\Reminder\Backend as ReminderBackend;
use OCP\AppFramework\Utility\ITimeFactory;
use Test\TestCase;

class BackendTest extends TestCase {

	/**
	 * Reminder Backend
	 *
	 * @var ReminderBackend|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $reminderBackend;

	/** @var ITimeFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $timeFactory;

	protected function setUp(): void {
		parent::setUp();

		$query = self::$realDatabase->getQueryBuilder();
		$query->delete('calendar_reminders')->execute();
		$query->delete('calendarobjects')->execute();
		$query->delete('calendars')->execute();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->reminderBackend = new ReminderBackend(self::$realDatabase, $this->timeFactory);

		$this->createRemindersTestSet();
	}

	protected function tearDown(): void {
		$query = self::$realDatabase->getQueryBuilder();
		$query->delete('calendar_reminders')->execute();
		$query->delete('calendarobjects')->execute();
		$query->delete('calendars')->execute();
	}


	public function testCleanRemindersForEvent(): void {
		$query = self::$realDatabase->getQueryBuilder();
		$rows = $query->select('*')
			->from('calendar_reminders')
			->execute()
			->fetchAll();

		$this->assertCount(4, $rows);

		$this->reminderBackend->cleanRemindersForEvent(1);

		$query = self::$realDatabase->getQueryBuilder();
		$rows = $query->select('*')
			->from('calendar_reminders')
			->execute()
			->fetchAll();

		$this->assertCount(2, $rows);
	}

	public function testCleanRemindersForCalendar(): void {
		$query = self::$realDatabase->getQueryBuilder();
		$rows = $query->select('*')
			->from('calendar_reminders')
			->execute()
			->fetchAll();

		$this->assertCount(4, $rows);

		$this->reminderBackend->cleanRemindersForCalendar(1);

		$query = self::$realDatabase->getQueryBuilder();
		$rows = $query->select('*')
			->from('calendar_reminders')
			->execute()
			->fetchAll();

		$this->assertCount(1, $rows);
	}

	public function testRemoveReminder(): void {
		$query = self::$realDatabase->getQueryBuilder();
		$rows = $query->select('*')
			->from('calendar_reminders')
			->execute()
			->fetchAll();

		$this->assertCount(4, $rows);

		$this->reminderBackend->removeReminder((int) $rows[3]['id']);

		$query = self::$realDatabase->getQueryBuilder();
		$rows = $query->select('*')
			->from('calendar_reminders')
			->execute()
			->fetchAll();

		$this->assertCount(3, $rows);
	}

	public function testGetRemindersToProcess(): void {
		$this->timeFactory->expects($this->exactly(1))
			->method('getTime')
			->with()
			->willReturn(123457);

		$rows = $this->reminderBackend->getRemindersToProcess();

		$this->assertCount(2, $rows);
		unset($rows[0]['id']);
		unset($rows[1]['id']);

		$this->assertEquals($rows[0], [
			'calendar_id' => 1,
			'object_id' => 1,
			'uid' => 'asd',
			'is_recurring' => false,
			'recurrence_id' => 123458,
			'is_recurrence_exception' => false,
			'event_hash' => 'asd123',
			'alarm_hash' => 'asd567',
			'type' => 'EMAIL',
			'is_relative' => true,
			'notification_date' => 123456,
			'is_repeat_based' => false,
			'calendardata' => 'Calendar data 123',
			'displayname' => 'Displayname 123',
			'principaluri' => 'principals/users/user001',
		]);
		$this->assertEquals($rows[1], [
			'calendar_id' => 1,
			'object_id' => 1,
			'uid' => 'asd',
			'is_recurring' => false,
			'recurrence_id' => 123458,
			'is_recurrence_exception' => false,
			'event_hash' => 'asd123',
			'alarm_hash' => 'asd567',
			'type' => 'AUDIO',
			'is_relative' => true,
			'notification_date' => 123456,
			'is_repeat_based' => false,
			'calendardata' => 'Calendar data 123',
			'displayname' => 'Displayname 123',
			'principaluri' => 'principals/users/user001',
		]);
	}

	public function testGetAllScheduledRemindersForEvent(): void {
		$rows = $this->reminderBackend->getAllScheduledRemindersForEvent(1);

		$this->assertCount(2, $rows);
		unset($rows[0]['id']);
		unset($rows[1]['id']);

		$this->assertEquals($rows[0], [
			'calendar_id' => 1,
			'object_id' => 1,
			'uid' => 'asd',
			'is_recurring' => false,
			'recurrence_id' => 123458,
			'is_recurrence_exception' => false,
			'event_hash' => 'asd123',
			'alarm_hash' => 'asd567',
			'type' => 'EMAIL',
			'is_relative' => true,
			'notification_date' => 123456,
			'is_repeat_based' => false,
		]);
		$this->assertEquals($rows[1], [
			'calendar_id' => 1,
			'object_id' => 1,
			'uid' => 'asd',
			'is_recurring' => false,
			'recurrence_id' => 123458,
			'is_recurrence_exception' => false,
			'event_hash' => 'asd123',
			'alarm_hash' => 'asd567',
			'type' => 'AUDIO',
			'is_relative' => true,
			'notification_date' => 123456,
			'is_repeat_based' => false,
		]);
	}

	public function testInsertReminder(): void {
		$query = self::$realDatabase->getQueryBuilder();
		$rows = $query->select('*')
			->from('calendar_reminders')
			->execute()
			->fetchAll();

		$this->assertCount(4, $rows);

		$this->reminderBackend->insertReminder(42, 1337, 'uid99', true, 12345678,
			true, 'hash99', 'hash42', 'AUDIO', false, 12345670, false);

		$query = self::$realDatabase->getQueryBuilder();
		$rows = $query->select('*')
			->from('calendar_reminders')
			->execute()
			->fetchAll();

		$this->assertCount(5, $rows);

		unset($rows[4]['id']);

		$this->assertEquals($rows[4], [
			'calendar_id' => '42',
			'object_id' => '1337',
			'is_recurring' => '1',
			'uid' => 'uid99',
			'recurrence_id' => '12345678',
			'is_recurrence_exception' => '1',
			'event_hash' => 'hash99',
			'alarm_hash' => 'hash42',
			'type' => 'AUDIO',
			'is_relative' => '0',
			'notification_date' => '12345670',
			'is_repeat_based' => '0',
		]);
	}

	public function testUpdateReminder() {
		$query = self::$realDatabase->getQueryBuilder();
		$rows = $query->select('*')
			->from('calendar_reminders')
			->execute()
			->fetchAll();

		$this->assertCount(4, $rows);

		$this->assertEquals($rows[3]['notification_date'], 123600);

		$reminderId = (int)  $rows[3]['id'];
		$newNotificationDate = 123700;

		$this->reminderBackend->updateReminder($reminderId, $newNotificationDate);

		$query = self::$realDatabase->getQueryBuilder();
		$row = $query->select('notification_date')
			->from('calendar_reminders')
			->where($query->expr()->eq('id', $query->createNamedParameter($reminderId)))
			->execute()
			->fetch();

		$this->assertEquals((int) $row['notification_date'], 123700);
	}


	private function createRemindersTestSet(): void {
		$query = self::$realDatabase->getQueryBuilder();
		$query->insert('calendars')
			->values([
				'id' => $query->createNamedParameter(1),
				'principaluri' => $query->createNamedParameter('principals/users/user001'),
				'displayname' => $query->createNamedParameter('Displayname 123'),
			])
			->execute();

		$query = self::$realDatabase->getQueryBuilder();
		$query->insert('calendars')
			->values([
				'id' => $query->createNamedParameter(99),
				'principaluri' => $query->createNamedParameter('principals/users/user002'),
				'displayname' => $query->createNamedParameter('Displayname 99'),
			])
			->execute();

		$query = self::$realDatabase->getQueryBuilder();
		$query->insert('calendarobjects')
			->values([
				'id' => $query->createNamedParameter(1),
				'calendardata' => $query->createNamedParameter('Calendar data 123'),
				'calendarid' => $query->createNamedParameter(1),
				'size' => $query->createNamedParameter(42),
			])
			->execute();

		$query = self::$realDatabase->getQueryBuilder();
		$query->insert('calendarobjects')
			->values([
				'id' => $query->createNamedParameter(2),
				'calendardata' => $query->createNamedParameter('Calendar data 456'),
				'calendarid' => $query->createNamedParameter(1),
				'size' => $query->createNamedParameter(42),
			])
			->execute();

		$query = self::$realDatabase->getQueryBuilder();
		$query->insert('calendarobjects')
			->values([
				'id' => $query->createNamedParameter(10),
				'calendardata' => $query->createNamedParameter('Calendar data 789'),
				'calendarid' => $query->createNamedParameter(99),
				'size' => $query->createNamedParameter(42),
			])
			->execute();

		$query = self::$realDatabase->getQueryBuilder();
		$query->insert('calendar_reminders')
			->values([
				'calendar_id' => $query->createNamedParameter(1),
				'object_id' => $query->createNamedParameter(1),
				'uid' => $query->createNamedParameter('asd'),
				'is_recurring' => $query->createNamedParameter(0),
				'recurrence_id' => $query->createNamedParameter(123458),
				'is_recurrence_exception' => $query->createNamedParameter(0),
				'event_hash' => $query->createNamedParameter('asd123'),
				'alarm_hash' => $query->createNamedParameter('asd567'),
				'type' => $query->createNamedParameter('EMAIL'),
				'is_relative' => $query->createNamedParameter(1),
				'notification_date' => $query->createNamedParameter(123456),
				'is_repeat_based' => $query->createNamedParameter(0),
			])
			->execute();

		$query = self::$realDatabase->getQueryBuilder();
		$query->insert('calendar_reminders')
			->values([
				'calendar_id' => $query->createNamedParameter(1),
				'object_id' => $query->createNamedParameter(1),
				'uid' => $query->createNamedParameter('asd'),
				'is_recurring' => $query->createNamedParameter(0),
				'recurrence_id' => $query->createNamedParameter(123458),
				'is_recurrence_exception' => $query->createNamedParameter(0),
				'event_hash' => $query->createNamedParameter('asd123'),
				'alarm_hash' => $query->createNamedParameter('asd567'),
				'type' => $query->createNamedParameter('AUDIO'),
				'is_relative' => $query->createNamedParameter(1),
				'notification_date' => $query->createNamedParameter(123456),
				'is_repeat_based' => $query->createNamedParameter(0),
			])
			->execute();

		$query = self::$realDatabase->getQueryBuilder();
		$query->insert('calendar_reminders')
			->values([
				'calendar_id' => $query->createNamedParameter(1),
				'object_id' => $query->createNamedParameter(2),
				'uid' => $query->createNamedParameter('asd'),
				'is_recurring' => $query->createNamedParameter(0),
				'recurrence_id' => $query->createNamedParameter(123900),
				'is_recurrence_exception' => $query->createNamedParameter(0),
				'event_hash' => $query->createNamedParameter('asd123'),
				'alarm_hash' => $query->createNamedParameter('asd567'),
				'type' => $query->createNamedParameter('EMAIL'),
				'is_relative' => $query->createNamedParameter(1),
				'notification_date' => $query->createNamedParameter(123499),
				'is_repeat_based' => $query->createNamedParameter(0),
			])
			->execute();

		$query = self::$realDatabase->getQueryBuilder();
		$query->insert('calendar_reminders')
			->values([
				'calendar_id' => $query->createNamedParameter(99),
				'object_id' => $query->createNamedParameter(10),
				'uid' => $query->createNamedParameter('asd'),
				'is_recurring' => $query->createNamedParameter(0),
				'recurrence_id' => $query->createNamedParameter(123900),
				'is_recurrence_exception' => $query->createNamedParameter(0),
				'event_hash' => $query->createNamedParameter('asd123'),
				'alarm_hash' => $query->createNamedParameter('asd567'),
				'type' => $query->createNamedParameter('DISPLAY'),
				'is_relative' => $query->createNamedParameter(1),
				'notification_date' => $query->createNamedParameter(123600),
				'is_repeat_based' => $query->createNamedParameter(0),
			])
			->execute();
	}
}
