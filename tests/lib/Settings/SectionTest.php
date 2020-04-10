<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Settings\Tests\AppInfo;

use OC\Settings\Section;
use Test\TestCase;

class SectionTest extends TestCase {
	public function testGetID() {
		$this->assertSame('ldap', (new Section('ldap', 'name', 1))->getID());
	}
	public function testGetName() {
		$this->assertSame('name', (new Section('ldap', 'name', 1))->getName());
	}
	public function testGetPriority() {
		$this->assertSame(1, (new Section('ldap', 'name', 1))->getPriority());
	}
}
