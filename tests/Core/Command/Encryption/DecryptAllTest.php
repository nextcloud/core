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

use OC\Core\Command\Encryption\DecryptAll;
use OCP\App\IAppManager;
use OCP\Encryption\IManager;
use OCP\IConfig;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class DecryptAllTest extends TestCase {

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

	/** @var \PHPUnit_Framework_MockObject_MockObject | \OC\Encryption\DecryptAll */
	protected $decryptAll;

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
		$this->questionHelper = $this->getMockBuilder(QuestionHelper::class)
			->disableOriginalConstructor()
			->getMock();
		$this->decryptAll = $this->getMockBuilder(\OC\Encryption\DecryptAll::class)
			->disableOriginalConstructor()->getMock();
		$this->consoleInput = $this->getMockBuilder(InputInterface::class)->getMock();
		$this->consoleInput->expects($this->any())
			->method('isInteractive')
			->willReturn(true);
		$this->consoleOutput = $this->getMockBuilder(OutputInterface::class)->getMock();

		$this->config->expects($this->any())
			->method('getSystemValue')
			->with('maintenance', false)
			->willReturn(false);
		$this->appManager->expects($this->any())
			->method('isEnabledForUser')
			->with('files_trashbin')->willReturn(true);

	}

	public function testMaintenanceAndTrashbin() {

		// on construct we enable single-user-mode and disable the trash bin
		$this->config->expects($this->at(1))
			->method('setSystemValue')
			->with('maintenance', true);
		$this->appManager->expects($this->once())
			->method('disableApp')
			->with('files_trashbin');

		// on destruct wi disable single-user-mode again and enable the trash bin
		$this->config->expects($this->at(2))
			->method('setSystemValue')
			->with('maintenance', false);
		$this->appManager->expects($this->once())
			->method('enableApp')
			->with('files_trashbin');

		$instance = new DecryptAll(
			$this->encryptionManager,
			$this->appManager,
			$this->config,
			$this->decryptAll,
			$this->questionHelper
		);
		$this->invokePrivate($instance, 'forceMaintenanceAndTrashbin');

		$this->assertTrue(
			$this->invokePrivate($instance, 'wasTrashbinEnabled')
		);

		$this->assertFalse(
			$this->invokePrivate($instance, 'wasMaintenanceModeEnabled')
		);
		$this->invokePrivate($instance, 'resetMaintenanceAndTrashbin');
	}

	/**
	 * @dataProvider dataTestExecute
	 */
	public function testExecute($encryptionEnabled, $continue) {

		$instance = new DecryptAll(
			$this->encryptionManager,
			$this->appManager,
			$this->config,
			$this->decryptAll,
			$this->questionHelper
		);

		$this->encryptionManager->expects($this->once())
			->method('isEnabled')
			->willReturn($encryptionEnabled);

		$this->consoleInput->expects($this->any())
			->method('getArgument')
			->with('user')
			->willReturn('user1');

		if ($encryptionEnabled) {
			$this->config->expects($this->at(1))
				->method('setAppValue')
				->with('core', 'encryption_enabled', 'no');
			$this->questionHelper->expects($this->once())
				->method('ask')
				->willReturn($continue);
			if ($continue) {
				$this->decryptAll->expects($this->once())
					->method('decryptAll')
					->with($this->consoleInput, $this->consoleOutput, 'user1');
			} else {
				$this->decryptAll->expects($this->never())->method('decryptAll');
				$this->config->expects($this->at(2))
					->method('setAppValue')
					->with('core', 'encryption_enabled', 'yes');
			}
		} else {
			$this->config->expects($this->never())->method('setAppValue');
			$this->decryptAll->expects($this->never())->method('decryptAll');
			$this->questionHelper->expects($this->never())->method('ask');
		}

		$this->invokePrivate($instance, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

	public function dataTestExecute() {
		return [
			[true, true],
			[true, false],
			[false, true],
			[false, false]
		];
	}

	
	public function testExecuteFailure() {
		$this->expectException(\Exception::class);

		$instance = new DecryptAll(
			$this->encryptionManager,
			$this->appManager,
			$this->config,
			$this->decryptAll,
			$this->questionHelper
		);

		$this->config->expects($this->at(1))
			->method('setAppValue')
			->with('core', 'encryption_enabled', 'no');

		// make sure that we enable encryption again after a exception was thrown
		$this->config->expects($this->at(4))
			->method('setAppValue')
			->with('core', 'encryption_enabled', 'yes');

		$this->encryptionManager->expects($this->once())
			->method('isEnabled')
			->willReturn(true);

		$this->consoleInput->expects($this->any())
			->method('getArgument')
			->with('user')
			->willReturn('user1');

		$this->questionHelper->expects($this->once())
			->method('ask')
			->willReturn(true);

		$this->decryptAll->expects($this->once())
			->method('decryptAll')
			->with($this->consoleInput, $this->consoleOutput, 'user1')
			->willReturnCallback(function () { throw new \Exception(); });

		$this->invokePrivate($instance, 'execute', [$this->consoleInput, $this->consoleOutput]);
	}

}
