<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2017, Georg Ehrke
 *
 * @author brad2014 <brad2014@users.noreply.github.com>
 * @author Brad Rubenstein <brad@wbr.tech>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Tests\unit\CalDAV\Schedule;

use OCA\DAV\CalDAV\Schedule\IMipPlugin;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Defaults;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IAttachment;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\ITip\Message;
use Test\TestCase;

class IMipPluginTest extends TestCase {

		/** @var IMessage|MockObject */
	private $mailMessage;

	/** @var IMailer|MockObject */
	private $mailer;

	/** @var IEMailTemplate|MockObject */
	private $emailTemplate;

	/** @var IAttachment|MockObject */
	private $emailAttachment;

	/** @var ITimeFactory|MockObject */
	private $timeFactory;

	/** @var IConfig|MockObject */
	private $config;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var IQueryBuilder|MockObject */
	private $queryBuilder;

	/** @var IMipPlugin */
	private $plugin;

	protected function setUp(): void {
		$this->mailMessage = $this->createMock(IMessage::class);
		$this->mailMessage->method('setFrom')->willReturn($this->mailMessage);
		$this->mailMessage->method('setReplyTo')->willReturn($this->mailMessage);
		$this->mailMessage->method('setTo')->willReturn($this->mailMessage);

		$this->mailer = $this->getMockBuilder(IMailer::class)->disableOriginalConstructor()->getMock();
		$this->mailer->method('createMessage')->willReturn($this->mailMessage);

		$this->emailTemplate = $this->createMock(IEMailTemplate::class);
		$this->mailer->method('createEMailTemplate')->willReturn($this->emailTemplate);

		$this->emailAttachment = $this->createMock(IAttachment::class);
		$this->mailer->method('createAttachment')->willReturn($this->emailAttachment);

		/** @var ILogger|MockObject $logger */
		$logger = $this->getMockBuilder(ILogger::class)->disableOriginalConstructor()->getMock();

		$this->timeFactory = $this->getMockBuilder(ITimeFactory::class)->disableOriginalConstructor()->getMock();
		$this->timeFactory->method('getTime')->willReturn(1496912528); // 2017-01-01

		$this->config = $this->createMock(IConfig::class);

		$this->userManager = $this->createMock(IUserManager::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($l10n);

		$urlGenerator = $this->createMock(IURLGenerator::class);

		$this->queryBuilder = $this->createMock(IQueryBuilder::class);
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')
			->with()
			->willReturn($this->queryBuilder);

		$random = $this->createMock(ISecureRandom::class);
		$random->method('generate')
			->with(60, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')
			->willReturn('random_token');

		$defaults = $this->createMock(Defaults::class);
		$defaults->method('getName')
			->willReturn('Instance Name 123');

		$this->plugin = new IMipPlugin($this->config, $this->mailer, $logger, $this->timeFactory, $l10nFactory, $urlGenerator, $defaults, $random, $db, $this->userManager, 'user123');
	}

	public function testDelivery() {
		$this->config
	  ->expects($this->at(1))
			->method('getAppValue')
			->with('dav', 'invitation_link_recipients', 'yes')
			->willReturn('yes');

		$message = $this->_testMessage();
		$this->_expectSend();
		$this->plugin->schedule($message);
		$this->assertEquals('1.1', $message->getScheduleStatus());
	}

	public function testFailedDelivery() {
		$this->config
	  ->expects($this->at(1))
			->method('getAppValue')
			->with('dav', 'invitation_link_recipients', 'yes')
			->willReturn('yes');

		$message = $this->_testMessage();
		$this->mailer
			->method('send')
			->willThrowException(new \Exception());
		$this->_expectSend();
		$this->plugin->schedule($message);
		$this->assertEquals('5.0', $message->getScheduleStatus());
	}

	public function testDeliveryWithNoCommonName() {
		$this->config
	  ->expects($this->at(1))
			->method('getAppValue')
			->with('dav', 'invitation_link_recipients', 'yes')
			->willReturn('yes');

		$message = $this->_testMessage();
		$message->senderName = null;

		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn('Mr. Wizard');

		$this->userManager->expects($this->once())
			->method('get')
			->with('user123')
			->willReturn($user);

		$this->_expectSend();
		$this->plugin->schedule($message);
		$this->assertEquals('1.1', $message->getScheduleStatus());
	}

	/**
	 * @dataProvider dataNoMessageSendForPastEvents
	 */
	public function testNoMessageSendForPastEvents(array $veventParams, bool $expectsMail) {
		$this->config
	  ->method('getAppValue')
	  ->willReturn('yes');

		$message = $this->_testMessage($veventParams);

		$this->_expectSend('frodo@hobb.it', $expectsMail, $expectsMail);

		$this->plugin->schedule($message);

		if ($expectsMail) {
			$this->assertEquals('1.1', $message->getScheduleStatus());
		} else {
			$this->assertEquals(false, $message->getScheduleStatus());
		}
	}

	public function dataNoMessageSendForPastEvents() {
		return [
			[['DTSTART' => new \DateTime('2017-01-01 00:00:00')], false],
			[['DTSTART' => new \DateTime('2017-01-01 00:00:00'), 'DTEND' => new \DateTime('2017-01-01 00:00:00')], false],
			[['DTSTART' => new \DateTime('2017-01-01 00:00:00'), 'DTEND' => new \DateTime('2017-12-31 00:00:00')], true],
			[['DTSTART' => new \DateTime('2017-01-01 00:00:00'), 'DURATION' => 'P1D'], false],
			[['DTSTART' => new \DateTime('2017-01-01 00:00:00'), 'DURATION' => 'P52W'], true],
			[['DTSTART' => new \DateTime('2017-01-01 00:00:00'), 'DTEND' => new \DateTime('2017-01-01 00:00:00'), 'RRULE' => 'FREQ=WEEKLY'], true],
			[['DTSTART' => new \DateTime('2017-01-01 00:00:00'), 'DTEND' => new \DateTime('2017-01-01 00:00:00'), 'RRULE' => 'FREQ=WEEKLY;COUNT=3'], false],
			[['DTSTART' => new \DateTime('2017-01-01 00:00:00'), 'DTEND' => new \DateTime('2017-01-01 00:00:00'), 'RRULE' => 'FREQ=WEEKLY;UNTIL=20170301T000000Z'], false],
			[['DTSTART' => new \DateTime('2017-01-01 00:00:00'), 'DTEND' => new \DateTime('2017-01-01 00:00:00'), 'RRULE' => 'FREQ=WEEKLY;COUNT=33'], true],
			[['DTSTART' => new \DateTime('2017-01-01 00:00:00'), 'DTEND' => new \DateTime('2017-01-01 00:00:00'), 'RRULE' => 'FREQ=WEEKLY;UNTIL=20171001T000000Z'], true],
		];
	}

	/**
	 * @dataProvider dataIncludeResponseButtons
	 */
	public function testIncludeResponseButtons(string $config_setting, string $recipient, bool $has_buttons) {
		$message = $this->_testMessage([],$recipient);

		$this->_expectSend($recipient, true, $has_buttons);
		$this->config
	  ->expects($this->at(1))
			->method('getAppValue')
			->with('dav', 'invitation_link_recipients', 'yes')
			->willReturn($config_setting);

		$this->plugin->schedule($message);
		$this->assertEquals('1.1', $message->getScheduleStatus());
	}

	public function dataIncludeResponseButtons() {
		return [
			// dav.invitation_link_recipients, recipient, $has_buttons
			[ 'yes', 'joe@internal.com', true],
			[ 'joe@internal.com', 'joe@internal.com', true],
			[ 'internal.com', 'joe@internal.com', true],
			[ 'pete@otherinternal.com,internal.com', 'joe@internal.com', true],
			[ 'no', 'joe@internal.com', false],
			[ 'internal.com', 'joe@external.com', false],
			[ 'jane@otherinternal.com,internal.com', 'joe@otherinternal.com', false],
		];
	}

	public function testMessageSendWhenEventWithoutName() {
		$this->config
			->method('getAppValue')
			->willReturn('yes');

		$message = $this->_testMessage(['SUMMARY' => '']);
		$this->_expectSend('frodo@hobb.it', true, true,'Invitation: Untitled event');
		$this->emailTemplate->expects($this->once())
			->method('addHeading')
			->with('Invitation');
		$this->plugin->schedule($message);
		$this->assertEquals('1.1', $message->getScheduleStatus());
	}

	private function _testMessage(array $attrs = [], string $recipient = 'frodo@hobb.it') {
		$message = new Message();
		$message->method = 'REQUEST';
		$message->message = new VCalendar();
		$message->message->add('VEVENT', array_merge([
			'UID' => 'uid-1234',
			'SEQUENCE' => 0,
			'SUMMARY' => 'Fellowship meeting',
			'DTSTART' => new \DateTime('2018-01-01 00:00:00')
		], $attrs));
		$message->message->VEVENT->add('ORGANIZER', 'mailto:gandalf@wiz.ard');
		$message->message->VEVENT->add('ATTENDEE', 'mailto:'.$recipient, [ 'RSVP' => 'TRUE' ]);
		$message->sender = 'mailto:gandalf@wiz.ard';
		$message->senderName = 'Mr. Wizard';
		$message->recipient = 'mailto:'.$recipient;
		return $message;
	}


	private function _expectSend(string $recipient = 'frodo@hobb.it', bool $expectSend = true, bool $expectButtons = true, string $subject = 'Invitation: Fellowship meeting') {

		// if the event is in the past, we skip out
		if (!$expectSend) {
			$this->mailer
				->expects($this->never())
				->method('send');
			return;
		}

		$this->emailTemplate->expects($this->once())
			->method('setSubject')
			->with($subject);
		$this->mailMessage->expects($this->once())
			->method('setTo')
			->with([$recipient => null]);
		$this->mailMessage->expects($this->once())
			->method('setReplyTo')
			->with(['gandalf@wiz.ard' => 'Mr. Wizard']);
		$this->mailMessage->expects($this->once())
			->method('setFrom')
			->with(['invitations-noreply@localhost' => 'Mr. Wizard via Instance Name 123']);
		$this->mailer
			->expects($this->once())
			->method('send');

		if ($expectButtons) {
			$this->queryBuilder->expects($this->at(0))
				->method('insert')
				->with('calendar_invitations')
				->willReturn($this->queryBuilder);
			$this->queryBuilder->expects($this->at(8))
				->method('values')
				->willReturn($this->queryBuilder);
			$this->queryBuilder->expects($this->at(9))
				->method('execute');
		} else {
			$this->queryBuilder->expects($this->never())
				->method('insert')
				->with('calendar_invitations');
		}
	}
}
