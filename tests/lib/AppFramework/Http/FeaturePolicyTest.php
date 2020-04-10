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

namespace Test\AppFramework\Http;

use OCP\AppFramework\Http\FeaturePolicy;

class FeaturePolicyTest extends \Test\TestCase {

	/** @var EmptyFeaturePolicy */
	private $policy;

	protected function setUp(): void {
		parent::setUp();
		$this->policy = new FeaturePolicy();
	}

	public function testGetPolicyDefault() {
		$defaultPolicy = "autoplay 'self';camera 'none';fullscreen 'self';geolocation 'none';microphone 'none';payment 'none'";
		$this->assertSame($defaultPolicy, $this->policy->buildPolicy());
	}

	public function testGetPolicyAutoplayDomainValid() {
		$expectedPolicy = "autoplay 'self' www.nextcloud.com;camera 'none';fullscreen 'self';geolocation 'none';microphone 'none';payment 'none'";

		$this->policy->addAllowedAutoplayDomain('www.nextcloud.com');
		$this->assertSame($expectedPolicy, $this->policy->buildPolicy());
	}

	public function testGetPolicyAutoplayDomainValidMultiple() {
		$expectedPolicy = "autoplay 'self' www.nextcloud.com www.nextcloud.org;camera 'none';fullscreen 'self';geolocation 'none';microphone 'none';payment 'none'";

		$this->policy->addAllowedAutoplayDomain('www.nextcloud.com');
		$this->policy->addAllowedAutoplayDomain('www.nextcloud.org');
		$this->assertSame($expectedPolicy, $this->policy->buildPolicy());
	}

	public function testGetPolicyCameraDomainValid() {
		$expectedPolicy = "autoplay 'self';camera www.nextcloud.com;fullscreen 'self';geolocation 'none';microphone 'none';payment 'none'";

		$this->policy->addAllowedCameraDomain('www.nextcloud.com');
		$this->assertSame($expectedPolicy, $this->policy->buildPolicy());
	}

	public function testGetPolicyCameraDomainValidMultiple() {
		$expectedPolicy = "autoplay 'self';camera www.nextcloud.com www.nextcloud.org;fullscreen 'self';geolocation 'none';microphone 'none';payment 'none'";

		$this->policy->addAllowedCameraDomain('www.nextcloud.com');
		$this->policy->addAllowedCameraDomain('www.nextcloud.org');
		$this->assertSame($expectedPolicy, $this->policy->buildPolicy());
	}

	public function testGetPolicyFullScreenDomainValid() {
		$expectedPolicy = "autoplay 'self';camera 'none';fullscreen 'self' www.nextcloud.com;geolocation 'none';microphone 'none';payment 'none'";

		$this->policy->addAllowedFullScreenDomain('www.nextcloud.com');
		$this->assertSame($expectedPolicy, $this->policy->buildPolicy());
	}

	public function testGetPolicyFullScreenDomainValidMultiple() {
		$expectedPolicy = "autoplay 'self';camera 'none';fullscreen 'self' www.nextcloud.com www.nextcloud.org;geolocation 'none';microphone 'none';payment 'none'";

		$this->policy->addAllowedFullScreenDomain('www.nextcloud.com');
		$this->policy->addAllowedFullScreenDomain('www.nextcloud.org');
		$this->assertSame($expectedPolicy, $this->policy->buildPolicy());
	}

	public function testGetPolicyGeoLocationDomainValid() {
		$expectedPolicy = "autoplay 'self';camera 'none';fullscreen 'self';geolocation www.nextcloud.com;microphone 'none';payment 'none'";

		$this->policy->addAllowedGeoLocationDomain('www.nextcloud.com');
		$this->assertSame($expectedPolicy, $this->policy->buildPolicy());
	}

	public function testGetPolicyGeoLocationDomainValidMultiple() {
		$expectedPolicy = "autoplay 'self';camera 'none';fullscreen 'self';geolocation www.nextcloud.com www.nextcloud.org;microphone 'none';payment 'none'";

		$this->policy->addAllowedGeoLocationDomain('www.nextcloud.com');
		$this->policy->addAllowedGeoLocationDomain('www.nextcloud.org');
		$this->assertSame($expectedPolicy, $this->policy->buildPolicy());
	}

	public function testGetPolicyMicrophoneDomainValid() {
		$expectedPolicy = "autoplay 'self';camera 'none';fullscreen 'self';geolocation 'none';microphone www.nextcloud.com;payment 'none'";

		$this->policy->addAllowedMicrophoneDomain('www.nextcloud.com');
		$this->assertSame($expectedPolicy, $this->policy->buildPolicy());
	}

	public function testGetPolicyMicrophoneDomainValidMultiple() {
		$expectedPolicy = "autoplay 'self';camera 'none';fullscreen 'self';geolocation 'none';microphone www.nextcloud.com www.nextcloud.org;payment 'none'";

		$this->policy->addAllowedMicrophoneDomain('www.nextcloud.com');
		$this->policy->addAllowedMicrophoneDomain('www.nextcloud.org');
		$this->assertSame($expectedPolicy, $this->policy->buildPolicy());
	}

	public function testGetPolicyPaymentDomainValid() {
		$expectedPolicy = "autoplay 'self';camera 'none';fullscreen 'self';geolocation 'none';microphone 'none';payment www.nextcloud.com";

		$this->policy->addAllowedPaymentDomain('www.nextcloud.com');
		$this->assertSame($expectedPolicy, $this->policy->buildPolicy());
	}

	public function testGetPolicyPaymentDomainValidMultiple() {
		$expectedPolicy = "autoplay 'self';camera 'none';fullscreen 'self';geolocation 'none';microphone 'none';payment www.nextcloud.com www.nextcloud.org";

		$this->policy->addAllowedPaymentDomain('www.nextcloud.com');
		$this->policy->addAllowedPaymentDomain('www.nextcloud.org');
		$this->assertSame($expectedPolicy, $this->policy->buildPolicy());
	}
}
