<?php
/**
 * @copyright 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Core\Command\Group;

use OC\Core\Command\Group\AddUser;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class AddUserTest extends TestCase {

	/** @var IGroupManager|\PHPUnit\Framework\MockObject\MockObject */
	private $groupManager;

	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;

	/** @var AddUser */
	private $command;

	/** @var InputInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $input;

	/** @var OutputInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $output;

	protected function setUp(): void {
		parent::setUp();

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->command = new AddUser($this->userManager, $this->groupManager);

		$this->input = $this->createMock(InputInterface::class);
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'group') {
					return 'myGroup';
				} elseif ($arg === 'user') {
					return 'myUser';
				}
				throw new \Exception();
			});
		$this->output = $this->createMock(OutputInterface::class);
	}

	public function testNoGroup() {
		$this->groupManager->method('get')
			->with('myGroup')
			->willReturn(null);

		$this->output->expects($this->once())
			->method('writeln')
			->with('<error>group not found</error>');

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testNoUser() {
		$group = $this->createMock(IGroup::class);
		$this->groupManager->method('get')
			->with('myGroup')
			->willReturn($group);

		$this->userManager->method('get')
			->with('myUser')
			->willReturn(null);

		$this->output->expects($this->once())
			->method('writeln')
			->with('<error>user not found</error>');

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testAdd() {
		$group = $this->createMock(IGroup::class);
		$this->groupManager->method('get')
			->with('myGroup')
			->willReturn($group);

		$user = $this->createMock(IUser::class);
		$this->userManager->method('get')
			->with('myUser')
			->willReturn($user);

		$group->expects($this->once())
			->method('addUser')
			->with($user);

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}
}
