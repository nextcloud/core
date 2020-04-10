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

namespace Test\GlobalScale;

use OC\GlobalScale\Config;
use OCP\IConfig;
use Test\TestCase;

class ConfigTest extends TestCase {

	/** @var  IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
	}

	/**
	 * @param array $mockMethods
	 * @return Config|\PHPUnit_Framework_MockObject_MockObject
	 */
	public function getInstance($mockMethods = []) {

		if (!empty($mockMethods)) {
			return $this->getMockBuilder(Config::class)
				->setConstructorArgs([$this->config])
				->setMethods($mockMethods)
				->getMock();
		}

		return new Config($this->config);
	}

	public function testIsGlobalScaleEnabled() {
		$gsConfig = $this->getInstance();
		$this->config->expects($this->once())->method('getSystemValue')
			->with('gs.enabled', false)->willReturn(true);

		$result = $gsConfig->isGlobalScaleEnabled();

		$this->assertTrue($result);
	}


	/**
	 * @dataProvider dataTestOnlyInternalFederation
	 *
	 * @param bool $gsEnabled
	 * @param string $gsFederation
	 * @param bool $expected
	 */
	public function testOnlyInternalFederation($gsEnabled, $gsFederation, $expected) {
		$gsConfig = $this->getInstance(['isGlobalScaleEnabled']);

		$gsConfig->expects($this->any())->method('isGlobalScaleEnabled')->willReturn($gsEnabled);

		$this->config->expects($this->any())->method('getSystemValue')
			->with('gs.federation', 'internal')->willReturn($gsFederation);

		$this->assertSame($expected, $gsConfig->onlyInternalFederation());
	}

	public function dataTestOnlyInternalFederation() {
		return [
			[true, 'global', false],
			[true, 'internal', true],
			[false, 'global', false],
			[false, 'internal', false]
		];
	}

}
