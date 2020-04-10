<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Federation\Tests\Settings;

use OCA\Federation\Settings\Admin;
use OCA\Federation\TrustedServers;
use OCP\AppFramework\Http\TemplateResponse;
use Test\TestCase;

class AdminTest extends TestCase {
	/** @var Admin */
	private $admin;
	/** @var TrustedServers */
	private $trustedServers;

	protected function setUp(): void {
		parent::setUp();
		$this->trustedServers = $this->getMockBuilder('\OCA\Federation\TrustedServers')->disableOriginalConstructor()->getMock();
		$this->admin = new Admin(
			$this->trustedServers
		);
	}

	public function testGetForm() {
		$this->trustedServers
			->expects($this->once())
			->method('getServers')
			->willReturn(['myserver', 'secondserver']);
		$this->trustedServers
			->expects($this->once())
			->method('getAutoAddServers')
			->willReturn(['autoserver1', 'autoserver2']);

		$params = [
			'trustedServers' => ['myserver', 'secondserver'],
			'autoAddServers' => ['autoserver1', 'autoserver2'],
		];
		$expected = new TemplateResponse('federation', 'settings-admin', $params, '');
		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetSection() {
		$this->assertSame('sharing', $this->admin->getSection());
	}

	public function testGetPriority() {
		$this->assertSame(30, $this->admin->getPriority());
	}
}
