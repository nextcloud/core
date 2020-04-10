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
use OCP\Support\Subscription\ISubscription;
use OCP\Support\Subscription\ISupportedApps;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class RegistryTest extends TestCase {

	/** @var Registry */
	private $registry;

	/** @var MockObject|IConfig */
	private $config;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->registry = new Registry($this->config);
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
		/* @var ISubscription|\PHPUnit_Framework_MockObject_MockObject $subscription */
		$subscription = $this->createMock(ISubscription::class);
		$subscription->expects($this->once())
			->method('hasValidSubscription')
			->willReturn(true);
		$this->registry->register($subscription);

		$this->assertSame(true, $this->registry->delegateHasValidSubscription());
	}

	public function testDelegateHasValidSubscriptionConfig() {
		/* @var ISubscription|\PHPUnit_Framework_MockObject_MockObject $subscription */
		$this->config->expects($this->once())
			->method('getSystemValueBool')
			->with('has_valid_subscription')
			->willReturn(true);

		$this->assertSame(true, $this->registry->delegateHasValidSubscription());
	}

	public function testDelegateHasExtendedSupport() {
		/* @var ISubscription|\PHPUnit_Framework_MockObject_MockObject $subscription */
		$subscription = $this->createMock(ISubscription::class);
		$subscription->expects($this->once())
			->method('hasExtendedSupport')
			->willReturn(true);
		$this->registry->register($subscription);

		$this->assertSame(true, $this->registry->delegateHasExtendedSupport());
	}


	public function testDelegateGetSupportedApps() {
		/* @var ISupportedApps|\PHPUnit_Framework_MockObject_MockObject $subscription */
		$subscription = $this->createMock(ISupportedApps::class);
		$subscription->expects($this->once())
			->method('getSupportedApps')
			->willReturn(['abc']);
		$this->registry->register($subscription);

		$this->assertSame(['abc'], $this->registry->delegateGetSupportedApps());
	}
}
