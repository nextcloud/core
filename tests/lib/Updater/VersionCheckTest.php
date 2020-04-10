<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace Test\Updater;

use OC\Updater\VersionCheck;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\Util;

class VersionCheckTest extends \Test\TestCase {
	/** @var IConfig| \PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var VersionCheck | \PHPUnit_Framework_MockObject_MockObject*/
	private $updater;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$clientService = $this->getMockBuilder(IClientService::class)
			->disableOriginalConstructor()
			->getMock();

		$this->updater = $this->getMockBuilder(VersionCheck::class)
			->setMethods(['getUrlContent'])
			->setConstructorArgs([$clientService, $this->config])
			->getMock();
	}

	/**
	 * @param string $baseUrl
	 * @return string
	 */
	private function buildUpdateUrl($baseUrl) {
		return $baseUrl . '?version='.implode('x', Util::getVersion()).'xinstalledatxlastupdatedatx'.\OC_Util::getChannel().'xxx'.PHP_MAJOR_VERSION.'x'.PHP_MINOR_VERSION.'x'.PHP_RELEASE_VERSION;
	}

	public function testCheckInCache() {
		$expectedResult = [
			'version' => '8.0.4.2',
			'versionstring' => 'ownCloud 8.0.4',
			'url' => 'https://download.example.org/community/owncloud-8.0.4.zip',
			'web' => 'http://doc.example.org/server/8.0/admin_manual/maintenance/upgrade.html',
			'changes' => '',
		];

		$this->config
			->expects($this->at(0))
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->config
			->expects($this->at(1))
			->method('getAppValue')
			->with('core', 'lastupdatedat')
			->willReturn(time());
		$this->config
			->expects($this->at(2))
			->method('getAppValue')
			->with('core', 'lastupdateResult')
			->willReturn(json_encode($expectedResult));

		$this->assertSame($expectedResult, $this->updater->check());
	}

	public function testCheckWithoutUpdateUrl() {
		$expectedResult = [
			'version' => '8.0.4.2',
			'versionstring' => 'ownCloud 8.0.4',
			'url' => 'https://download.example.org/community/owncloud-8.0.4.zip',
			'web' => 'http://doc.example.org/server/8.0/admin_manual/maintenance/upgrade.html',
			'changes' => '',
			'autoupdater' => '0',
			'eol' => '1',
		];

		$this->config
			->expects($this->at(0))
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->config
			->expects($this->at(1))
			->method('getAppValue')
			->with('core', 'lastupdatedat')
			->willReturn(0);
		$this->config
			->expects($this->at(2))
			->method('getSystemValue')
			->with('updater.server.url', 'https://updates.nextcloud.com/updater_server/')
			->willReturnArgument(1);
		$this->config
			->expects($this->at(3))
			->method('setAppValue')
			->with('core', 'lastupdatedat', $this->isType('integer'));
		$this->config
			->expects($this->at(5))
			->method('getAppValue')
			->with('core', 'installedat')
			->willReturn('installedat');
		$this->config
			->expects($this->at(6))
			->method('getAppValue')
			->with('core', 'lastupdatedat')
			->willReturn('lastupdatedat');
		$this->config
			->expects($this->at(7))
			->method('setAppValue')
			->with('core', 'lastupdateResult', json_encode($expectedResult));

		$updateXml = '<?xml version="1.0"?>
<owncloud>
  <version>8.0.4.2</version>
  <versionstring>ownCloud 8.0.4</versionstring>
  <url>https://download.example.org/community/owncloud-8.0.4.zip</url>
  <web>http://doc.example.org/server/8.0/admin_manual/maintenance/upgrade.html</web>
  <autoupdater>0</autoupdater>
  <eol>1</eol>
</owncloud>';
		$this->updater
			->expects($this->once())
			->method('getUrlContent')
			->with($this->buildUpdateUrl('https://updates.nextcloud.com/updater_server/'))
			->willReturn($updateXml);

		$this->assertSame($expectedResult, $this->updater->check());
	}

	public function testCheckWithInvalidXml() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->config
			->expects($this->at(1))
			->method('getAppValue')
			->with('core', 'lastupdatedat')
			->willReturn(0);
		$this->config
			->expects($this->at(2))
			->method('getSystemValue')
			->with('updater.server.url', 'https://updates.nextcloud.com/updater_server/')
			->willReturnArgument(1);
		$this->config
			->expects($this->at(3))
			->method('setAppValue')
			->with('core', 'lastupdatedat', $this->isType('integer'));
		$this->config
			->expects($this->at(5))
			->method('getAppValue')
			->with('core', 'installedat')
			->willReturn('installedat');
		$this->config
			->expects($this->at(6))
			->method('getAppValue')
			->with('core', 'lastupdatedat')
			->willReturn('lastupdatedat');
		$this->config
			->expects($this->at(7))
			->method('setAppValue')
			->with('core', 'lastupdateResult', '[]');

		$updateXml = 'Invalid XML Response!';
		$this->updater
			->expects($this->once())
			->method('getUrlContent')
			->with($this->buildUpdateUrl('https://updates.nextcloud.com/updater_server/'))
			->willReturn($updateXml);

		$this->assertSame([], $this->updater->check());
	}

	public function testCheckWithEmptyValidXmlResponse() {
		$expectedResult = [
			'version' => '',
			'versionstring' => '',
			'url' => '',
			'web' => '',
			'changes' => '',
			'autoupdater' => '',
			'eol' => '0',
		];

		$this->config
			->expects($this->at(0))
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->config
			->expects($this->at(1))
			->method('getAppValue')
			->with('core', 'lastupdatedat')
			->willReturn(0);
		$this->config
			->expects($this->at(2))
			->method('getSystemValue')
			->with('updater.server.url', 'https://updates.nextcloud.com/updater_server/')
			->willReturnArgument(1);
		$this->config
			->expects($this->at(3))
			->method('setAppValue')
			->with('core', 'lastupdatedat', $this->isType('integer'));
		$this->config
			->expects($this->at(5))
			->method('getAppValue')
			->with('core', 'installedat')
			->willReturn('installedat');
		$this->config
			->expects($this->at(6))
			->method('getAppValue')
			->with('core', 'lastupdatedat')
			->willReturn('lastupdatedat');

		$updateXml = '<?xml version="1.0"?>
<owncloud>
  <version></version>
  <versionstring></versionstring>
  <url></url>
  <web></web>
  <autoupdater></autoupdater>
</owncloud>';
		$this->updater
			->expects($this->once())
			->method('getUrlContent')
			->with($this->buildUpdateUrl('https://updates.nextcloud.com/updater_server/'))
			->willReturn($updateXml);

		$this->assertSame($expectedResult, $this->updater->check());
	}

	public function testCheckWithEmptyInvalidXmlResponse() {
		$expectedResult = [];

		$this->config
			->expects($this->at(0))
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->config
			->expects($this->at(1))
			->method('getAppValue')
			->with('core', 'lastupdatedat')
			->willReturn(0);
		$this->config
			->expects($this->at(2))
			->method('getSystemValue')
			->with('updater.server.url', 'https://updates.nextcloud.com/updater_server/')
			->willReturnArgument(1);
		$this->config
			->expects($this->at(3))
			->method('setAppValue')
			->with('core', 'lastupdatedat', $this->isType('integer'));
		$this->config
			->expects($this->at(5))
			->method('getAppValue')
			->with('core', 'installedat')
			->willReturn('installedat');
		$this->config
			->expects($this->at(6))
			->method('getAppValue')
			->with('core', 'lastupdatedat')
			->willReturn('lastupdatedat');
		$this->config
			->expects($this->at(7))
			->method('setAppValue')
			->with('core', 'lastupdateResult', json_encode($expectedResult));

		$updateXml = '';
		$this->updater
			->expects($this->once())
			->method('getUrlContent')
			->with($this->buildUpdateUrl('https://updates.nextcloud.com/updater_server/'))
			->willReturn($updateXml);

		$this->assertSame($expectedResult, $this->updater->check());
	}

	public function testCheckWithMissingAttributeXmlResponse() {
		$expectedResult = [
			'version' => '',
			'versionstring' => '',
			'url' => '',
			'web' => '',
			'changes' => '',
			'autoupdater' => '',
			'eol' => '0',
		];

		$this->config
			->expects($this->at(0))
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->config
			->expects($this->at(1))
			->method('getAppValue')
			->with('core', 'lastupdatedat')
			->willReturn(0);
		$this->config
			->expects($this->at(2))
			->method('getSystemValue')
			->with('updater.server.url', 'https://updates.nextcloud.com/updater_server/')
			->willReturnArgument(1);
		$this->config
			->expects($this->at(3))
			->method('setAppValue')
			->with('core', 'lastupdatedat', $this->isType('integer'));
		$this->config
			->expects($this->at(5))
			->method('getAppValue')
			->with('core', 'installedat')
			->willReturn('installedat');
		$this->config
			->expects($this->at(6))
			->method('getAppValue')
			->with('core', 'lastupdatedat')
			->willReturn('lastupdatedat');

		// missing autoupdater element should still not fail
		$updateXml = '<?xml version="1.0"?>
<owncloud>
  <version></version>
  <versionstring></versionstring>
  <url></url>
  <web></web>
</owncloud>';
		$this->updater
			->expects($this->once())
			->method('getUrlContent')
			->with($this->buildUpdateUrl('https://updates.nextcloud.com/updater_server/'))
			->willReturn($updateXml);

		$this->assertSame($expectedResult, $this->updater->check());
	}

	public function testNoInternet() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValueBool')
			->with('has_internet_connection', true)
			->willReturn(false);

		$this->assertFalse($this->updater->check());
	}
}
