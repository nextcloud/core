<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\TwoFactorBackupCodes\Tests\Unit\Notification;

use OCA\TwoFactorBackupCodes\Notifications\Notifier;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class NotifierTest extends TestCase {
	/** @var Notifier */
	protected $notifier;

	/** @var IFactory|MockObject */
	protected $factory;
	/** @var IURLGenerator|MockObject */
	protected $url;
	/** @var IL10N|MockObject */
	protected $l;

	protected function setUp(): void {
		parent::setUp();

		$this->l = $this->createMock(IL10N::class);
		$this->l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($string, $args) {
				return vsprintf($string, $args);
			});
		$this->factory = $this->createMock(IFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->factory->expects($this->any())
			->method('get')
			->willReturn($this->l);

		$this->notifier = new Notifier(
			$this->factory,
			$this->url
		);
	}

	
	public function testPrepareWrongApp() {
		$this->expectException(\InvalidArgumentException::class);

		/** @var INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
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

		/** @var INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('getApp')
			->willReturn('twofactor_backupcodes');
		$notification->expects($this->once())
			->method('getSubject')
			->willReturn('wrong subject');

		$this->notifier->prepare($notification, 'en');
	}

	public function testPrepare() {
		/** @var \OCP\Notification\INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->createMock(INotification::class);

		$notification->expects($this->once())
			->method('getApp')
			->willReturn('twofactor_backupcodes');
		$notification->expects($this->once())
			->method('getSubject')
			->willReturn('create_backupcodes');

		$this->factory->expects($this->once())
			->method('get')
			->with('twofactor_backupcodes', 'nl')
			->willReturn($this->l);

		$notification->expects($this->once())
			->method('setParsedSubject')
			->with('Generate backup codes')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setParsedMessage')
			->with('You enabled two-factor authentication but did not generate backup codes yet. They are needed to restore access to your account in case you lose your second factor.')
			->willReturnSelf();

		$this->url->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('settings.PersonalSettings.index', ['section' => 'security'])
			->willReturn('linkToRouteAbsolute');
		$notification->expects($this->once())
			->method('setLink')
			->with('linkToRouteAbsolute')
			->willReturnSelf();

		$return = $this->notifier->prepare($notification, 'nl');
		$this->assertEquals($notification, $return);
	}
}
