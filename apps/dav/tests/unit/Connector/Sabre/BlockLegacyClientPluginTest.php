<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\DAV\Tests\unit\Connector\Sabre;

use OCA\DAV\Connector\Sabre\BlockLegacyClientPlugin;
use OCP\IConfig;
use PHPUnit_Framework_MockObject_MockObject;
use Test\TestCase;

/**
 * Class BlockLegacyClientPluginTest
 *
 * @package OCA\DAV\Tests\unit\Connector\Sabre
 */
class BlockLegacyClientPluginTest extends TestCase {
	/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var BlockLegacyClientPlugin */
	private $blockLegacyClientVersionPlugin;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$this->blockLegacyClientVersionPlugin = new BlockLegacyClientPlugin($this->config);
	}

	/**
	 * @return array
	 */
	public function oldDesktopClientProvider() {
		return [
			['Mozilla/5.0 (1.5.0) mirall/1.5.0'],
			['mirall/1.5.0'],
			['mirall/1.5.4'],
			['mirall/1.6.0'],
			['Mozilla/5.0 (Bogus Text) mirall/1.6.9'],
		];
	}

	/**
	 * @dataProvider oldDesktopClientProvider
	 * @param string $userAgent
	 */
	public function testBeforeHandlerException($userAgent) {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);
		$this->expectExceptionMessage('Unsupported client version.');

		/** @var \Sabre\HTTP\RequestInterface | PHPUnit_Framework_MockObject_MockObject $request */
		$request = $this->createMock('\Sabre\HTTP\RequestInterface');
		$request
			->expects($this->once())
			->method('getHeader')
			->with('User-Agent')
			->willReturn($userAgent);

		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('minimum.supported.desktop.version', '2.0.0')
			->willReturn('1.7.0');

		$this->blockLegacyClientVersionPlugin->beforeHandler($request);
	}

	/**
	 * @return array
	 */
	public function newAndAlternateDesktopClientProvider() {
		return [
			['Mozilla/5.0 (1.7.0) mirall/1.7.0'],
			['mirall/1.8.3'],
			['mirall/1.7.2'],
			['mirall/1.7.0'],
			['Mozilla/5.0 (Bogus Text) mirall/1.9.3'],
		];
	}

	/**
	 * @dataProvider newAndAlternateDesktopClientProvider
	 * @param string $userAgent
	 */
	public function testBeforeHandlerSuccess($userAgent) {
		/** @var \Sabre\HTTP\RequestInterface | PHPUnit_Framework_MockObject_MockObject $request */
		$request = $this->createMock('\Sabre\HTTP\RequestInterface');
		$request
			->expects($this->once())
			->method('getHeader')
			->with('User-Agent')
			->willReturn($userAgent);

		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('minimum.supported.desktop.version', '2.0.0')
			->willReturn('1.7.0');

		$this->blockLegacyClientVersionPlugin->beforeHandler($request);
	}

	public function testBeforeHandlerNoUserAgent() {
		/** @var \Sabre\HTTP\RequestInterface | PHPUnit_Framework_MockObject_MockObject $request */
		$request = $this->createMock('\Sabre\HTTP\RequestInterface');
		$request
			->expects($this->once())
			->method('getHeader')
			->with('User-Agent')
			->willReturn(null);
		$this->blockLegacyClientVersionPlugin->beforeHandler($request);
	}

}
