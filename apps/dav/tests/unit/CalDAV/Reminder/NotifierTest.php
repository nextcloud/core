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

use OCA\DAV\AppInfo\Application;
use OCA\DAV\CalDAV\Reminder\Notifier;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use Test\TestCase;

class NotifierTest extends TestCase {
	/** @var Notifier */
	protected $notifier;

	/** @var IFactory|\PHPUnit\Framework\MockObject\MockObject */
	protected $factory;

	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	protected $urlGenerator;

	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	protected $l10n;

	/** @var ITimeFactory|\PHPUnit\Framework\MockObject\MockObject */
	protected $timeFactory;

	protected function setUp(): void {
		parent::setUp();

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->expects($this->any())
			->method('t')
			->willReturnCallback(function ($string, $args) {
				return vsprintf($string, $args);
			});
		$this->l10n->expects($this->any())
			->method('l')
			->willReturnCallback(function ($string, $args) {
				/** \DateTime $args */
				return $args->format(\DateTime::ATOM);
			});
		$this->l10n->expects($this->any())
			->method('n')
			->willReturnCallback(function ($textSingular, $textPlural, $count, $args) {
				$text = $count === 1 ? $textSingular : $textPlural;
				$text = str_replace('%n', (string)$count, $text);
				return vsprintf($text, $args);
			});
		$this->factory = $this->createMock(IFactory::class);
		$this->factory->expects($this->any())
			->method('get')
			->willReturn($this->l10n);

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory
			->method('getDateTime')
			->willReturn(\DateTime::createFromFormat(\DateTime::ATOM, '2005-08-15T14:00:00+02:00'));

		$this->notifier = new Notifier(
			$this->factory,
			$this->urlGenerator,
			$this->timeFactory
		);
	}

	public function testGetId():void {
		$this->assertEquals($this->notifier->getID(), 'dav');
	}

	public function testGetName():void {
		$this->assertEquals($this->notifier->getName(), 'Calendar');
	}

	
	public function testPrepareWrongApp(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Notification not from this app');

		/** @var INotification|\PHPUnit\Framework\MockObject\MockObject $notification */
		$notification = $this->createMock(INotification::class);

		$notification->expects($this->once())
			->method('getApp')
			->willReturn('notifications');
		$notification->expects($this->never())
			->method('getSubject');

		$this->notifier->prepare($notification, 'en');
	}

	
	public function testPrepareWrongSubject() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown subject');

		/** @var INotification|\PHPUnit\Framework\MockObject\MockObject $notification */
		$notification = $this->createMock(INotification::class);

		$notification->expects($this->once())
			->method('getApp')
			->willReturn(Application::APP_ID);
		$notification->expects($this->once())
			->method('getSubject')
			->willReturn('wrong subject');

		$this->notifier->prepare($notification, 'en');
	}

	public function dataPrepare(): array
	{
		return [
			[
				'calendar_reminder',
				[
					'title' => 'Title of this event',
					'start_atom' => '2005-08-15T15:52:01+02:00'
				],
				'Title of this event (in 1 hour, 52 minutes)',
				[
					'title' => 'Title of this event',
					'description' => null,
					'location' => 'NC Headquarters',
					'all_day' => false,
					'start_atom' => '2005-08-15T15:52:01+02:00',
					'start_is_floating' => false,
					'start_timezone' => 'Europe/Berlin',
					'end_atom' => '2005-08-15T17:52:01+02:00',
					'end_is_floating' => false,
					'end_timezone' => 'Europe/Berlin',
					'calendar_displayname' => 'Personal',
				],
				"Calendar: Personal\r\nDate: 2005-08-15T15:52:01+02:00, 2005-08-15T15:52:01+02:00 - 2005-08-15T17:52:01+02:00 (Europe/Berlin)\r\nWhere: NC Headquarters"
			],
		];
	}

	/**
	 * @dataProvider dataPrepare
	 *
	 * @param string $subjectType
	 * @param array $subjectParams
	 * @param string $subject
	 * @param array $messageParams
	 * @param string $message
	 * @throws \Exception
	 */
	public function testPrepare(string $subjectType, array $subjectParams, string $subject, array $messageParams, string $message): void
	{
		/** @var INotification|\PHPUnit\Framework\MockObject\MockObject $notification */
		$notification = $this->createMock(INotification::class);

		$notification->expects($this->once())
			->method('getApp')
			->willReturn(Application::APP_ID);
		$notification->expects($this->once())
			->method('getSubject')
			->willReturn($subjectType);
		$notification->expects($this->once())
			->method('getSubjectParameters')
			->willReturn($subjectParams);
		$notification->expects($this->once())
			->method('getMessageParameters')
			->willReturn($messageParams);

		$notification->expects($this->once())
			->method('setParsedSubject')
			->with($subject)
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setParsedMessage')
			->with($message)
			->willReturnSelf();

		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->with('core', 'places/calendar.svg')
			->willReturn('icon-url');
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('icon-url')
			->willReturn('absolute-icon-url');
		$notification->expects($this->once())
			->method('setIcon')
			->with('absolute-icon-url')
			->willReturnSelf();

		$return = $this->notifier->prepare($notification, 'en');

		$this->assertEquals($notification, $return);
	}
}
