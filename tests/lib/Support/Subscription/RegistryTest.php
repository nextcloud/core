<?php

/**
 * @author Morris Jobke <hey@morrisjobke.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Support\Subscription;

use OC\Support\Subscription\Registry;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IServerContainer;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OCP\Support\Subscription\ISubscription;
use OCP\Support\Subscription\ISupportedApps;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class RegistryTest extends TestCase {

	/** @var Registry */
	private $registry;

	/** @var MockObject|IConfig */
	private $config;

	/** @var MockObject|IServerContainer */
	private $serverContainer;

	/** @var MockObject|IUserManager */
	private $userManager;

	/** @var MockObject|IGroupManager */
	private $groupManager;

	/** @var MockObject|LoggerInterface */
	private $logger;

	/** @var MockObject|IManager */
	private $notificationManager;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->serverContainer = $this->createMock(IServerContainer::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->registry = new Registry(
			$this->config,
			$this->serverContainer,
			$this->userManager,
			$this->groupManager,
			$this->logger,
			$this->notificationManager
		);
	}

	/**
	 * Doesn't assert anything, just checks whether anything "explodes"
	 */
	public function testDelegateToNone() {
		$this->registry->delegateHasValidSubscription();
		$this->addToAssertionCount(1);
	}


	public function testDoubleRegistration() {
		$this->expectException(\OCP\Support\Subscription\Exception\AlreadyRegisteredException::class);

		/* @var ISubscription $subscription1 */
		$subscription1 = $this->createMock(ISubscription::class);
		/* @var ISubscription $subscription2 */
		$subscription2 = $this->createMock(ISubscription::class);
		$this->registry->register($subscription1);
		$this->registry->register($subscription2);
	}

	public function testNoSupportApp() {
		$this->assertSame([], $this->registry->delegateGetSupportedApps());
		$this->assertSame(false, $this->registry->delegateHasValidSubscription());
	}

	public function testDelegateHasValidSubscription() {
		/* @var ISubscription|\PHPUnit\Framework\MockObject\MockObject $subscription */
		$subscription = $this->createMock(ISubscription::class);
		$subscription->expects($this->once())
			->method('hasValidSubscription')
			->willReturn(true);
		$this->registry->register($subscription);

		$this->assertSame(true, $this->registry->delegateHasValidSubscription());
	}

	public function testDelegateHasValidSubscriptionConfig() {
		/* @var ISubscription|\PHPUnit\Framework\MockObject\MockObject $subscription */
		$this->config->expects($this->once())
			->method('getSystemValueBool')
			->with('has_valid_subscription')
			->willReturn(true);

		$this->assertSame(true, $this->registry->delegateHasValidSubscription());
	}

	public function testDelegateHasExtendedSupport() {
		/* @var ISubscription|\PHPUnit\Framework\MockObject\MockObject $subscription */
		$subscription = $this->createMock(ISubscription::class);
		$subscription->expects($this->once())
			->method('hasExtendedSupport')
			->willReturn(true);
		$this->registry->register($subscription);

		$this->assertSame(true, $this->registry->delegateHasExtendedSupport());
	}


	public function testDelegateGetSupportedApps() {
		/* @var ISupportedApps|\PHPUnit\Framework\MockObject\MockObject $subscription */
		$subscription = $this->createMock(ISupportedApps::class);
		$subscription->expects($this->once())
			->method('getSupportedApps')
			->willReturn(['abc']);
		$this->registry->register($subscription);

		$this->assertSame(['abc'], $this->registry->delegateGetSupportedApps());
	}

	public function testSubscriptionService() {
		$this->serverContainer->method('query')
			->with(DummySubscription::class)
			->willReturn(new DummySubscription(true, false, false));
		$this->registry->registerService(DummySubscription::class);

		$this->assertTrue($this->registry->delegateHasValidSubscription());
		$this->assertFalse($this->registry->delegateHasExtendedSupport());
	}

	public function testDelegateIsHardUserLimitReached() {
		/* @var ISubscription|\PHPUnit\Framework\MockObject\MockObject $subscription */
		$subscription = $this->createMock(ISubscription::class);
		$subscription->expects($this->once())
			->method('hasValidSubscription')
			->willReturn(true);
		$subscription->expects($this->once())
			->method('isHardUserLimitReached')
			->willReturn(true);
		$this->registry->register($subscription);
		$dummyGroup = $this->createMock(IGroup::class);
		$dummyGroup->expects($this->once())
			->method('getUsers')
			->willReturn([]);
		$this->groupManager->expects($this->once())
			->method('get')
			->willReturn($dummyGroup);

		$this->assertSame(true, $this->registry->delegateIsHardUserLimitReached());
	}

	public function testDelegateIsHardUserLimitReachedWithoutSupportApp() {
		$this->config->expects($this->once())
			->method('getSystemValueBool')
			->with('one-click-instance')
			->willReturn(false);

		$this->assertSame(false, $this->registry->delegateIsHardUserLimitReached());
	}

	public function dataForUserLimitCheck() {
		return [
			// $userLimit, $userCount, $disabledUsers, $expectedResult
			[35, 15, 2, false],
			[35, 45, 15, false],
			[35, 45, 5, true],
			[35, 45, 55, false],
		];
	}

	/**
	 * @dataProvider dataForUserLimitCheck
	 */
	public function testDelegateIsHardUserLimitReachedWithoutSupportAppAndUserCount($userLimit, $userCount, $disabledUsers, $expectedResult) {
		$this->config->expects($this->once())
			->method('getSystemValueBool')
			->with('one-click-instance')
			->willReturn(true);
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('one-click-instance.user-limit')
			->willReturn($userLimit);
		$this->config->expects($this->once())
			->method('getUsersForUserValue')
			->with('core', 'enabled', 'false')
			->willReturn(array_fill(0, $disabledUsers, ''));
		/* @var UserInterface|\PHPUnit\Framework\MockObject\MockObject $dummyBackend */
		$dummyBackend = $this->createMock(UserInterface::class);
		$dummyBackend->expects($this->once())
			->method('implementsActions')
			->willReturn(true);
		$dummyBackend->expects($this->once())
			->method('countUsers')
			->willReturn($userCount);
		$this->userManager->expects($this->once())
			->method('getBackends')
			->willReturn([$dummyBackend]);

		if ($expectedResult) {
			$dummyGroup = $this->createMock(IGroup::class);
			$dummyGroup->expects($this->once())
				->method('getUsers')
				->willReturn([]);
			$this->groupManager->expects($this->once())
				->method('get')
				->willReturn($dummyGroup);
		}

		$this->assertSame($expectedResult, $this->registry->delegateIsHardUserLimitReached());
	}
}
