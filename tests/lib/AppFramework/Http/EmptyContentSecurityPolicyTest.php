<?php
/**
 * Copyright (c) 2015 Lukas Reschke lukas@owncloud.com
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\AppFramework\Http;

use OCP\AppFramework\Http\EmptyContentSecurityPolicy;

/**
 * Class ContentSecurityPolicyTest
 *
 * @package OC\AppFramework\Http
 */
class EmptyContentSecurityPolicyTest extends \Test\TestCase {

	/** @var EmptyContentSecurityPolicy */
	private $contentSecurityPolicy;

	protected function setUp(): void {
		parent::setUp();
		$this->contentSecurityPolicy = new EmptyContentSecurityPolicy();
	}

	public function testGetPolicyDefault() {
		$defaultPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";
		$this->assertSame($defaultPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyScriptDomainValid() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';script-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedScriptDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyScriptDomainValidMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';script-src www.owncloud.com www.owncloud.org";

		$this->contentSecurityPolicy->addAllowedScriptDomain('www.owncloud.com');
		$this->contentSecurityPolicy->addAllowedScriptDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowScriptDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedScriptDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowScriptDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowScriptDomainMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';script-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedScriptDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowScriptDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowScriptDomainMultipleStacked() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedScriptDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowScriptDomain('www.owncloud.org')->disallowScriptDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyScriptAllowInline() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';script-src  'unsafe-inline'";

		$this->contentSecurityPolicy->allowInlineScript(true);
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyScriptAllowInlineWithDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';script-src www.owncloud.com 'unsafe-inline'";

		$this->contentSecurityPolicy->addAllowedScriptDomain('www.owncloud.com');
		$this->contentSecurityPolicy->allowInlineScript(true);
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyScriptAllowInlineAndEval() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';script-src  'unsafe-inline' 'unsafe-eval'";

		$this->contentSecurityPolicy->allowInlineScript(true);
		$this->contentSecurityPolicy->allowEvalScript(true);
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyStyleDomainValid() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';style-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedStyleDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyStyleDomainValidMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';style-src www.owncloud.com www.owncloud.org";

		$this->contentSecurityPolicy->addAllowedStyleDomain('www.owncloud.com');
		$this->contentSecurityPolicy->addAllowedStyleDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowStyleDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedStyleDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowStyleDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowStyleDomainMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';style-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedStyleDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowStyleDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowStyleDomainMultipleStacked() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedStyleDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowStyleDomain('www.owncloud.org')->disallowStyleDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyStyleAllowInline() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';style-src  'unsafe-inline'";

		$this->contentSecurityPolicy->allowInlineStyle(true);
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyStyleAllowInlineWithDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';style-src www.owncloud.com 'unsafe-inline'";

		$this->contentSecurityPolicy->addAllowedStyleDomain('www.owncloud.com');
		$this->contentSecurityPolicy->allowInlineStyle(true);
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyStyleDisallowInline() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->allowInlineStyle(false);
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyImageDomainValid() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';img-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedImageDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyImageDomainValidMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';img-src www.owncloud.com www.owncloud.org";

		$this->contentSecurityPolicy->addAllowedImageDomain('www.owncloud.com');
		$this->contentSecurityPolicy->addAllowedImageDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowImageDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedImageDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowImageDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowImageDomainMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';img-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedImageDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowImageDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowImageDomainMultipleStakes() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedImageDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowImageDomain('www.owncloud.org')->disallowImageDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyFontDomainValid() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';font-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedFontDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyFontDomainValidMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';font-src www.owncloud.com www.owncloud.org";

		$this->contentSecurityPolicy->addAllowedFontDomain('www.owncloud.com');
		$this->contentSecurityPolicy->addAllowedFontDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowFontDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedFontDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowFontDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowFontDomainMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';font-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedFontDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowFontDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowFontDomainMultipleStakes() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedFontDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowFontDomain('www.owncloud.org')->disallowFontDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyConnectDomainValid() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';connect-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedConnectDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyConnectDomainValidMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';connect-src www.owncloud.com www.owncloud.org";

		$this->contentSecurityPolicy->addAllowedConnectDomain('www.owncloud.com');
		$this->contentSecurityPolicy->addAllowedConnectDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowConnectDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedConnectDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowConnectDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowConnectDomainMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';connect-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedConnectDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowConnectDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowConnectDomainMultipleStakes() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedConnectDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowConnectDomain('www.owncloud.org')->disallowConnectDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyMediaDomainValid() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';media-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedMediaDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyMediaDomainValidMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';media-src www.owncloud.com www.owncloud.org";

		$this->contentSecurityPolicy->addAllowedMediaDomain('www.owncloud.com');
		$this->contentSecurityPolicy->addAllowedMediaDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowMediaDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedMediaDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowMediaDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowMediaDomainMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';media-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedMediaDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowMediaDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowMediaDomainMultipleStakes() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedMediaDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowMediaDomain('www.owncloud.org')->disallowMediaDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyObjectDomainValid() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';object-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedObjectDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyObjectDomainValidMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';object-src www.owncloud.com www.owncloud.org";

		$this->contentSecurityPolicy->addAllowedObjectDomain('www.owncloud.com');
		$this->contentSecurityPolicy->addAllowedObjectDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowObjectDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedObjectDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowObjectDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowObjectDomainMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';object-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedObjectDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowObjectDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowObjectDomainMultipleStakes() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedObjectDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowObjectDomain('www.owncloud.org')->disallowObjectDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetAllowedFrameDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';frame-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedFrameDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyFrameDomainValidMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';frame-src www.owncloud.com www.owncloud.org";

		$this->contentSecurityPolicy->addAllowedFrameDomain('www.owncloud.com');
		$this->contentSecurityPolicy->addAllowedFrameDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowFrameDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedFrameDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowFrameDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowFrameDomainMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';frame-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedFrameDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowFrameDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowFrameDomainMultipleStakes() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedFrameDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowFrameDomain('www.owncloud.org')->disallowFrameDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetAllowedChildSrcDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';child-src child.owncloud.com";

		$this->contentSecurityPolicy->addAllowedChildSrcDomain('child.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyChildSrcValidMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';child-src child.owncloud.com child.owncloud.org";

		$this->contentSecurityPolicy->addAllowedChildSrcDomain('child.owncloud.com');
		$this->contentSecurityPolicy->addAllowedChildSrcDomain('child.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowChildSrcDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedChildSrcDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowChildSrcDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowChildSrcDomainMultiple() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';child-src www.owncloud.com";

		$this->contentSecurityPolicy->addAllowedChildSrcDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowChildSrcDomain('www.owncloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyDisallowChildSrcDomainMultipleStakes() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self'";

		$this->contentSecurityPolicy->addAllowedChildSrcDomain('www.owncloud.com');
		$this->contentSecurityPolicy->disallowChildSrcDomain('www.owncloud.org')->disallowChildSrcDomain('www.owncloud.com');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyWithJsNonceAndScriptDomains() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';script-src 'nonce-TXlKc05vbmNl' www.nextcloud.com www.nextcloud.org";

		$this->contentSecurityPolicy->addAllowedScriptDomain('www.nextcloud.com');
		$this->contentSecurityPolicy->useJsNonce('MyJsNonce');
		$this->contentSecurityPolicy->addAllowedScriptDomain('www.nextcloud.org');
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyWithJsNonceAndSelfScriptDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';script-src 'nonce-TXlKc05vbmNl'";

		$this->contentSecurityPolicy->useJsNonce('MyJsNonce');
		$this->contentSecurityPolicy->addAllowedScriptDomain("'self'");
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyWithoutJsNonceAndSelfScriptDomain() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';script-src 'self'";

		$this->contentSecurityPolicy->addAllowedScriptDomain("'self'");
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyWithReportUri() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';report-uri https://my-report-uri.com";

		$this->contentSecurityPolicy->addReportTo("https://my-report-uri.com");
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}

	public function testGetPolicyWithMultipleReportUri() {
		$expectedPolicy = "default-src 'none';base-uri 'none';manifest-src 'self';report-uri https://my-report-uri.com https://my-other-report-uri.com";

		$this->contentSecurityPolicy->addReportTo("https://my-report-uri.com");
		$this->contentSecurityPolicy->addReportTo("https://my-other-report-uri.com");
		$this->assertSame($expectedPolicy, $this->contentSecurityPolicy->buildPolicy());
	}
}
