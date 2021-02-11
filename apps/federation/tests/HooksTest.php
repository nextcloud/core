<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Federation\Tests;

use OCA\Federation\Hooks;
use OCA\Federation\TrustedServers;
use Test\TestCase;

class HooksTest extends TestCase {

	/** @var \PHPUnit\Framework\MockObject\MockObject | TrustedServers */
	private $trustedServers;

	/** @var  Hooks */
	private $hooks;

	protected function setUp(): void {
		parent::setUp();

		$this->trustedServers = $this->getMockBuilder('OCA\Federation\TrustedServers')
			->disableOriginalConstructor()->getMock();

		$this->hooks = new Hooks($this->trustedServers);
	}

	/**
	 * @dataProvider dataTestAddServerHook
	 *
	 * @param bool $autoAddEnabled is auto-add enabled
	 * @param bool $isTrustedServer is the server already in the list of trusted servers
	 * @param bool $addServer should the server be added
	 */
	public function testAddServerHook($autoAddEnabled, $isTrustedServer, $addServer) {
		$this->trustedServers->expects($this->any())->method('getAutoAddServers')
			->willReturn($autoAddEnabled);
		$this->trustedServers->expects($this->any())->method('isTrustedServer')
				->with('url')->willReturn($isTrustedServer);

		if ($addServer) {
			$this->trustedServers->expects($this->once())->method('addServer')
				->with('url');
		} else {
			$this->trustedServers->expects($this->never())->method('addServer');
		}

		$this->hooks->addServerHook(['server' => 'url']);
	}

	public function dataTestAddServerHook() {
		return [
			[true, true, false],
			[false, true, false],
			[true, false, true],
			[false, false, false],
		];
	}
}
