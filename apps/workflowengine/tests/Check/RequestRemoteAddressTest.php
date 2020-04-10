<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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

namespace OCA\WorkflowEngine\Tests\Check;

use OCP\IL10N;
use OCP\IRequest;

class RequestRemoteAddressTest extends \Test\TestCase {

	/** @var \OCP\IRequest|\PHPUnit_Framework_MockObject_MockObject */
	protected $request;

	/**
	 * @return \OCP\IL10N|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getL10NMock() {
		$l = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($string, $args) {
				return sprintf($string, $args);
			});
		return $l;
	}

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->getMockBuilder(IRequest::class)
			->getMock();
	}

	public function dataExecuteCheckIPv4() {
		return [
			['127.0.0.1/32', '127.0.0.1', true],
			['127.0.0.1/32', '127.0.0.0', false],
			['127.0.0.1/31', '127.0.0.0', true],
			['127.0.0.1/32', '127.0.0.2', false],
			['127.0.0.1/31', '127.0.0.2', false],
			['127.0.0.1/30', '127.0.0.2', true],
		];
	}

	/**
	 * @dataProvider dataExecuteCheckIPv4
	 * @param string $value
	 * @param string $ip
	 * @param bool $expected
	 */
	public function testExecuteCheckMatchesIPv4($value, $ip, $expected) {
		$check = new \OCA\WorkflowEngine\Check\RequestRemoteAddress($this->getL10NMock(), $this->request);

		$this->request->expects($this->once())
			->method('getRemoteAddress')
			->willReturn($ip);

		$this->assertEquals($expected, $check->executeCheck('matchesIPv4', $value));
	}

	/**
	 * @dataProvider dataExecuteCheckIPv4
	 * @param string $value
	 * @param string $ip
	 * @param bool $expected
	 */
	public function testExecuteCheckNotMatchesIPv4($value, $ip, $expected) {
		$check = new \OCA\WorkflowEngine\Check\RequestRemoteAddress($this->getL10NMock(), $this->request);

		$this->request->expects($this->once())
			->method('getRemoteAddress')
			->willReturn($ip);

		$this->assertEquals(!$expected, $check->executeCheck('!matchesIPv4', $value));
	}

	public function dataExecuteCheckIPv6() {
		return [
			['::1/128', '::1', true],
			['::2/128', '::3', false],
			['::2/127', '::3', true],
			['::1/128', '::2', false],
			['::1/127', '::2', false],
			['::1/126', '::2', true],
			['1234::1/127', '1234::', true],
		];
	}

	/**
	 * @dataProvider dataExecuteCheckIPv6
	 * @param string $value
	 * @param string $ip
	 * @param bool $expected
	 */
	public function testExecuteCheckMatchesIPv6($value, $ip, $expected) {
		$check = new \OCA\WorkflowEngine\Check\RequestRemoteAddress($this->getL10NMock(), $this->request);

		$this->request->expects($this->once())
			->method('getRemoteAddress')
			->willReturn($ip);

		$this->assertEquals($expected, $check->executeCheck('matchesIPv6', $value));
	}

	/**
	 * @dataProvider dataExecuteCheckIPv6
	 * @param string $value
	 * @param string $ip
	 * @param bool $expected
	 */
	public function testExecuteCheckNotMatchesIPv6($value, $ip, $expected) {
		$check = new \OCA\WorkflowEngine\Check\RequestRemoteAddress($this->getL10NMock(), $this->request);

		$this->request->expects($this->once())
			->method('getRemoteAddress')
			->willReturn($ip);

		$this->assertEquals(!$expected, $check->executeCheck('!matchesIPv6', $value));
	}
}
