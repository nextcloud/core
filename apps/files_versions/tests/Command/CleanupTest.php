<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_Versions\Tests\Command;

use OC\User\Manager;
use OCA\Files_Versions\Command\CleanUp;
use OCP\Files\IRootFolder;
use Test\TestCase;

/**
 * Class CleanupTest
 *
 * @group DB
 *
 * @package OCA\Files_Versions\Tests\Command
 */
class CleanupTest extends TestCase {

	/** @var  CleanUp */
	protected $cleanup;

	/** @var \PHPUnit\Framework\MockObject\MockObject | Manager */
	protected $userManager;

	/** @var \PHPUnit\Framework\MockObject\MockObject | IRootFolder */
	protected $rootFolder;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->getMockBuilder('OCP\Files\IRootFolder')
			->disableOriginalConstructor()->getMock();
		$this->userManager = $this->getMockBuilder('OC\User\Manager')
			->disableOriginalConstructor()->getMock();


		$this->cleanup = new CleanUp($this->rootFolder, $this->userManager);
	}

	/**
	 * @dataProvider dataTestDeleteVersions
	 * @param boolean $nodeExists
	 */
	public function testDeleteVersions($nodeExists) {
		$this->rootFolder->expects($this->once())
			->method('nodeExists')
			->with('/testUser/files_versions')
			->willReturn($nodeExists);


		if ($nodeExists) {
			$this->rootFolder->expects($this->once())
				->method('get')
				->with('/testUser/files_versions')
				->willReturn($this->rootFolder);
			$this->rootFolder->expects($this->once())
				->method('delete');
		} else {
			$this->rootFolder->expects($this->never())
				->method('get');
			$this->rootFolder->expects($this->never())
				->method('delete');
		}

		$this->invokePrivate($this->cleanup, 'deleteVersions', ['testUser']);
	}

	public function dataTestDeleteVersions() {
		return [
			[true],
			[false]
		];
	}


	/**
	 * test delete versions from users given as parameter
	 */
	public function testExecuteDeleteListOfUsers() {
		$userIds = ['user1', 'user2', 'user3'];

		$instance = $this->getMockBuilder('OCA\Files_Versions\Command\CleanUp')
			->setMethods(['deleteVersions'])
			->setConstructorArgs([$this->rootFolder, $this->userManager])
			->getMock();
		$instance->expects($this->exactly(count($userIds)))
			->method('deleteVersions')
			->willReturnCallback(function ($user) use ($userIds) {
				$this->assertTrue(in_array($user, $userIds));
			});

		$this->userManager->expects($this->exactly(count($userIds)))
			->method('userExists')->willReturn(true);

		$inputInterface = $this->getMockBuilder('\Symfony\Component\Console\Input\InputInterface')
			->disableOriginalConstructor()->getMock();
		$inputInterface->expects($this->once())->method('getArgument')
			->with('user_id')
			->willReturn($userIds);

		$outputInterface = $this->getMockBuilder('\Symfony\Component\Console\Output\OutputInterface')
			->disableOriginalConstructor()->getMock();

		$this->invokePrivate($instance, 'execute', [$inputInterface, $outputInterface]);
	}

	/**
	 * test delete versions of all users
	 */
	public function testExecuteAllUsers() {
		$userIds = [];
		$backendUsers = ['user1', 'user2'];

		$instance = $this->getMockBuilder('OCA\Files_Versions\Command\CleanUp')
			->setMethods(['deleteVersions'])
			->setConstructorArgs([$this->rootFolder, $this->userManager])
			->getMock();

		$backend = $this->getMockBuilder(\OCP\UserInterface::class)
			->disableOriginalConstructor()->getMock();
		$backend->expects($this->once())->method('getUsers')
			->with('', 500, 0)
			->willReturn($backendUsers);

		$instance->expects($this->exactly(count($backendUsers)))
			->method('deleteVersions')
			->willReturnCallback(function ($user) use ($backendUsers) {
				$this->assertTrue(in_array($user, $backendUsers));
			});

		$inputInterface = $this->getMockBuilder('\Symfony\Component\Console\Input\InputInterface')
			->disableOriginalConstructor()->getMock();
		$inputInterface->expects($this->once())->method('getArgument')
			->with('user_id')
			->willReturn($userIds);

		$outputInterface = $this->getMockBuilder('\Symfony\Component\Console\Output\OutputInterface')
			->disableOriginalConstructor()->getMock();

		$this->userManager->expects($this->once())
				->method('getBackends')
				->willReturn([$backend]);

		$this->invokePrivate($instance, 'execute', [$inputInterface, $outputInterface]);
	}
}
