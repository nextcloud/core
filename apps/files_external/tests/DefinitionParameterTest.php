<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCA\Files_External\Tests;

use OCA\Files_External\Lib\DefinitionParameter as Param;

class DefinitionParameterTest extends \Test\TestCase {

	public function testJsonSerialization() {
		$param = new Param('foo', 'bar');
		$this->assertEquals([
			'value' => 'bar',
			'flags' => 0,
			'type' => 0,
			'tooltip' => '',
		], $param->jsonSerialize());

		$param->setType(Param::VALUE_BOOLEAN);
		$this->assertEquals([
			'value' => 'bar',
			'flags' => 0,
			'type' => Param::VALUE_BOOLEAN,
			'tooltip' => '',
		], $param->jsonSerialize());

		$param->setType(Param::VALUE_PASSWORD);
		$param->setFlag(Param::FLAG_OPTIONAL);
		$this->assertEquals([
			'value' => 'bar',
			'flags' => Param::FLAG_OPTIONAL,
			'type' => Param::VALUE_PASSWORD,
			'tooltip' => '',
		], $param->jsonSerialize());

		$param->setType(Param::VALUE_HIDDEN);
		$param->setFlags(Param::FLAG_NONE);
		$this->assertEquals([
			'value' => 'bar',
			'flags' => Param::FLAG_NONE,
			'type' => Param::VALUE_HIDDEN,
			'tooltip' => '',
		], $param->jsonSerialize());
	}

	public function validateValueProvider() {
		return [
			[Param::VALUE_TEXT, Param::FLAG_NONE, 'abc', true],
			[Param::VALUE_TEXT, Param::FLAG_NONE, '', false],
			[Param::VALUE_TEXT, Param::FLAG_OPTIONAL, '', true],

			[Param::VALUE_BOOLEAN, Param::FLAG_NONE, false, true],
			[Param::VALUE_BOOLEAN, Param::FLAG_NONE, 123, false],
			// conversion from string to boolean
			[Param::VALUE_BOOLEAN, Param::FLAG_NONE, 'false', true, false],
			[Param::VALUE_BOOLEAN, Param::FLAG_NONE, 'true', true, true],

			[Param::VALUE_PASSWORD, Param::FLAG_NONE, 'foobar', true],
			[Param::VALUE_PASSWORD, Param::FLAG_NONE, '', false],

			[Param::VALUE_HIDDEN, Param::FLAG_NONE, '', false]
		];
	}

	/**
	 * @dataProvider validateValueProvider
	 */
	public function testValidateValue($type, $flags, $value, $success, $expectedValue = null) {
		$param = new Param('foo', 'bar');
		$param->setType($type);
		$param->setFlags($flags);

		$this->assertEquals($success, $param->validateValue($value));
		if (isset($expectedValue)) {
			$this->assertEquals($expectedValue, $value);
		}
	}
}
