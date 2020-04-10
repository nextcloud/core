<?php
/**
 * @copyright 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Security\IdentityProof;

use OC\Security\IdentityProof\Key;
use Test\TestCase;

class KeyTest extends TestCase {
	/** @var Key */
	private $key;

	protected function setUp(): void {
		parent::setUp();

		$this->key = new Key('public', 'private');
	}

	public function testGetPrivate() {
		$this->assertSame('private', $this->key->getPrivate());
	}

	public function testGetPublic() {
		$this->assertSame('public', $this->key->getPublic());
	}
}
