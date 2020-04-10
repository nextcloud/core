<?php
/**
 * Copyright (c) 2015 Lukas Reschke <lukas@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Security;

use OC\Security\TrustedDomainHelper;
use OCP\IConfig;

/**
 * Class TrustedDomainHelperTest
 */
class TrustedDomainHelperTest extends \Test\TestCase {
	/** @var IConfig */
	protected $config;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
	}

	/**
	 * @dataProvider trustedDomainDataProvider
	 * @param string $trustedDomains
	 * @param string $testDomain
	 * @param bool $result
	 */
	public function testIsTrustedDomain($trustedDomains, $testDomain, $result) {
		$this->config->expects($this->at(0))
			->method('getSystemValue')
			->with('overwritehost')
			->willReturn('');
		$this->config->expects($this->at(1))
			->method('getSystemValue')
			->with('trusted_domains')
			->willReturn($trustedDomains);

		$trustedDomainHelper = new TrustedDomainHelper($this->config);
		$this->assertEquals($result, $trustedDomainHelper->isTrustedDomain($testDomain));
	}

	/**
	 * @return array
	 */
	public function trustedDomainDataProvider() {
		$trustedHostTestList = [
			'host.one.test',
			'host.two.test',
			'[1fff:0:a88:85a3::ac1f]',
			'host.three.test:443',
			'*.leading.host',
			'trailing.host*',
			'cen*ter',
			'*.leadingwith.port:123',
			'trailingwith.port*:456',
			'UPPERCASE.DOMAIN',
			'lowercase.domain',
		];
		return [
			// empty defaults to false with 8.1
			[null, 'host.one.test:8080', false],
			['', 'host.one.test:8080', false],
			[[], 'host.one.test:8080', false],
			// trust list when defined
			[$trustedHostTestList, 'host.two.test:8080', true],
			[$trustedHostTestList, 'host.two.test:9999', true],
			[$trustedHostTestList, 'host.three.test:8080', false],
			[$trustedHostTestList, 'host.two.test:8080:aa:222', false],
			[$trustedHostTestList, '[1fff:0:a88:85a3::ac1f]', true],
			[$trustedHostTestList, '[1fff:0:a88:85a3::ac1f]:801', true],
			[$trustedHostTestList, '[1fff:0:a88:85a3::ac1f]:801:34', false],
			[$trustedHostTestList, 'host.three.test:443', true],
			[$trustedHostTestList, 'host.three.test:80', false],
			[$trustedHostTestList, 'host.three.test', false],
			// trust localhost regardless of trust list
			[$trustedHostTestList, 'localhost', true],
			[$trustedHostTestList, 'localhost:8080', true],
			[$trustedHostTestList, '127.0.0.1', true],
			[$trustedHostTestList, '127.0.0.1:8080', true],
			// do not trust invalid localhosts
			[$trustedHostTestList, 'localhost:1:2', false],
			[$trustedHostTestList, 'localhost: evil.host', false],
			// do not trust casting
			[[1], '1', false],
			// leading *
			[$trustedHostTestList, 'abc.leading.host', true],
			[$trustedHostTestList, 'abc.def.leading.host', true],
			[$trustedHostTestList, 'abc.def.leading.host.another', false],
			[$trustedHostTestList, 'abc.def.leading.host:123', true],
			[$trustedHostTestList, 'leading.host', false],
			// trailing *
			[$trustedHostTestList, 'trailing.host', true],
			[$trustedHostTestList, 'trailing.host.abc', true],
			[$trustedHostTestList, 'trailing.host.abc.def', true],
			[$trustedHostTestList, 'trailing.host.abc:123', true],
			[$trustedHostTestList, 'another.trailing.host', false],
			// center *
			[$trustedHostTestList, 'center', true],
			[$trustedHostTestList, 'cenxxxter', true],
			[$trustedHostTestList, 'cen.x.y.ter', true],
			// with port
			[$trustedHostTestList, 'abc.leadingwith.port:123', true],
			[$trustedHostTestList, 'abc.leadingwith.port:1234', false],
			[$trustedHostTestList, 'trailingwith.port.abc:456', true],
			[$trustedHostTestList, 'trailingwith.port.abc:123', false],
			// bad hostname
			[$trustedHostTestList, '-bad', false],
			[$trustedHostTestList, '-bad.leading.host', false],
			[$trustedHostTestList, 'bad..der.leading.host', false],
			// case sensitivity
			[$trustedHostTestList, 'uppercase.domain', true],
			[$trustedHostTestList, 'LOWERCASE.DOMAIN', true],
		];
	}

	public function testIsTrustedDomainOverwriteHost() {
		$this->config->expects($this->at(0))
			->method('getSystemValue')
			->with('overwritehost')
			->willReturn('myproxyhost');

		$trustedDomainHelper = new TrustedDomainHelper($this->config);
		$this->assertTrue($trustedDomainHelper->isTrustedDomain('myproxyhost'));
		$this->assertTrue($trustedDomainHelper->isTrustedDomain('myotherhost'));
	}
}
