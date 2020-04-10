<?php
/**
 * Copyright (c) 2014 Lukas Reschke <lukas@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Mail;

use OC\Mail\Message;
use OCP\Mail\IEMailTemplate;
use Swift_Message;
use Test\TestCase;

class MessageTest extends TestCase {
	/** @var Swift_Message */
	private $swiftMessage;
	/** @var Message */
	private $message;

	/**
	 * @return array
	 */
	public function mailAddressProvider() {
		return [
			[['lukas@owncloud.com' => 'Lukas Reschke'], ['lukas@owncloud.com' => 'Lukas Reschke']],
			[['lukas@owncloud.com' => 'Lukas Reschke', 'lukas@öwnclöüd.com', 'lukäs@owncloud.örg' => 'Lükäs Réschke'],
				['lukas@owncloud.com' => 'Lukas Reschke', 'lukas@xn--wncld-iuae2c.com', 'lukäs@owncloud.xn--rg-eka' => 'Lükäs Réschke']],
			[['lukas@öwnclöüd.com'], ['lukas@xn--wncld-iuae2c.com']],
		];
	}

	/**
	 * @return array
	 */
	public function getMailAddressProvider() {
		return [
			[null, []],
			[['lukas@owncloud.com' => 'Lukas Reschke'], ['lukas@owncloud.com' => 'Lukas Reschke']],
		];
	}

	protected function setUp(): void {
		parent::setUp();

		$this->swiftMessage = $this->getMockBuilder('\Swift_Message')
			->disableOriginalConstructor()->getMock();

		$this->message = new Message($this->swiftMessage, false);
	}

	/**
	 * @requires function idn_to_ascii
	 * @dataProvider mailAddressProvider
	 *
	 * @param string $unconverted
	 * @param string $expected
	 */
	public function testConvertAddresses($unconverted, $expected) {
		$this->assertSame($expected, self::invokePrivate($this->message, 'convertAddresses', [$unconverted]));
	}

	public function testSetFrom() {
		$this->swiftMessage
			->expects($this->once())
			->method('setFrom')
			->with(['lukas@owncloud.com']);
		$this->message->setFrom(['lukas@owncloud.com']);
	}


	/**
	 * @dataProvider getMailAddressProvider
	 *
	 * @param $swiftresult
	 * @param $return
	 */
	public function testGetFrom($swiftresult, $return) {
		$this->swiftMessage
			->expects($this->once())
			->method('getFrom')
			->willReturn($swiftresult);

		$this->assertSame($return, $this->message->getFrom());
	}

	public function testSetReplyTo() {
		$this->swiftMessage
			->expects($this->once())
			->method('setReplyTo')
			->with(['lukas@owncloud.com']);
		$this->message->setReplyTo(['lukas@owncloud.com']);
	}

	public function testGetReplyTo() {
		$this->swiftMessage
			->expects($this->once())
			->method('getReplyTo')
			->willReturn('lukas@owncloud.com');

		$this->assertSame('lukas@owncloud.com', $this->message->getReplyTo());
	}

	public function testSetTo() {
		$this->swiftMessage
			->expects($this->once())
			->method('setTo')
			->with(['lukas@owncloud.com']);
		$this->message->setTo(['lukas@owncloud.com']);
	}

	/**
	 * @dataProvider  getMailAddressProvider
	 */
	public function testGetTo($swiftresult,$return) {
		$this->swiftMessage
			->expects($this->once())
			->method('getTo')
			->willReturn($swiftresult);

		$this->assertSame($return, $this->message->getTo());
	}

	public function testSetCc() {
		$this->swiftMessage
			->expects($this->once())
			->method('setCc')
			->with(['lukas@owncloud.com']);
		$this->message->setCc(['lukas@owncloud.com']);
	}

	/**
	 * @dataProvider  getMailAddressProvider
	 */
	public function testGetCc($swiftresult,$return) {
		$this->swiftMessage
			->expects($this->once())
			->method('getCc')
			->willReturn($swiftresult);

		$this->assertSame($return, $this->message->getCc());
	}

	public function testSetBcc() {
		$this->swiftMessage
			->expects($this->once())
			->method('setBcc')
			->with(['lukas@owncloud.com']);
		$this->message->setBcc(['lukas@owncloud.com']);
	}

	/**
	 * @dataProvider  getMailAddressProvider
	 */
	public function testGetBcc($swiftresult,$return) {
		$this->swiftMessage
			->expects($this->once())
			->method('getBcc')
			->willReturn($swiftresult);

		$this->assertSame($return, $this->message->getBcc());
	}

	public function testSetSubject() {
		$this->swiftMessage
			->expects($this->once())
			->method('setSubject')
			->with('Fancy Subject');

		$this->message->setSubject('Fancy Subject');
	}

	public function testGetSubject() {
		$this->swiftMessage
			->expects($this->once())
			->method('getSubject')
			->willReturn('Fancy Subject');

		$this->assertSame('Fancy Subject', $this->message->getSubject());
	}

	public function testSetPlainBody() {
		$this->swiftMessage
			->expects($this->once())
			->method('setBody')
			->with('Fancy Body');

		$this->message->setPlainBody('Fancy Body');
	}

	public function testGetPlainBody() {
		$this->swiftMessage
			->expects($this->once())
			->method('getBody')
			->willReturn('Fancy Body');

		$this->assertSame('Fancy Body', $this->message->getPlainBody());
	}

	public function testSetHtmlBody() {
		$this->swiftMessage
			->expects($this->once())
			->method('addPart')
			->with('<blink>Fancy Body</blink>', 'text/html');

		$this->message->setHtmlBody('<blink>Fancy Body</blink>');
	}

	public function testPlainTextRenderOption() {
		/** @var \PHPUnit_Framework_MockObject_MockObject|Swift_Message $swiftMessage */
		$swiftMessage = $this->getMockBuilder('\Swift_Message')
			->disableOriginalConstructor()->getMock();
		/** @var \PHPUnit_Framework_MockObject_MockObject|IEMailTemplate $template */
		$template = $this->getMockBuilder('\OCP\Mail\IEMailTemplate')
			->disableOriginalConstructor()->getMock();

		$message = new Message($swiftMessage, true);

		$template
			->expects($this->never())
			->method('renderHTML');
		$template
			->expects($this->once())
			->method('renderText');
		$template
			->expects($this->once())
			->method('renderSubject');

		$message->useTemplate($template);
	}

	public function testBothRenderingOptions() {
		/** @var \PHPUnit_Framework_MockObject_MockObject|Swift_Message $swiftMessage */
		$swiftMessage = $this->getMockBuilder('\Swift_Message')
			->disableOriginalConstructor()->getMock();
		/** @var \PHPUnit_Framework_MockObject_MockObject|IEMailTemplate $template */
		$template = $this->getMockBuilder('\OCP\Mail\IEMailTemplate')
			->disableOriginalConstructor()->getMock();

		$message = new Message($swiftMessage, false);

		$template
			->expects($this->once())
			->method('renderHTML');
		$template
			->expects($this->once())
			->method('renderText');
		$template
			->expects($this->once())
			->method('renderSubject');

		$message->useTemplate($template);
	}

}
