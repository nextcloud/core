<?php
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

use OCA\WorkflowEngine\Check\RequestUserAgent;
use OCP\IL10N;
use OCP\IRequest;
use Test\TestCase;

class RequestUserAgentTest extends TestCase {

	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	protected $request;

	/** @var RequestUserAgent */
	protected $check;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject $l */
		$l = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$l->expects($this->any())
			->method('t')
			->willReturnCallback(function ($string, $args) {
				return sprintf($string, $args);
			});

		$this->check = new RequestUserAgent($l, $this->request);
	}

	public function dataExecuteCheck() {
		return [
			['is', 'android', 'Mozilla/5.0 (Android) Nextcloud-android v2.2.0', true],
			['is', 'android', 'Mozilla/5.0 (iOS) Nextcloud-iOS v2.2.0', false],
			['is', 'android', 'Mozilla/5.0 (Linux) mirall/2.2.0', false],
			['is', 'android', 'Mozilla/5.0 (Windows) Nextcloud-Outlook v2.2.0', false],
			['is', 'android', 'Mozilla/5.0 (Linux) Nextcloud-Thunderbird v2.2.0', false],
			['!is', 'android', 'Mozilla/5.0 (Android) Nextcloud-android v2.2.0', false],
			['!is', 'android', 'Mozilla/5.0 (iOS) Nextcloud-iOS v2.2.0', true],
			['!is', 'android', 'Mozilla/5.0 (Linux) mirall/2.2.0', true],
			['!is', 'android', 'Mozilla/5.0 (Windows) Nextcloud-Outlook v2.2.0', true],
			['!is', 'android', 'Mozilla/5.0 (Linux) Nextcloud-Thunderbird v2.2.0', true],

			['is', 'ios', 'Mozilla/5.0 (Android) Nextcloud-android v2.2.0', false],
			['is', 'ios', 'Mozilla/5.0 (iOS) Nextcloud-iOS v2.2.0', true],
			['is', 'ios', 'Mozilla/5.0 (Linux) mirall/2.2.0', false],
			['is', 'ios', 'Mozilla/5.0 (Windows) Nextcloud-Outlook v2.2.0', false],
			['is', 'ios', 'Mozilla/5.0 (Linux) Nextcloud-Thunderbird v2.2.0', false],
			['!is', 'ios', 'Mozilla/5.0 (Android) Nextcloud-android v2.2.0', true],
			['!is', 'ios', 'Mozilla/5.0 (iOS) Nextcloud-iOS v2.2.0', false],
			['!is', 'ios', 'Mozilla/5.0 (Linux) mirall/2.2.0', true],
			['!is', 'ios', 'Mozilla/5.0 (Windows) Nextcloud-Outlook v2.2.0', true],
			['!is', 'ios', 'Mozilla/5.0 (Linux) Nextcloud-Thunderbird v2.2.0', true],

			['is', 'desktop', 'Mozilla/5.0 (Android) Nextcloud-android v2.2.0', false],
			['is', 'desktop', 'Mozilla/5.0 (iOS) Nextcloud-iOS v2.2.0', false],
			['is', 'desktop', 'Mozilla/5.0 (Linux) mirall/2.2.0', true],
			['is', 'desktop', 'Mozilla/5.0 (Windows) Nextcloud-Outlook v2.2.0', false],
			['is', 'desktop', 'Mozilla/5.0 (Linux) Nextcloud-Thunderbird v2.2.0', false],
			['!is', 'desktop', 'Mozilla/5.0 (Android) Nextcloud-android v2.2.0', true],
			['!is', 'desktop', 'Mozilla/5.0 (iOS) Nextcloud-iOS v2.2.0', true],
			['!is', 'desktop', 'Mozilla/5.0 (Linux) mirall/2.2.0', false],
			['!is', 'desktop', 'Mozilla/5.0 (Windows) Nextcloud-Outlook v2.2.0', true],
			['!is', 'desktop', 'Mozilla/5.0 (Linux) Nextcloud-Thunderbird v2.2.0', true],

			['is', 'mail', 'Mozilla/5.0 (Android) Nextcloud-android v2.2.0', false],
			['is', 'mail', 'Mozilla/5.0 (iOS) Nextcloud-iOS v2.2.0', false],
			['is', 'mail', 'Mozilla/5.0 (Linux) mirall/2.2.0', false],
			['is', 'mail', 'Mozilla/5.0 (Windows) Nextcloud-Outlook v2.2.0', true],
			['is', 'mail', 'Mozilla/5.0 (Linux) Nextcloud-Thunderbird v2.2.0', true],
			['!is', 'mail', 'Mozilla/5.0 (Android) Nextcloud-android v2.2.0', true],
			['!is', 'mail', 'Mozilla/5.0 (iOS) Nextcloud-iOS v2.2.0', true],
			['!is', 'mail', 'Mozilla/5.0 (Linux) mirall/2.2.0', true],
			['!is', 'mail', 'Mozilla/5.0 (Windows) Nextcloud-Outlook v2.2.0', false],
			['!is', 'mail', 'Mozilla/5.0 (Linux) Nextcloud-Thunderbird v2.2.0', false],
		];
	}

	/**
	 * @dataProvider dataExecuteCheck
	 * @param string $operation
	 * @param string $checkValue
	 * @param string $actualValue
	 * @param bool $expected
	 */
	public function testExecuteCheck($operation, $checkValue, $actualValue, $expected) {
		$this->request->expects($this->once())
			->method('getHeader')
			->willReturn($actualValue);

		/** @var \OCA\WorkflowEngine\Check\AbstractStringCheck $check */
		$this->assertEquals($expected, $this->check->executeCheck($operation, $checkValue));
	}
}
