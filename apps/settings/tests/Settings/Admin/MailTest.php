<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Settings\Tests\Settings\Admin;

use OCA\Settings\Settings\Admin\Mail;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use Test\TestCase;

class MailTest extends TestCase {
	/** @var Mail */
	private $admin;
	/** @var IConfig */
	private $config;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->getMockBuilder(IConfig::class)->getMock();

		$this->admin = new Mail(
			$this->config
		);
	}

	public function testGetForm() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('mail_domain', '')
			->willReturn('mx.nextcloud.com');
		$this->config
			->expects($this->at(1))
			->method('getSystemValue')
			->with('mail_from_address', '')
			->willReturn('no-reply@nextcloud.com');
		$this->config
			->expects($this->at(2))
			->method('getSystemValue')
			->with('mail_smtpmode', '')
			->willReturn('smtp');
		$this->config
			->expects($this->at(3))
			->method('getSystemValue')
			->with('mail_smtpsecure', '')
			->willReturn(true);
		$this->config
			->expects($this->at(4))
			->method('getSystemValue')
			->with('mail_smtphost', '')
			->willReturn('smtp.nextcloud.com');
		$this->config
			->expects($this->at(5))
			->method('getSystemValue')
			->with('mail_smtpport', '')
			->willReturn(25);
		$this->config
			->expects($this->at(6))
			->method('getSystemValue')
			->with('mail_smtpauthtype', '')
			->willReturn('login');
		$this->config
			->expects($this->at(7))
			->method('getSystemValue')
			->with('mail_smtpauth', false)
			->willReturn(true);
		$this->config
			->expects($this->at(8))
			->method('getSystemValue')
			->with('mail_smtpname', '')
			->willReturn('smtp.sender.com');
		$this->config
			->expects($this->at(9))
			->method('getSystemValue')
			->with('mail_smtppassword', '')
			->willReturn('mypassword');
		$this->config
			->expects($this->at(10))
			->method('getSystemValue')
			->with('mail_sendmailmode', 'smtp')
			->willReturn('smtp');

		$expected = new TemplateResponse(
			'settings',
			'settings/admin/additional-mail',
			[
				'sendmail_is_available' => (bool) \OC_Helper::findBinaryPath('sendmail'),
				'mail_domain'           => 'mx.nextcloud.com',
				'mail_from_address'     => 'no-reply@nextcloud.com',
				'mail_smtpmode'         => 'smtp',
				'mail_smtpsecure'       => true,
				'mail_smtphost'         => 'smtp.nextcloud.com',
				'mail_smtpport'         => 25,
				'mail_smtpauthtype'     => 'login',
				'mail_smtpauth'         => true,
				'mail_smtpname'         => 'smtp.sender.com',
				'mail_smtppassword'     => '********',
				'mail_sendmailmode'		=> 'smtp',
			],
			''
		);

		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetSection() {
		$this->assertSame('server', $this->admin->getSection());
	}

	public function testGetPriority() {
		$this->assertSame(10, $this->admin->getPriority());
	}
}
