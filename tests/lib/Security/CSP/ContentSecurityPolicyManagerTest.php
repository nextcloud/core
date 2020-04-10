<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

namespace Test\Security\CSP;

use OC\Security\CSP\ContentSecurityPolicyManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

class ContentSecurityPolicyManagerTest extends TestCase {
	/** @var EventDispatcherInterface */
	private $dispatcher;

	/** @var ContentSecurityPolicyManager */
	private $contentSecurityPolicyManager;

	protected function setUp(): void {
		parent::setUp();
		$this->dispatcher = \OC::$server->query(IEventDispatcher::class);
		$this->contentSecurityPolicyManager = new ContentSecurityPolicyManager($this->dispatcher);
	}

	public function testAddDefaultPolicy() {
		$this->contentSecurityPolicyManager->addDefaultPolicy(new \OCP\AppFramework\Http\ContentSecurityPolicy());
		$this->addToAssertionCount(1);
	}

	public function testGetDefaultPolicyWithPolicies() {
		$policy = new \OCP\AppFramework\Http\ContentSecurityPolicy();
		$policy->addAllowedFontDomain('mydomain.com');
		$policy->addAllowedImageDomain('anotherdomain.de');
		$this->contentSecurityPolicyManager->addDefaultPolicy($policy);
		$policy = new \OCP\AppFramework\Http\ContentSecurityPolicy();
		$policy->addAllowedFontDomain('example.com');
		$policy->addAllowedImageDomain('example.org');
		$policy->allowInlineScript(true);
		$policy->allowEvalScript(true);
		$this->contentSecurityPolicyManager->addDefaultPolicy($policy);
		$policy = new \OCP\AppFramework\Http\EmptyContentSecurityPolicy();
		$policy->addAllowedChildSrcDomain('childdomain');
		$policy->addAllowedFontDomain('anotherFontDomain');
		$policy->addAllowedFormActionDomain('thirdDomain');
		$this->contentSecurityPolicyManager->addDefaultPolicy($policy);

		$expected = new \OC\Security\CSP\ContentSecurityPolicy();
		$expected->allowInlineScript(true);
		$expected->allowEvalScript(true);
		$expected->addAllowedFontDomain('mydomain.com');
		$expected->addAllowedFontDomain('example.com');
		$expected->addAllowedFontDomain('anotherFontDomain');
		$expected->addAllowedFormActionDomain('thirdDomain');
		$expected->addAllowedImageDomain('anotherdomain.de');
		$expected->addAllowedImageDomain('example.org');
		$expected->addAllowedChildSrcDomain('childdomain');
		$expectedStringPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';script-src 'self' 'unsafe-inline' 'unsafe-eval';style-src 'self' 'unsafe-inline';img-src 'self' data: blob: anotherdomain.de example.org;font-src 'self' data: mydomain.com example.com anotherFontDomain;connect-src 'self';media-src 'self';child-src childdomain;frame-ancestors 'self';form-action 'self' thirdDomain";

		$this->assertEquals($expected, $this->contentSecurityPolicyManager->getDefaultPolicy());
		$this->assertSame($expectedStringPolicy, $this->contentSecurityPolicyManager->getDefaultPolicy()->buildPolicy());
	}

	public function testGetDefaultPolicyWithPoliciesViaEvent() {
		$this->dispatcher->addListener(AddContentSecurityPolicyEvent::class, function (AddContentSecurityPolicyEvent $e) {
			$policy = new \OCP\AppFramework\Http\ContentSecurityPolicy();
			$policy->addAllowedFontDomain('mydomain.com');
			$policy->addAllowedImageDomain('anotherdomain.de');

			$e->addPolicy($policy);
		});

		$this->dispatcher->addListener(AddContentSecurityPolicyEvent::class, function (AddContentSecurityPolicyEvent $e) {
			$policy = new \OCP\AppFramework\Http\ContentSecurityPolicy();
			$policy->addAllowedFontDomain('example.com');
			$policy->addAllowedImageDomain('example.org');
			$policy->allowInlineScript(true);
			$policy->allowEvalScript(true);
			$e->addPolicy($policy);
		});

		$this->dispatcher->addListener(AddContentSecurityPolicyEvent::class, function (AddContentSecurityPolicyEvent $e) {
			$policy = new \OCP\AppFramework\Http\EmptyContentSecurityPolicy();
			$policy->addAllowedChildSrcDomain('childdomain');
			$policy->addAllowedFontDomain('anotherFontDomain');
			$policy->addAllowedFormActionDomain('thirdDomain');
			$e->addPolicy($policy);
		});

		$expected = new \OC\Security\CSP\ContentSecurityPolicy();
		$expected->allowInlineScript(true);
		$expected->allowEvalScript(true);
		$expected->addAllowedFontDomain('mydomain.com');
		$expected->addAllowedFontDomain('example.com');
		$expected->addAllowedFontDomain('anotherFontDomain');
		$expected->addAllowedImageDomain('anotherdomain.de');
		$expected->addAllowedImageDomain('example.org');
		$expected->addAllowedChildSrcDomain('childdomain');
		$expected->addAllowedFormActionDomain('thirdDomain');
		$expectedStringPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';script-src 'self' 'unsafe-inline' 'unsafe-eval';style-src 'self' 'unsafe-inline';img-src 'self' data: blob: anotherdomain.de example.org;font-src 'self' data: mydomain.com example.com anotherFontDomain;connect-src 'self';media-src 'self';child-src childdomain;frame-ancestors 'self';form-action 'self' thirdDomain";

		$this->assertEquals($expected, $this->contentSecurityPolicyManager->getDefaultPolicy());
		$this->assertSame($expectedStringPolicy, $this->contentSecurityPolicyManager->getDefaultPolicy()->buildPolicy());
	}

}
