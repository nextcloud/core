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

namespace OCA\DAV\Tests\unit\CalDAV\Reminder\NotificationProvider;

use OCA\DAV\CalDAV\Reminder\NotificationProvider\EmailProvider;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\L10N\IFactory as L10NFactory;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use Sabre\VObject\Component\VCalendar;

class EmailProviderTest extends AbstractNotificationProviderTest {

	const USER_EMAIL = 'frodo@hobb.it';

	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	protected $logger;

	/** @var L10NFactory|\PHPUnit\Framework\MockObject\MockObject */
	protected $l10nFactory;

	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	protected $l10n;

	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	protected $urlGenerator;

	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;

	/** @var IMailer|\PHPUnit\Framework\MockObject\MockObject */
	private $mailer;

	protected function setUp(): void {
		parent::setUp();

		$this->mailer = $this->createMock(IMailer::class);

		$this->provider = new EmailProvider(
			$this->config,
			$this->mailer,
			$this->logger,
			$this->l10nFactory,
			$this->urlGenerator
		);
	}

	public function testSendWithoutAttendees():void {
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')
			->willReturn('uid1');
		$user1->method('getEMailAddress')
			->willReturn('uid1@example.com');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')
			->willReturn('uid2');
		$user2->method('getEMailAddress')
			->willReturn('uid2@example.com');
		$user3 = $this->createMock(IUser::class);
		$user3->method('getUID')
			->willReturn('uid3');
		$user3->method('getEMailAddress')
			->willReturn('uid3@example.com');
		$user4 = $this->createMock(IUser::class);
		$user4->method('getUID')
			->willReturn('uid4');
		$user4->method('getEMailAddress')
			->willReturn(null);

		$users = [$user1, $user2, $user3, $user4];

		$this->config->expects($this->at(0))
			->method('getUserValue')
			->with('uid1', 'core', 'lang', null)
			->willReturn(null);
		$this->config->expects($this->at(1))
			->method('getUserValue')
			->with('uid2', 'core', 'lang', null)
			->willReturn('de');
		$this->config->expects($this->at(2))
			->method('getUserValue')
			->with('uid3', 'core', 'lang', null)
			->willReturn('de');

		$enL10N = $this->createMock(IL10N::class);
		$enL10N->method('t')
			->willReturnArgument(0);
		$enL10N->method('l')
			->willReturnArgument(0);

		$deL10N = $this->createMock(IL10N::class);
		$deL10N->method('t')
			->willReturnArgument(0);
		$deL10N->method('l')
			->willReturnArgument(0);

		$this->l10nFactory->expects($this->at(0))
			->method('findLanguage')
			->with()
			->willReturn('en');

		$this->l10nFactory->expects($this->at(1))
			->method('languageExists')
			->with('dav', 'en')
			->willReturn(true);

		$this->l10nFactory->expects($this->at(2))
			->method('get')
			->with('dav', 'en')
			->willReturn($enL10N);

		$this->l10nFactory->expects($this->at(3))
			->method('languageExists')
			->with('dav', 'de')
			->willReturn(true);

		$this->l10nFactory->expects($this->at(4))
			->method('get')
			->with('dav', 'de')
			->willReturn($deL10N);

		$template1 = $this->getTemplateMock();
		$message11 = $this->getMessageMock('uid1@example.com', $template1);
		$template2 = $this->getTemplateMock();
		$message21 = $this->getMessageMock('uid2@example.com', $template2);
		$message22 = $this->getMessageMock('uid3@example.com', $template2);

		$this->mailer->expects($this->at(0))
			->method('createEMailTemplate')
			->with('dav.calendarReminder')
			->willReturn($template1);

		$this->mailer->expects($this->at(1))
			->method('createMessage')
			->with()
			->willReturn($message11);
		$this->mailer->expects($this->at(2))
			->method('send')
			->with($message11)
			->willReturn([]);

		$this->mailer->expects($this->at(3))
			->method('createEMailTemplate')
			->with('dav.calendarReminder')
			->willReturn($template2);

		$this->mailer->expects($this->at(4))
			->method('createMessage')
			->with()
			->willReturn($message21);
		$this->mailer->expects($this->at(5))
			->method('send')
			->with($message21)
			->willReturn([]);
		$this->mailer->expects($this->at(6))
			->method('createMessage')
			->with()
			->willReturn($message22);
		$this->mailer->expects($this->at(7))
			->method('send')
			->with($message22)
			->willReturn([]);

		$this->setupURLGeneratorMock(2);

		$vcalendar = $this->getNoAttendeeVCalendar();
		$this->provider->send($vcalendar->VEVENT, $this->calendarDisplayName, $users);
	}

	public function testSendWithAttendees(): void {
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')
			->willReturn('uid1');
		$user1->method('getEMailAddress')
			->willReturn('uid1@example.com');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')
			->willReturn('uid2');
		$user2->method('getEMailAddress')
			->willReturn('uid2@example.com');
		$user3 = $this->createMock(IUser::class);
		$user3->method('getUID')
			->willReturn('uid3');
		$user3->method('getEMailAddress')
			->willReturn('uid3@example.com');
		$user4 = $this->createMock(IUser::class);
		$user4->method('getUID')
			->willReturn('uid4');
		$user4->method('getEMailAddress')
			->willReturn(null);

		$users = [$user1, $user2, $user3, $user4];

		$this->config->expects($this->at(0))
			->method('getUserValue')
			->with('uid1', 'core', 'lang', null)
			->willReturn(null);
		$this->config->expects($this->at(1))
			->method('getUserValue')
			->with('uid2', 'core', 'lang', null)
			->willReturn('de');
		$this->config->expects($this->at(2))
			->method('getUserValue')
			->with('uid3', 'core', 'lang', null)
			->willReturn('de');

		$enL10N = $this->createMock(IL10N::class);
		$enL10N->method('t')
			->willReturnArgument(0);
		$enL10N->method('l')
			->willReturnArgument(0);

		$deL10N = $this->createMock(IL10N::class);
		$deL10N->method('t')
			->willReturnArgument(0);
		$deL10N->method('l')
			->willReturnArgument(0);

		$this->l10nFactory->expects($this->at(0))
			->method('findLanguage')
			->with()
			->willReturn('en');

		$this->l10nFactory->expects($this->at(1))
			->method('languageExists')
			->with('dav', 'de')
			->willReturn(true);

		$this->l10nFactory->expects($this->at(2))
			->method('get')
			->with('dav', 'de')
			->willReturn($enL10N);

		$this->l10nFactory->expects($this->at(3))
			->method('languageExists')
			->with('dav', 'en')
			->willReturn(true);

		$this->l10nFactory->expects($this->at(4))
			->method('get')
			->with('dav', 'en')
			->willReturn($deL10N);

		$template1 = $this->getTemplateMock();
		$message11 = $this->getMessageMock('foo1@example.org', $template1);
		$message12 = $this->getMessageMock('uid2@example.com', $template1);
		$message13 = $this->getMessageMock('uid3@example.com', $template1);
		$template2 = $this->getTemplateMock();
		$message21 = $this->getMessageMock('foo3@example.org', $template2);
		$message22 = $this->getMessageMock('foo4@example.org', $template2);
		$message23 = $this->getMessageMock('uid1@example.com', $template2);

		$this->mailer->expects($this->at(0))
			->method('createEMailTemplate')
			->with('dav.calendarReminder')
			->willReturn($template1);

		$this->mailer->expects($this->at(1))
			->method('createMessage')
			->with()
			->willReturn($message11);
		$this->mailer->expects($this->at(2))
			->method('send')
			->with($message11)
			->willReturn([]);
		$this->mailer->expects($this->at(3))
			->method('createMessage')
			->with()
			->willReturn($message12);
		$this->mailer->expects($this->at(4))
			->method('send')
			->with($message12)
			->willReturn([]);
		$this->mailer->expects($this->at(5))
			->method('createMessage')
			->with()
			->willReturn($message13);
		$this->mailer->expects($this->at(6))
			->method('send')
			->with($message13)
			->willReturn([]);

		$this->mailer->expects($this->at(7))
			->method('createEMailTemplate')
			->with('dav.calendarReminder')
			->willReturn($template2);

		$this->mailer->expects($this->at(8))
			->method('createMessage')
			->with()
			->willReturn($message21);
		$this->mailer->expects($this->at(9))
			->method('send')
			->with($message21)
			->willReturn([]);
		$this->mailer->expects($this->at(10))
			->method('createMessage')
			->with()
			->willReturn($message22);
		$this->mailer->expects($this->at(11))
			->method('send')
			->with($message22)
			->willReturn([]);
		$this->mailer->expects($this->at(12))
			->method('createMessage')
			->with()
			->willReturn($message23);
		$this->mailer->expects($this->at(13))
			->method('send')
			->with($message23)
			->willReturn([]);

		$this->setupURLGeneratorMock(2);

		$vcalendar = $this->getAttendeeVCalendar();
		$this->provider->send($vcalendar->VEVENT, $this->calendarDisplayName, $users);
	}

	/**
	 * @return IEMailTemplate
	 */
	private function getTemplateMock():IEMailTemplate {
		$template = $this->createMock(IEMailTemplate::class);

		$template->expects($this->at(0))
			->method('addHeader')
			->with()
			->willReturn($template);

		$template->expects($this->at(1))
			->method('setSubject')
			->with()
			->willReturn($template);

		$template->expects($this->at(2))
			->method('addHeading')
			->with()
			->willReturn($template);

		$template->expects($this->at(3))
			->method('addBodyListItem')
			->with()
			->willReturn($template);

		$template->expects($this->at(4))
			->method('addBodyListItem')
			->with()
			->willReturn($template);

		$template->expects($this->at(5))
			->method('addBodyListItem')
			->with()
			->willReturn($template);

		$template->expects($this->at(6))
			->method('addBodyListItem')
			->with()
			->willReturn($template);

		$template->expects($this->at(7))
			->method('addFooter')
			->with()
			->willReturn($template);

		return $template;
	}

	/**
	 * @param array $toMail
	 * @param IEMailTemplate $templateMock
	 * @param array $replyTo
	 * @return IMessage
	 */
	private function getMessageMock(string $toMail, IEMailTemplate $templateMock, array $replyTo=null):IMessage {
		$message = $this->createMock(IMessage::class);
		$i = 0;

		$message->expects($this->at($i++))
			->method('setFrom')
			->with([\OCP\Util::getDefaultEmailAddress('reminders-noreply')])
			->willReturn($message);

		if ($replyTo) {
			$message->expects($this->at($i++))
				->method('setReplyTo')
				->with($replyTo)
				->willReturn($message);
		}

		$message->expects($this->at($i++))
			->method('setTo')
			->with([$toMail])
			->willReturn($message);

		$message->expects($this->at($i++))
			->method('useTemplate')
			->with($templateMock)
			->willReturn($message);

		return $message;
	}

	private function getNoAttendeeVCalendar():VCalendar {
		$vcalendar = new VCalendar();
		$vcalendar->add('VEVENT', [
			'SUMMARY' => 'Fellowship meeting',
			'DTSTART' => new \DateTime('2017-01-01 00:00:00+00:00'), // 1483228800,
			'UID' => 'uid1234',
			'LOCATION' => 'Location 123',
			'DESCRIPTION' => 'DESCRIPTION 456',
		]);

		return $vcalendar;
	}

	private function getAttendeeVCalendar():VCalendar {
		$vcalendar = new VCalendar();
		$vcalendar->add('VEVENT', [
			'SUMMARY' => 'Fellowship meeting',
			'DTSTART' => new \DateTime('2017-01-01 00:00:00+00:00'), // 1483228800,
			'UID' => 'uid1234',
			'LOCATION' => 'Location 123',
			'DESCRIPTION' => 'DESCRIPTION 456',
		]);

		$vcalendar->VEVENT->add(
			'ATTENDEE',
			'mailto:foo1@example.org',
			[
				'LANG' => 'de',
				'PARTSTAT' => 'NEEDS-ACTION',
			]
		);

		$vcalendar->VEVENT->add(
			'ATTENDEE',
			'mailto:foo2@example.org',
			[
				'LANG' => 'de',
				'PARTSTAT' => 'DECLINED',
			]
		);

		$vcalendar->VEVENT->add(
			'ATTENDEE',
			'mailto:foo3@example.org',
			[
				'LANG' => 'en',
				'PARTSTAT' => 'CONFIRMED',
			]
		);

		$vcalendar->VEVENT->add(
			'ATTENDEE',
			'mailto:foo4@example.org'
		);

		$vcalendar->VEVENT->add(
			'ATTENDEE',
			'tomail:foo5@example.org'
		);

		return $vcalendar;
	}

	private function setupURLGeneratorMock(int $times=1):void {
		for ($i = 0; $i < $times; $i++) {
			$this->urlGenerator
				->expects($this->at(8 * $i))
				->method('imagePath')
				->with('core', 'actions/info.svg')
				->willReturn('imagePath1');

			$this->urlGenerator
				->expects($this->at(8 * $i + 1))
				->method('getAbsoluteURL')
				->with('imagePath1')
				->willReturn('AbsURL1');

			$this->urlGenerator
				->expects($this->at(8 * $i + 2))
				->method('imagePath')
				->with('core', 'places/calendar.svg')
				->willReturn('imagePath2');

			$this->urlGenerator
				->expects($this->at(8 * $i + 3))
				->method('getAbsoluteURL')
				->with('imagePath2')
				->willReturn('AbsURL2');

			$this->urlGenerator
				->expects($this->at(8 * $i + 4))
				->method('imagePath')
				->with('core', 'actions/address.svg')
				->willReturn('imagePath3');

			$this->urlGenerator
				->expects($this->at(8 * $i + 5))
				->method('getAbsoluteURL')
				->with('imagePath3')
				->willReturn('AbsURL3');

			$this->urlGenerator
				->expects($this->at(8 * $i + 6))
				->method('imagePath')
				->with('core', 'actions/more.svg')
				->willReturn('imagePath4');

			$this->urlGenerator
				->expects($this->at(8 * $i + 7))
				->method('getAbsoluteURL')
				->with('imagePath4')
				->willReturn('AbsURL4');
		}
	}
}
