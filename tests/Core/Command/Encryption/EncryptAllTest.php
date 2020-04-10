<?php
/**
 * @author Björn Schießle <schiessle@owncloud.com>
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

use OC\Core\Command\Encryption\EncryptAll;
use OCP\App\IAppManager;
use OCP\Encryption\IEncryptionModule;
use OCP\Encryption\IManager;
use OCP\IConfig;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class EncryptAllTest extends TestCase {

	/** @var \PHPUnit_Framework_MockObject_MockObject | \OCP\IConfig */
	protected $config;

	/** @var \PHPUnit_Framework_MockObject_MockObject | \OCP\Encryption\IManager  */
	protected $encryptionManager;

	/** @var \PHPUnit_Framework_MockObject_MockObject | \OCP\App\IAppManager  */
	protected $appManager;

	/** @var \PHPUnit_Framework_MockObject_MockObject  | \Symfony\Component\Console\Input\InputInterface */
	protected $consoleInput;

	/** @var \PHPUnit_Framework_MockObject_MockObject | \Symfony\Component\Console\Output\OutputInterface */
	protected $consoleOutput;

	/** @var \PHPUnit_Framework_MockObject_MockObject | \Symfony\Component\Console\Helper\QuestionHelper */
	protected $questionHelper;

	/** @var \PHPUnit_Framework_MockObject_MockObject | \OCP\Encryption\IEncryptionModule */
	protected $encryptionModule;

	/** @var  EncryptAll */
	protected $command;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$this->encryptionManager = $this->getMockBuilder(IManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->appManager = $this->getMockBuilder(IAppManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->encryptionModule = $this->getMockBuilder(IEncryptionModule::class)
			->disableOriginalConstructor()
			->getMock();
		$this->questionHelper = $this->getMockBuilder(QuestionHelper::class)
			->disableOriginalConstructor()
			->getMock();
		$this->consoleInput = $this->getMockBuilder(InputInterface::class)->getMock();
		$this->consoleInput->expects($this->any())
			->method('isInteractive')
			->willReturn(true);
		$this->consoleOutput = $this->getMockBuilder(OutputInterface::class)->getMock();

	}

	public function testEncryptAll() {
		// trash bin needs to be disabled in order to avoid adding dummy files to the users
		// trash bin which gets deleted during the encryption process
		$this->appManager->expects($this->once())->method('disableApp')->with('files_trashbin');
		// enable single user mode to avoid that other user login during encryption
		// destructor should disable the single user mode again
		$this->config->expects($this->once())->method('getSystemValueBool')->with('maintenance', false)->willReturn(false);
		$this->config->expects($this->at(1))->method('setSystemValue')->with('maintenance', true);
		$this->config->expects($this->at(2))->method('setSystemValue')->with('maintenance', false);

		$instance = new EncryptAll($this->encryptionManager, $this->appManager, $this->config, $this->questionHelper);
		$this->invokePrivate($instance, 'forceMaintenanceAndTrashbin');
		$this->invokePrivate($instance, 'resetMaintenanceAndTrashbin');
	}

	/**
	 * @dataProvider dataTestExecute
	 */
	public function testExecute($answer, $askResult) {

		$command = new EncryptAll($this->encryptionManager, $this->appManager, $this->config, $this->questionHelper);

		$this->encryptionManager->expects($this->once())->method('isEnabled')->willReturn(true);
		$this->questionHelper->expects($this->once())->method('ask')->willReturn($askResult);

		if ($answer === 'Y' || $answer === 'y') {
			$this->encryptionManager->expects($this->once())
				->method('getEncryptionModule')->willReturn($this->encryptionModule);
			$this->encryptionModule->expects($this->once())
				->method('encryptAll')->with($this->consoleInput, $this->consoleOutput);
		} else {
			$this->encryptionManager->expects($this->never())->method('getEncryptionModule');
			$this->encryptionModule->expects($this->never())->method('encryptAll');
		}

		$this->invokePrivate($command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

	public function dataTestExecute() {
		return [
			['y', true], ['Y', true], ['n', false], ['N', false], ['', false]
		];
	}

	
	public function testExecuteException() {
		$this->expectException(\Exception::class);

		$command = new EncryptAll($this->encryptionManager, $this->appManager, $this->config, $this->questionHelper);
		$this->encryptionManager->expects($this->once())->method('isEnabled')->willReturn(false);
		$this->encryptionManager->expects($this->never())->method('getEncryptionModule');
		$this->encryptionModule->expects($this->never())->method('encryptAll');
		$this->invokePrivate($command, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

}
