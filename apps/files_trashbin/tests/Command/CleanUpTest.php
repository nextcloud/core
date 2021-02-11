<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Files_Trashbin\Tests\Command;

use OC\User\Manager;
use OCA\Files_Trashbin\Command\CleanUp;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

/**
 * Class CleanUpTest
 *
 * @group DB
 *
 * @package OCA\Files_Trashbin\Tests\Command
 */
class CleanUpTest extends TestCase {

	/** @var  CleanUp */
	protected $cleanup;

	/** @var \PHPUnit\Framework\MockObject\MockObject | Manager */
	protected $userManager;

	/** @var \PHPUnit\Framework\MockObject\MockObject | IRootFolder */
	protected $rootFolder;

	/** @var IDBConnection */
	protected $dbConnection;

	/** @var  string */
	protected $trashTable = 'files_trash';

	/** @var string  */
	protected $user0 = 'user0';

	protected function setUp(): void {
		parent::setUp();
		$this->rootFolder = $this->getMockBuilder('OCP\Files\IRootFolder')
			->disableOriginalConstructor()->getMock();
		$this->userManager = $this->getMockBuilder('OC\User\Manager')
			->disableOriginalConstructor()->getMock();

		$this->dbConnection = \OC::$server->getDatabaseConnection();

		$this->cleanup = new CleanUp($this->rootFolder, $this->userManager, $this->dbConnection);
	}

	/**
	 * populate files_trash table with 10 dummy values
	 */
	public function initTable() {
		$query = $this->dbConnection->getQueryBuilder();
		$query->delete($this->trashTable)->execute();
		for ($i = 0; $i < 10; $i++) {
			$query->insert($this->trashTable)
				->values([
					'id' => $query->expr()->literal('file'.$i),
					'timestamp' => $query->expr()->literal($i),
					'location' => $query->expr()->literal('.'),
					'user' => $query->expr()->literal('user'.$i % 2)
				])->execute();
		}
		$getAllQuery = $this->dbConnection->getQueryBuilder();
		$result = $getAllQuery->select('id')
			->from($this->trashTable)
			->execute()
			->fetchAll();
		$this->assertSame(10, count($result));
	}

	/**
	 * @dataProvider dataTestRemoveDeletedFiles
	 * @param boolean $nodeExists
	 */
	public function testRemoveDeletedFiles($nodeExists) {
		$this->initTable();
		$this->rootFolder->expects($this->once())
			->method('nodeExists')
			->with('/' . $this->user0 . '/files_trashbin')
			->willReturn($nodeExists);
		if ($nodeExists) {
			$this->rootFolder->expects($this->once())
				->method('get')
				->with('/' . $this->user0 . '/files_trashbin')
				->willReturn($this->rootFolder);
			$this->rootFolder->expects($this->once())
				->method('delete');
		} else {
			$this->rootFolder->expects($this->never())->method('get');
			$this->rootFolder->expects($this->never())->method('delete');
		}
		$this->invokePrivate($this->cleanup, 'removeDeletedFiles', [$this->user0]);

		if ($nodeExists) {
			// if the delete operation was execute only files from user1
			// should be left.
			$query = $this->dbConnection->getQueryBuilder();
			$query->select('user')
				->from($this->trashTable);

			$qResult = $query->execute();
			$result = $qResult->fetchAll();
			$qResult->closeCursor();

			$this->assertSame(5, count($result));
			foreach ($result as $r) {
				$this->assertSame('user1', $r['user']);
			}
		} else {
			// if no delete operation was execute we should still have all 10
			// database entries
			$getAllQuery = $this->dbConnection->getQueryBuilder();
			$result = $getAllQuery->select('id')
				->from($this->trashTable)
				->execute()
				->fetchAll();
			$this->assertSame(10, count($result));
		}
	}
	public function dataTestRemoveDeletedFiles() {
		return [
			[true],
			[false]
		];
	}

	/**
	 * test remove deleted files from users given as parameter
	 */
	public function testExecuteDeleteListOfUsers() {
		$userIds = ['user1', 'user2', 'user3'];
		$instance = $this->getMockBuilder('OCA\Files_Trashbin\Command\CleanUp')
			->setMethods(['removeDeletedFiles'])
			->setConstructorArgs([$this->rootFolder, $this->userManager, $this->dbConnection])
			->getMock();
		$instance->expects($this->exactly(count($userIds)))
			->method('removeDeletedFiles')
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
	 * test remove deleted files of all users
	 */
	public function testExecuteAllUsers() {
		$userIds = [];
		$backendUsers = ['user1', 'user2'];
		$instance = $this->getMockBuilder('OCA\Files_Trashbin\Command\CleanUp')
			->setMethods(['removeDeletedFiles'])
			->setConstructorArgs([$this->rootFolder, $this->userManager, $this->dbConnection])
			->getMock();
		$backend = $this->createMock(\OCP\UserInterface::class);
		$backend->expects($this->once())->method('getUsers')
			->with('', 500, 0)
			->willReturn($backendUsers);
		$instance->expects($this->exactly(count($backendUsers)))
			->method('removeDeletedFiles')
			->willReturnCallback(function ($user) use ($backendUsers) {
				$this->assertTrue(in_array($user, $backendUsers));
			});
		$inputInterface = $this->createMock(InputInterface::class);
		$inputInterface->expects($this->once())->method('getArgument')
			->with('user_id')
			->willReturn($userIds);
		$inputInterface->method('getOption')
			->with('all-users')
			->willReturn(true);
		$outputInterface = $this->createMock(OutputInterface::class);
		$this->userManager->expects($this->once())
			->method('getBackends')
			->willReturn([$backend]);
		$this->invokePrivate($instance, 'execute', [$inputInterface, $outputInterface]);
	}

	public function testExecuteNoUsersAndNoAllUsers() {
		$inputInterface = $this->createMock(InputInterface::class);
		$inputInterface->expects($this->once())->method('getArgument')
			->with('user_id')
			->willReturn([]);
		$inputInterface->method('getOption')
			->with('all-users')
			->willReturn(false);
		$outputInterface = $this->createMock(OutputInterface::class);

		$this->expectException(InvalidOptionException::class);
		$this->expectExceptionMessage('Either specify a user_id or --all-users');

		$this->invokePrivate($this->cleanup, 'execute', [$inputInterface, $outputInterface]);
	}

	public function testExecuteUsersAndAllUsers() {
		$inputInterface = $this->createMock(InputInterface::class);
		$inputInterface->expects($this->once())->method('getArgument')
			->with('user_id')
			->willReturn(['user1', 'user2']);
		$inputInterface->method('getOption')
			->with('all-users')
			->willReturn(true);
		$outputInterface = $this->createMock(OutputInterface::class);

		$this->expectException(InvalidOptionException::class);
		$this->expectExceptionMessage('Either specify a user_id or --all-users');

		$this->invokePrivate($this->cleanup, 'execute', [$inputInterface, $outputInterface]);
	}
}
