<?php
/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
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

namespace Test\OCS;

use OC\OCS\DiscoveryService;
use OCP\Http\Client\IClientService;
use OCP\ICacheFactory;
use OCP\OCS\IDiscoveryService;
use Test\TestCase;

class DiscoveryServiceTest extends TestCase {

	/** @var  \PHPUnit_Framework_MockObject_MockObject | ICacheFactory */
	private $cacheFactory;

	/** @var  \PHPUnit_Framework_MockObject_MockObject | IClientService */
	private $clientService;

	/** @var  IDiscoveryService */
	private $discoveryService;

	protected function setUp(): void {
		parent::setUp();

		$this->cacheFactory = $this->getMockBuilder(ICacheFactory::class)->getMock();
		$this->clientService = $this->getMockBuilder(IClientService::class)->getMock();

		$this->discoveryService = new DiscoveryService(
			$this->cacheFactory,
			$this->clientService
		);
	}

	/**
	 * @dataProvider dataTestIsSafeUrl
	 *
	 * @param string $url
	 * @param bool $expected
	 */
	public function testIsSafeUrl($url, $expected) {
		$result = $this->invokePrivate($this->discoveryService, 'isSafeUrl', [$url]);
		$this->assertSame($expected, $result);
	}

	public function dataTestIsSafeUrl() {
		return [
			['api/ocs/v1.php/foo', true],
			['/api/ocs/v1.php/foo', true],
			['api/ocs/v1.php/foo/', true],
			['api/ocs/v1.php/foo-bar/', true],
			['api/ocs/v1:php/foo', false],
			['api/ocs/<v1.php/foo', false],
			['api/ocs/v1.php>/foo', false],
		];
	}

	/**
	 * @dataProvider dataTestGetEndpoints
	 *
	 * @param array $decodedServices
	 * @param string $service
	 * @param array $expected
	 */
	public function testGetEndpoints($decodedServices, $service, $expected) {
		$result = $this->invokePrivate($this->discoveryService, 'getEndpoints', [$decodedServices, $service]);
		$this->assertSame($expected, $result);
	}

	public function dataTestGetEndpoints() {
		return [
			[['services' => ['myService' => ['endpoints' => []]]], 'myService', []],
			[['services' => ['myService' => ['endpoints' => ['foo' => '/bar']]]], 'myService', ['foo' => '/bar']],
			[['services' => ['myService' => ['endpoints' => ['foo' => '/bar']]]], 'anotherService', []],
			[['services' => ['myService' => ['endpoints' => ['foo' => '/bar</foo']]]], 'myService', []],
		];
	}

}
