<?php
/**
 * Copyright (c) 2014-2015 Lukas Reschke <lukas@owncloud.com>
 *
 * @author Arne Hamann <github@arne.email>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Mail;

use OC\Mail\EMailTemplate;
use OC\Mail\Mailer;
use OC\Mail\Message;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\Mail\Events\BeforeMessageSent;
use Test\TestCase;
use Swift_SwiftException;

class MailerTest extends TestCase {
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var Defaults|\PHPUnit_Framework_MockObject_MockObject */
	private $defaults;
	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	private $logger;
	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l10n;
	/** @var Mailer */
	private $mailer;
	/** @var IEventDispatcher */
	private $dispatcher;


	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->defaults = $this->createMock(Defaults::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->mailer = new Mailer(
			$this->config,
			$this->logger,
			$this->defaults,
			$this->urlGenerator,
			$this->l10n,
			$this->dispatcher
		);
	}

	/**
	 * @return array
	 */
	public function sendmailModeProvider(): array {
		return [
			'smtp' => ['smtp', ' -bs'],
			'pipe' => ['pipe', ' -t'],
		];
	}

	/**
	 * @dataProvider sendmailModeProvider
	 * @param $sendmailMode
	 * @param $binaryParam
	 */
	public function testGetSendmailInstanceSendMail($sendmailMode, $binaryParam) {
		$this->config
			->expects($this->exactly(2))
			->method('getSystemValue')
			->willReturnMap([
				['mail_smtpmode', 'smtp', 'sendmail'],
				['mail_sendmailmode', 'smtp', $sendmailMode],
			]);

		$path = \OC_Helper::findBinaryPath('sendmail');
		if ($path === null) {
			$path = '/usr/sbin/sendmail';
		}

		$expected = new \Swift_SendmailTransport($path . $binaryParam);
		$this->assertEquals($expected, self::invokePrivate($this->mailer, 'getSendMailInstance'));
	}

	/**
	 * @dataProvider sendmailModeProvider
	 * @param $sendmailMode
	 * @param $binaryParam
	 */
	public function testGetSendmailInstanceSendMailQmail($sendmailMode, $binaryParam) {
		$this->config
			->expects($this->exactly(2))
			->method('getSystemValue')
			->willReturnMap([
				['mail_smtpmode', 'smtp', 'qmail'],
				['mail_sendmailmode', 'smtp', $sendmailMode],
			]);

		$this->assertEquals(new \Swift_SendmailTransport('/var/qmail/bin/sendmail' . $binaryParam), self::invokePrivate($this->mailer, 'getSendMailInstance'));
	}

	public function testGetInstanceDefault() {
		$mailer = self::invokePrivate($this->mailer, 'getInstance');
		$this->assertInstanceOf(\Swift_Mailer::class, $mailer);
		$this->assertInstanceOf(\Swift_SmtpTransport::class, $mailer->getTransport());
	}

	public function testGetInstanceSendmail() {
		$this->config
			->method('getSystemValue')
			->willReturnMap([
				['mail_smtpmode', 'smtp', 'sendmail'],
				['mail_sendmailmode', 'smtp', 'smtp'],
			]);

		$mailer = self::invokePrivate($this->mailer, 'getInstance');
		$this->assertInstanceOf(\Swift_Mailer::class, $mailer);
		$this->assertInstanceOf(\Swift_SendmailTransport::class, $mailer->getTransport());
	}

	public function testEvents() {
		$message = $this->createMock(Message::class);

		$event = new BeforeMessageSent($message);
		$this->dispatcher->expects($this->at(0))
			->method('dispatchTyped')
			->with($this->equalTo($event));

		# We do not care at this point about errors in Swiftmailer
		try {
			$this->mailer->send($message);
		} catch (Swift_SwiftException $e) {
		}
	}

	public function testCreateMessage() {
		$this->config
			->expects($this->any())
			->method('getSystemValue')
			->with('mail_send_plaintext_only', false)
			->willReturn(false);
		$this->assertInstanceOf('\OC\Mail\Message', $this->mailer->createMessage());
	}

	
	public function testSendInvalidMailException() {
		$this->expectException(\Exception::class);

		$message = $this->getMockBuilder('\OC\Mail\Message')
			->disableOriginalConstructor()->getMock();
		$message->expects($this->once())
			->method('getSwiftMessage')
			->willReturn(new \Swift_Message());

		$this->mailer->send($message);
	}

	/**
	 * @return array
	 */
	public function mailAddressProvider() {
		return [
			['lukas@owncloud.com', true],
			['lukas@localhost', true],
			['lukas@192.168.1.1', true],
			['lukas@éxämplè.com', true],
			['asdf', false],
			['lukas@owncloud.org@owncloud.com', false],
		];
	}

	/**
	 * @dataProvider mailAddressProvider
	 */
	public function testValidateMailAddress($email, $expected) {
		$this->assertSame($expected, $this->mailer->validateMailAddress($email));
	}

	public function testCreateEMailTemplate() {
		$this->config->method('getSystemValue')
			->with('mail_template_class', '')
			->willReturnArgument(1);

		$this->assertSame(EMailTemplate::class, get_class($this->mailer->createEMailTemplate('tests.MailerTest')));
	}

	public function testStreamingOptions() {
		$this->config->method('getSystemValue')
			->willReturnMap([
				['mail_smtpmode', 'smtp', 'smtp'],
				['mail_smtpstreamoptions', [], ['foo' => 1]]
			]);
		$mailer = self::invokePrivate($this->mailer, 'getInstance');
		$this->assertEquals(1, count($mailer->getTransport()->getStreamOptions()));
		$this->assertTrue(isset($mailer->getTransport()->getStreamOptions()['foo']));

	}

	public function testStreamingOptionsWrongType() {
		$this->config->method('getSystemValue')
			->willReturnMap([
				['mail_smtpmode', 'smtp', 'smtp'],
				['mail_smtpstreamoptions', [], 'bar']
			]);
		$mailer = self::invokePrivate($this->mailer, 'getInstance');
		$this->assertEquals(0, count($mailer->getTransport()->getStreamOptions()));
	}
}
