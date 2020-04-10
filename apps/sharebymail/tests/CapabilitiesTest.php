<?php
/**
 *
 *
 * @author MasterOfDeath <rinat.gumirov@mail.ru>
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

namespace OCA\ShareByMail\Tests;

use OCA\ShareByMail\Capabilities;
use Test\TestCase;

class CapabilitiesTest extends TestCase {
	/** @var Capabilities */
	private $capabilities;

	protected function setUp(): void {
		parent::setUp();

		$this->capabilities = new Capabilities();
	}

	public function testGetCapabilities() {
		$capabilities = [
			'files_sharing' =>
				[
					'sharebymail' =>
						[
							'enabled' => true,
							'upload_files_drop' => ['enabled' => true],
							'password' => ['enabled' => true],
							'expire_date' => ['enabled' => true]
						]
				]
		];

		$this->assertSame($capabilities, $this->capabilities->getCapabilities());
	}
}
