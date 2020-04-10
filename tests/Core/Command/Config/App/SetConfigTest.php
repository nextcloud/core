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

namespace Tests\Core\Command\Config\App;

use OC\Core\Command\Config\App\SetConfig;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class SetConfigTest extends TestCase {
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

		/** @var \OCP\IConfig $config */
		$this->command = new SetConfig($config);
	}


	public function setData() {
		return [
			[
				'name',
				'newvalue',
				true,
				true,
				true,
				'info',
			],
			[
				'name',
				'newvalue',
				false,
				true,
				false,
				'comment',
			],
		];
	}

	/**
	 * @dataProvider setData
	 *
	 * @param string $configName
	 * @param mixed $newValue
	 * @param bool $configExists
	 * @param bool $updateOnly
	 * @param bool $updated
	 * @param string $expectedMessage
	 */
	public function testSet($configName, $newValue, $configExists, $updateOnly, $updated, $expectedMessage) {
		$this->config->expects($this->once())
			->method('getAppKeys')
			->with('app-name')
			->willReturn($configExists ? [$configName] : []);

		if ($updated) {
			$this->config->expects($this->once())
				->method('setAppValue')
				->with('app-name', $configName, $newValue);
		}

		$this->consoleInput->expects($this->exactly(2))
			->method('getArgument')
			->willReturnMap([
				['app', 'app-name'],
				['name', $configName],
			]);
		$this->consoleInput->expects($this->any())
			->method('getOption')
			->with('value')
			->willReturn($newValue);
		$this->consoleInput->expects($this->any())
			->method('hasParameterOption')
			->with('--update-only')
			->willReturn($updateOnly);

		$this->consoleOutput->expects($this->any())
			->method('writeln')
			->with($this->stringContains($expectedMessage));

		$this->invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}
}
