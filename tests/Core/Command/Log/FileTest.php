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

use OC\Core\Command\Log\File;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class FileTest extends TestCase {
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

		$this->command = new File($config);
	}

	public function testEnable() {
		$this->consoleInput->method('getOption')
			->willReturnMap([
				['enable', 'true']
			]);
		$this->config->expects($this->once())
			->method('setSystemValue')
			->with('log_type', 'file');

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

	public function testChangeFile() {
		$this->consoleInput->method('getOption')
			->willReturnMap([
				['file', '/foo/bar/file.log']
			]);
		$this->config->expects($this->once())
			->method('setSystemValue')
			->with('logfile', '/foo/bar/file.log');

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

	public function changeRotateSizeProvider() {
		return [
			['42', 42],
			['0', 0],
			['1 kB', 1024],
			['5MB', 5 * 1024 * 1024],
		];
	}

	/**
	 * @dataProvider changeRotateSizeProvider
	 */
	public function testChangeRotateSize($optionValue, $configValue) {
		$this->consoleInput->method('getOption')
			->willReturnMap([
				['rotate-size', $optionValue]
			]);
		$this->config->expects($this->once())
			->method('setSystemValue')
			->with('log_rotate_size', $configValue);

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

	public function testGetConfiguration() {
		$this->config->method('getSystemValue')
			->willReturnMap([
				['log_type', 'file', 'log_type_value'],
				['datadirectory', \OC::$SERVERROOT.'/data', '/data/directory/'],
				['logfile', '/data/directory/nextcloud.log', '/var/log/nextcloud.log'],
				['log_rotate_size', 100 * 1024 * 1024, 5 * 1024 * 1024],
			]);

		$this->consoleOutput->expects($this->at(0))
			->method('writeln')
			->with('Log backend file: disabled');
		$this->consoleOutput->expects($this->at(1))
			->method('writeln')
			->with('Log file: /var/log/nextcloud.log');
		$this->consoleOutput->expects($this->at(2))
			->method('writeln')
			->with('Rotate at: 5 MB');

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

}
