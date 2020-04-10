<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\User_LDAP\Tests;

use OCA\User_LDAP\Helper;
use OCP\IConfig;

class HelperTest extends \Test\TestCase {

	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;

	/** @var Helper */
	private $helper;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->helper = new Helper($this->config);
	}

	public function testGetServerConfigurationPrefixes() {
		$this->config->method('getAppKeys')
			->with($this->equalTo('user_ldap'))
			->willReturn([
				'foo',
				'ldap_configuration_active',
				's1ldap_configuration_active',
			]);

		$result = $this->helper->getServerConfigurationPrefixes(false);

		$this->assertEquals(['', 's1'], $result);
	}

	public function testGetServerConfigurationPrefixesActive() {
		$this->config->method('getAppKeys')
			->with($this->equalTo('user_ldap'))
			->willReturn([
				'foo',
				'ldap_configuration_active',
				's1ldap_configuration_active',
			]);

		$this->config->method('getAppValue')
			->willReturnCallback(function ($app, $key, $default) {
				if ($app !== 'user_ldap') {
					$this->fail('wrong app');
				}
				if ($key === 's1ldap_configuration_active') {
					return '1';
				}
				return $default;
			});

		$result = $this->helper->getServerConfigurationPrefixes(true);

		$this->assertEquals(['s1'], $result);
	}

	public function testGetServerConfigurationHost() {
		$this->config->method('getAppKeys')
			->with($this->equalTo('user_ldap'))
			->willReturn([
				'foo',
				'ldap_host',
				's1ldap_host',
				's02ldap_host',
			]);

		$this->config->method('getAppValue')
			->willReturnCallback(function ($app, $key, $default) {
				if ($app !== 'user_ldap') {
					$this->fail('wrong app');
				}
				if ($key === 'ldap_host') {
					return 'example.com';
				}
				if ($key === 's1ldap_host') {
					return 'foo.bar.com';
				}
				return $default;
			});

		$result = $this->helper->getServerConfigurationHosts();

		$this->assertEquals([
			'' => 'example.com',
			's1' => 'foo.bar.com',
			's02' => '',
		], $result);
	}
}
