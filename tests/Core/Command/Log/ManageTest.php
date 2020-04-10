<?php
/**
 * @author Robin McCorkell <rmccorkell@owncloud.com>
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

namespace Tests\Core\Command\Log;

use OC\Core\Command\Log\Manage;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class ManageTest extends TestCase {
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $config;
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $consoleInput;
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $consoleOutput;

	/** @var \Symfony\Component\Console\Command\Command */
	protected $command;

	protected function setUp(): void {
		parent::setUp();

		$config = $this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$this->consoleInput = $this->getMockBuilder(InputInterface::class)->getMock();
		$this->consoleOutput = $this->getMockBuilder(OutputInterface::class)->getMock();

		$this->command = new Manage($config);
	}

	public function testChangeBackend() {
		$this->consoleInput->method('getOption')
			->willReturnMap([
				['backend', 'syslog']
			]);
		$this->config->expects($this->once())
			->method('setSystemValue')
			->with('log_type', 'syslog');

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

	public function testChangeLevel() {
		$this->consoleInput->method('getOption')
			->willReturnMap([
				['level', 'debug']
			]);
		$this->config->expects($this->once())
			->method('setSystemValue')
			->with('loglevel', 0);

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

	public function testChangeTimezone() {
		$this->consoleInput->method('getOption')
			->willReturnMap([
				['timezone', 'UTC']
			]);
		$this->config->expects($this->once())
			->method('setSystemValue')
			->with('logtimezone', 'UTC');

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

	
	public function testValidateBackend() {
		$this->expectException(\InvalidArgumentException::class);

		self::invokePrivate($this->command, 'validateBackend', ['notabackend']);
	}

	
	public function testValidateTimezone() {
		$this->expectException(\Exception::class);

		// this might need to be changed when humanity colonises Mars
		self::invokePrivate($this->command, 'validateTimezone', ['Mars/OlympusMons']);
	}

	public function convertLevelStringProvider() {
		return [
			['dEbug', 0],
			['inFO', 1],
			['Warning', 2],
			['wArn', 2],
			['error', 3],
			['eRr', 3],
			['fAtAl', 4],
		];
	}

	/**
	 * @dataProvider convertLevelStringProvider
	 */
	public function testConvertLevelString($levelString, $expectedInt) {
		$this->assertEquals($expectedInt,
			self::invokePrivate($this->command, 'convertLevelString', [$levelString])
		);
	}

	
	public function testConvertLevelStringInvalid() {
		$this->expectException(\InvalidArgumentException::class);

		self::invokePrivate($this->command, 'convertLevelString', ['abc']);
	}

	public function convertLevelNumberProvider() {
		return [
			[0, 'Debug'],
			[1, 'Info'],
			[2, 'Warning'],
			[3, 'Error'],
			[4, 'Fatal'],
		];
	}

	/**
	 * @dataProvider convertLevelNumberProvider
	 */
	public function testConvertLevelNumber($levelNum, $expectedString) {
		$this->assertEquals($expectedString,
			self::invokePrivate($this->command, 'convertLevelNumber', [$levelNum])
		);
	}

	
	public function testConvertLevelNumberInvalid() {
		$this->expectException(\InvalidArgumentException::class);

		self::invokePrivate($this->command, 'convertLevelNumber', [11]);
	}

	public function testGetConfiguration() {
		$this->config->expects($this->at(0))
			->method('getSystemValue')
			->with('log_type', 'file')
			->willReturn('log_type_value');
		$this->config->expects($this->at(1))
			->method('getSystemValue')
			->with('loglevel', 2)
			->willReturn(0);
		$this->config->expects($this->at(2))
			->method('getSystemValue')
			->with('logtimezone', 'UTC')
			->willReturn('logtimezone_value');

		$this->consoleOutput->expects($this->at(0))
			->method('writeln')
			->with('Enabled logging backend: log_type_value');
		$this->consoleOutput->expects($this->at(1))
			->method('writeln')
			->with('Log level: Debug (0)');
		$this->consoleOutput->expects($this->at(2))
			->method('writeln')
			->with('Log timezone: logtimezone_value');

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

}
