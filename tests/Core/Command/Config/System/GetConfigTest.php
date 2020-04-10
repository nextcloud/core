<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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

namespace Tests\Core\Command\Config\System;

use OC\Core\Command\Config\System\GetConfig;
use OC\SystemConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class GetConfigTest extends TestCase {
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $systemConfig;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $consoleInput;
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $consoleOutput;

	/** @var \Symfony\Component\Console\Command\Command */
	protected $command;

	protected function setUp(): void {
		parent::setUp();

		$systemConfig = $this->systemConfig = $this->getMockBuilder(SystemConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$this->consoleInput = $this->getMockBuilder(InputInterface::class)->getMock();
		$this->consoleOutput = $this->getMockBuilder(OutputInterface::class)->getMock();

		/** @var \OC\SystemConfig $systemConfig */
		$this->command = new GetConfig($systemConfig);
	}


	public function getData() {
		return [
			// String output as json
			['name', 'newvalue', true, null, false, 'json', 0, json_encode('newvalue')],
			// String output as plain text
			['name', 'newvalue', true, null, false, 'plain', 0, 'newvalue'],
			// String falling back to default output as json
			['name', null, false, 'newvalue', true, 'json', 0, json_encode('newvalue')],
			// String falling back without default: error
			['name', null, false, null, false, 'json', 1, null],

			// Int "0" output as json/plain
			['name', 0, true, null, false, 'json', 0, json_encode(0)],
			['name', 0, true, null, false, 'plain', 0, '0'],
			// Int "1" output as json/plain
			['name', 1, true, null, false, 'json', 0, json_encode(1)],
			['name', 1, true, null, false, 'plain', 0, '1'],

			// Bool "true" output as json/plain
			['name', true, true, null, false, 'json', 0, json_encode(true)],
			['name', true, true, null, false, 'plain', 0, 'true'],
			// Bool "false" output as json/plain
			['name', false, true, null, false, 'json', 0, json_encode(false)],
			['name', false, true, null, false, 'plain', 0, 'false'],

			// Null output as json/plain
			['name', null, true, null, false, 'json', 0, json_encode(null)],
			['name', null, true, null, false, 'plain', 0, 'null'],

			// Array output as json/plain
			['name', ['a', 'b'], true, null, false, 'json', 0, json_encode(['a', 'b'])],
			['name', ['a', 'b'], true, null, false, 'plain', 0, "a\nb"],
			// Key array output as json/plain
			['name', [0 => 'a', 1 => 'b'], true, null, false, 'json', 0, json_encode(['a', 'b'])],
			['name', [0 => 'a', 1 => 'b'], true, null, false, 'plain', 0, "a\nb"],
			// Associative array output as json/plain
			['name', ['a' => 1, 'b' => 2], true, null, false, 'json', 0, json_encode(['a' => 1, 'b' => 2])],
			['name', ['a' => 1, 'b' => 2], true, null, false, 'plain', 0, "a: 1\nb: 2"],

			// Nested depth
			[['name', 'a'], ['a' => 1, 'b' => 2], true, null, false, 'json', 0, json_encode(1)],
			[['name', 'a'], ['a' => 1, 'b' => 2], true, null, false, 'plain', 0, '1'],
			[['name', 'c'], ['a' => 1, 'b' => 2], true, true, true, 'json', 0, json_encode(true)],
			[['name', 'c'], ['a' => 1, 'b' => 2], true, true, false, 'json', 1, null],

		];
	}

	/**
	 * @dataProvider getData
	 *
	 * @param string[] $configNames
	 * @param mixed $value
	 * @param bool $configExists
	 * @param mixed $defaultValue
	 * @param bool $hasDefault
	 * @param string $outputFormat
	 * @param int $expectedReturn
	 * @param string $expectedMessage
	 */
	public function testGet($configNames, $value, $configExists, $defaultValue, $hasDefault, $outputFormat, $expectedReturn, $expectedMessage) {
		if (is_array($configNames)) {
			$configName = $configNames[0];
		} else {
			$configName = $configNames;
			$configNames = [$configName];
		}
		$this->systemConfig->expects($this->atLeastOnce())
			->method('getKeys')
			->willReturn($configExists ? [$configName] : []);

		if (!$expectedReturn) {
			if ($configExists) {
				$this->systemConfig->expects($this->once())
					->method('getValue')
					->with($configName)
					->willReturn($value);
			}
		}

		$this->consoleInput->expects($this->once())
			->method('getArgument')
			->with('name')
			->willReturn($configNames);
		$this->consoleInput->expects($this->any())
			->method('getOption')
			->willReturnMap([
				['default-value', $defaultValue],
				['output', $outputFormat],
			]);
		$this->consoleInput->expects($this->any())
			->method('hasParameterOption')
			->willReturnMap([
				['--output', false, true],
				['--default-value', false,$hasDefault],
			]);

		if ($expectedMessage !== null) {
			global $output;

			$output = '';
			$this->consoleOutput->expects($this->any())
				->method('writeln')
				->willReturnCallback(function ($value) {
					global $output;
					$output .= $value . "\n";
					return $output;
				});
		}

		$this->assertSame($expectedReturn, $this->invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]));

		if ($expectedMessage !== null) {
			global $output;
			// Remove the trailing newline
			$this->assertSame($expectedMessage, substr($output, 0, -1));
		}
	}
}
