<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\UpdateNotification\Tests\Notification;

use OCA\UpdateNotification\Notification\Notifier;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Test\TestCase;

class NotifierTest extends TestCase {

	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	protected $urlGenerator;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;
	/** @var IManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $notificationManager;
	/** @var IFactory|\PHPUnit\Framework\MockObject\MockObject */
	protected $l10nFactory;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	protected $userSession;
	/** @var IGroupManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $groupManager;

	protected function setUp(): void {
		parent::setUp();

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->config = $this->createMock(IConfig::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
	}

	/**
	 * @param array $methods
	 * @return Notifier|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected function getNotifier(array $methods = []) {
		if (empty($methods)) {
			return new Notifier(
				$this->urlGenerator,
				$this->config,
				$this->notificationManager,
				$this->l10nFactory,
				$this->userSession,
				$this->groupManager
			);
		}
		{
			return $this->getMockBuilder(Notifier::class)
				->setConstructorArgs([
					$this->urlGenerator,
					$this->config,
					$this->notificationManager,
					$this->l10nFactory,
					$this->userSession,
					$this->groupManager,
				])
				->setMethods($methods)
				->getMock();
		}
	}

	public function dataUpdateAlreadyInstalledCheck(): array {
		return [
			['1.1.0', '1.0.0', false],
			['1.1.0', '1.1.0', true],
			['1.1.0', '1.2.0', true],
		];
	}

	/**
	 * @dataProvider dataUpdateAlreadyInstalledCheck
	 *
	 * @param string $versionNotification
	 * @param string $versionInstalled
	 * @param bool $exception
	 */
	public function testUpdateAlreadyInstalledCheck(string $versionNotification, string $versionInstalled, bool $exception) {
		$notifier = $this->getNotifier();

		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('getObjectId')
			->willReturn($versionNotification);

		try {
			self::invokePrivate($notifier, 'updateAlreadyInstalledCheck', [$notification, $versionInstalled]);
			$this->assertFalse($exception);
		} catch (\Exception $e) {
			$this->assertTrue($exception);
			$this->assertInstanceOf(AlreadyProcessedException::class, $e);
		}
	}
}
