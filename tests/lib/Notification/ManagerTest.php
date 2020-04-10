<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\Notification;

use OC\Notification\Manager;
use OCP\ILogger;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\RichObjectStrings\IValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ManagerTest extends TestCase {
	/** @var IManager */
	protected $manager;

	/** @var IValidator|MockObject */
	protected $validator;
	/** @var ILogger|MockObject */
	protected $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = $this->createMock(IValidator::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->manager = new Manager($this->validator, $this->logger);
	}

	public function testRegisterApp() {

		$this->assertEquals([], self::invokePrivate($this->manager, 'getApps'));

		$this->manager->registerApp(DummyApp::class);

		$this->assertCount(1, self::invokePrivate($this->manager, 'getApps'));
		$this->assertCount(1, self::invokePrivate($this->manager, 'getApps'));

		$this->manager->registerApp(DummyApp::class);

		$this->assertCount(2, self::invokePrivate($this->manager, 'getApps'));
	}

	public function testRegisterAppInvalid() {
		$this->manager->registerApp(DummyNotifier::class);

		$this->logger->expects($this->once())
			->method('error');
		self::invokePrivate($this->manager, 'getApps');
	}

	public function testRegisterNotifier() {
		$this->assertEquals([], self::invokePrivate($this->manager, 'getNotifiers'));

		$this->manager->registerNotifierService(DummyNotifier::class);

		$this->assertCount(1, self::invokePrivate($this->manager, 'getNotifiers'));
		$this->assertCount(1, self::invokePrivate($this->manager, 'getNotifiers'));

		$this->manager->registerNotifierService(DummyNotifier::class);

		$this->assertCount(2, self::invokePrivate($this->manager, 'getNotifiers'));
	}

	public function testRegisterNotifierInvalid() {
		$this->manager->registerNotifierService(DummyApp::class);

		$this->logger->expects($this->once())
			->method('error');
		self::invokePrivate($this->manager, 'getNotifiers');
	}

	public function testCreateNotification() {
		$action = $this->manager->createNotification();
		$this->assertInstanceOf('OCP\Notification\INotification', $action);
	}

	public function testNotify() {
		/** @var \OCP\Notification\INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();
		$notification->expects($this->once())
			->method('isValid')
			->willReturn(true);

		$manager = $this->getMockBuilder(Manager::class)
			->setConstructorArgs([
				$this->validator,
				$this->logger,
			])
			->setMethods(['getApps'])
			->getMock();

		$manager->expects($this->once())
			->method('getApps')
			->willReturn([]);

		$manager->notify($notification);
	}

	
	public function testNotifyInvalid() {
		$this->expectException(\InvalidArgumentException::class);

		/** @var \OCP\Notification\INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();
		$notification->expects($this->once())
			->method('isValid')
			->willReturn(false);

		$manager = $this->getMockBuilder(Manager::class)
			->setConstructorArgs([
				$this->validator,
				$this->logger,
			])
			->setMethods(['getApps'])
			->getMock();

		$manager->expects($this->never())
			->method('getApps');

		$manager->notify($notification);
	}

	public function testMarkProcessed() {
		/** @var \OCP\Notification\INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();

		$manager = $this->getMockBuilder(Manager::class)
			->setConstructorArgs([
				$this->validator,
				$this->logger,
			])
			->setMethods(['getApps'])
			->getMock();

		$manager->expects($this->once())
			->method('getApps')
			->willReturn([]);

		$manager->markProcessed($notification);
	}

	public function testGetCount() {
		/** @var \OCP\Notification\INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();

		$manager = $this->getMockBuilder(Manager::class)
			->setConstructorArgs([
				$this->validator,
				$this->logger,
			])
			->setMethods(['getApps'])
			->getMock();

		$manager->expects($this->once())
			->method('getApps')
			->willReturn([]);

		$manager->getCount($notification);
	}
}
