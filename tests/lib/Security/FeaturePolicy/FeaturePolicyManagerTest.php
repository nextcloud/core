<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Security\CSP;

use OC\Security\FeaturePolicy\FeaturePolicyManager;
use OCP\AppFramework\Http\FeaturePolicy;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Security\FeaturePolicy\AddFeaturePolicyEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

class FeaturePolicyManagerTest extends TestCase {
	/** @var EventDispatcherInterface */
	private $dispatcher;

	/** @var FeaturePolicyManager */
	private $manager;

	protected function setUp(): void {
		parent::setUp();
		$this->dispatcher = \OC::$server->query(IEventDispatcher::class);
		$this->manager = new FeaturePolicyManager($this->dispatcher);
	}

	public function testAddDefaultPolicy() {
		$this->manager->addDefaultPolicy(new FeaturePolicy());
		$this->addToAssertionCount(1);
	}

	public function testGetDefaultPolicyWithPoliciesViaEvent() {
		$this->dispatcher->addListener(AddFeaturePolicyEvent::class, function (AddFeaturePolicyEvent $e) {
			$policy = new FeaturePolicy();
			$policy->addAllowedMicrophoneDomain('mydomain.com');
			$policy->addAllowedPaymentDomain('mypaymentdomain.com');

			$e->addPolicy($policy);
		});

		$this->dispatcher->addListener(AddFeaturePolicyEvent::class, function (AddFeaturePolicyEvent $e) {
			$policy = new FeaturePolicy();
			$policy->addAllowedPaymentDomain('mydomainother.com');
			$policy->addAllowedGeoLocationDomain('mylocation.here');

			$e->addPolicy($policy);
		});

		$this->dispatcher->addListener(AddFeaturePolicyEvent::class, function (AddFeaturePolicyEvent $e) {
			$policy = new FeaturePolicy();
			$policy->addAllowedAutoplayDomain('youtube.com');

			$e->addPolicy($policy);
		});

		$expected = new \OC\Security\FeaturePolicy\FeaturePolicy();
		$expected->addAllowedMicrophoneDomain('mydomain.com');
		$expected->addAllowedPaymentDomain('mypaymentdomain.com');
		$expected->addAllowedPaymentDomain('mydomainother.com');
		$expected->addAllowedGeoLocationDomain('mylocation.here');
		$expected->addAllowedAutoplayDomain('youtube.com');

		$expectedStringPolicy = "autoplay 'self' youtube.com;camera 'none';fullscreen 'self';geolocation mylocation.here;microphone mydomain.com;payment mypaymentdomain.com mydomainother.com";

		$this->assertEquals($expected, $this->manager->getDefaultPolicy());
		$this->assertSame($expectedStringPolicy, $this->manager->getDefaultPolicy()->buildPolicy());
	}

}
