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

namespace Tests\Core\Command\Encryption;

use OC\Core\Command\Encryption\Enable;
use OCP\Encryption\IManager;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class EnableTest extends TestCase {
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $config;
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $manager;
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
		$manager = $this->manager = $this->getMockBuilder(IManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->consoleInput = $this->getMockBuilder(InputInterface::class)->getMock();
		$this->consoleOutput = $this->getMockBuilder(OutputInterface::class)->getMock();

		/** @var \OCP\IConfig $config */
		/** @var \OCP\Encryption\IManager $manager */
		$this->command = new Enable($config, $manager);
	}


	public function dataEnable() {
		return [
			['no', null, [], true, 'Encryption enabled', 'No encryption module is loaded'],
			['yes', null, [], false, 'Encryption is already enabled', 'No encryption module is loaded'],
			['no', null, ['OC_TEST_MODULE' => []], true, 'Encryption enabled', 'No default module is set'],
			['no', 'OC_NO_MODULE', ['OC_TEST_MODULE' => []], true, 'Encryption enabled', 'The current default module does not exist: OC_NO_MODULE'],
			['no', 'OC_TEST_MODULE', ['OC_TEST_MODULE' => []], true, 'Encryption enabled', 'Default module: OC_TEST_MODULE'],
		];
	}

	/**
	 * @dataProvider dataEnable
	 *
	 * @param string $oldStatus
	 * @param string $defaultModule
	 * @param array $availableModules
	 * @param bool $isUpdating
	 * @param string $expectedString
	 * @param string $expectedDefaultModuleString
	 */
	public function testEnable($oldStatus, $defaultModule, $availableModules, $isUpdating, $expectedString, $expectedDefaultModuleString) {
		$invokeCount = 0;
		$this->config->expects($this->at($invokeCount))
			->method('getAppValue')
			->with('core', 'encryption_enabled', $this->anything())
			->willReturn($oldStatus);
		$invokeCount++;

		if ($isUpdating) {
			$this->config->expects($this->once())
				->method('setAppValue')
				->with('core', 'encryption_enabled', 'yes');
			$invokeCount++;
		}

		$this->manager->expects($this->atLeastOnce())
			->method('getEncryptionModules')
			->willReturn($availableModules);

		if (!empty($availableModules)) {
			$this->config->expects($this->at($invokeCount))
				->method('getAppValue')
				->with('core', 'default_encryption_module', $this->anything())
				->willReturn($defaultModule);
		}

		$this->consoleOutput->expects($this->at(0))
			->method('writeln')
			->with($this->stringContains($expectedString));

		$this->consoleOutput->expects($this->at(1))
			->method('writeln')
			->with('');

		$this->consoleOutput->expects($this->at(2))
			->method('writeln')
			->with($this->stringContains($expectedDefaultModuleString));

		self::invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}
}
