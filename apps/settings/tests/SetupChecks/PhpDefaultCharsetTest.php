<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Daniel Kesselberg <mail@danielkesselberg.de>
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
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

namespace OCA\Settings\Tests;

use OCA\Settings\SetupChecks\PhpDefaultCharset;
use Test\TestCase;

class PhpDefaultCharsetTest extends TestCase {
	public function testPass(): void {
		$check = new PhpDefaultCharset();
		$this->assertTrue($check->run());
	}

	public function testFail(): void {
		ini_set('default_charset', 'ISO-8859-15');

		$check = new PhpDefaultCharset();
		$this->assertFalse($check->run());

		ini_restore('default_charset');
	}
}
