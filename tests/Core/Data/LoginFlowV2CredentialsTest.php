<?php
/**
 * @author Konrad Abicht <hi@inspirito.de>
 *
 * @copyright Copyright (c) 2021, Konrad Abicht <hi@inspirito.de>
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

namespace Tests\Core\Data;

use JsonSerializable;
use OC\Core\Data\LoginFlowV2Credentials;
use Test\TestCase;

class LoginFlowV2CredentialsTest extends TestCase {
	/** @var \OC\Core\Data\LoginFlowV2Credentials */
	private $fixture;

	public function setUp(): void {
		parent::setUp();

		$this->fixture = new LoginFlowV2Credentials('server', 'login', 'pass');
	}

	public function testImplementsJsonSerializable() {
		$this->assertTrue($this->fixture instanceof JsonSerializable);
	}

	/**
	 * Test getter functions.
	 */
	public function testGetter() {
		$this->assertEquals('server', $this->fixture->getServer());
		$this->assertEquals('login', $this->fixture->getLoginName());
		$this->assertEquals('pass', $this->fixture->getAppPassword());
	}

	public function testJsonSerialize() {
		$this->assertEquals(
			[
				'server' => 'server',
				'loginName' => 'login',
				'appPassword' => 'pass',
			],
			$this->fixture->jsonSerialize()
		);
	}
}
