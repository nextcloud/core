<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\DAV\Tests\unit\DAV;

use OCA\DAV\DAV\SystemPrincipalBackend;
use Test\TestCase;

class SystemPrincipalBackendTest extends TestCase {

	/**
	 * @dataProvider providesPrefix
	 * @param $expected
	 * @param $prefix
	 */
	public function testGetPrincipalsByPrefix($expected, $prefix) {
		$backend = new SystemPrincipalBackend();
		$result = $backend->getPrincipalsByPrefix($prefix);
		$this->assertEquals($expected, $result);
	}

	public function providesPrefix() {
		return [
			[[], ''],
			[[[
				'uri' => 'principals/system/system',
				'{DAV:}displayname' => 'system',
			],
				[
					'uri' => 'principals/system/public',
					'{DAV:}displayname' => 'public',
				]
			], 'principals/system'],
		];
	}

	/**
	 * @dataProvider providesPath
	 * @param $expected
	 * @param $path
	 */
	public function testGetPrincipalByPath($expected, $path) {
		$backend = new SystemPrincipalBackend();
		$result = $backend->getPrincipalByPath($path);
		$this->assertEquals($expected, $result);
	}

	public function providesPath() {
		return [
			[null, ''],
			[null, 'principals'],
			[null, 'principals/system'],
			[[
				'uri' => 'principals/system/system',
				'{DAV:}displayname' => 'system',
			], 'principals/system/system'],
		];
	}

	/**
	 * @dataProvider providesPrincipalForGetGroupMemberSet
	 *
	 * @param string $principal
	 * @throws \Sabre\DAV\Exception
	 */
	public function testGetGroupMemberSetExceptional($principal) {
		$this->expectException(\Sabre\DAV\Exception::class);
		$this->expectExceptionMessage('Principal not found');

		$backend = new SystemPrincipalBackend();
		$backend->getGroupMemberSet($principal);
	}

	public function providesPrincipalForGetGroupMemberSet() {
		return [
			[null],
			['principals/system'],
		];
	}

	/**
	 * @throws \Sabre\DAV\Exception
	 */
	public function testGetGroupMemberSet() {
		$backend = new SystemPrincipalBackend();
		$result = $backend->getGroupMemberSet('principals/system/system');
		$this->assertEquals(['principals/system/system'], $result);
	}

	/**
	 * @dataProvider providesPrincipalForGetGroupMembership
	 *
	 * @param string $principal
	 * @throws \Sabre\DAV\Exception
	 */
	public function testGetGroupMembershipExceptional($principal) {
		$this->expectException(\Sabre\DAV\Exception::class);
		$this->expectExceptionMessage('Principal not found');

		$backend = new SystemPrincipalBackend();
		$backend->getGroupMembership($principal);
	}

	public function providesPrincipalForGetGroupMembership() {
		return [
			['principals/system/a'],
		];
	}

	/**
	 * @throws \Sabre\DAV\Exception
	 */
	public function testGetGroupMembership() {
		$backend = new SystemPrincipalBackend();
		$result = $backend->getGroupMembership('principals/system/system');
		$this->assertEquals([], $result);
	}


}
