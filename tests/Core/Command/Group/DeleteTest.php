<?php
/**
 * @copyright 2018, Denis Mosolov <denismosolov@gmail.com>
 *
 * @author Denis Mosolov <denismosolov@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Afferoq General Public License as
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

use OC\Core\Command\Group\Delete;
use OCP\IGroup;
use OCP\IGroupManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class DeleteTest extends TestCase {

	/** @var IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;

	/** @var Delete */
	private $command;

	/** @var InputInterface|\PHPUnit_Framework_MockObject_MockObject */
	private $input;

	/** @var OutputInterface|\PHPUnit_Framework_MockObject_MockObject */
	private $output;

	protected function setUp(): void {
		parent::setUp();

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->command = new Delete($this->groupManager);

		$this->input = $this->createMock(InputInterface::class);
		$this->output = $this->createMock(OutputInterface::class);
	}

	public function testDoesNotExists() {
		$gid = 'myGroup';
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) use ($gid) {
				if ($arg === 'groupid') {
					return $gid;
				}
				throw new \Exception();
			});
		$this->groupManager->method('groupExists')
			->with($gid)
			->willReturn(false);

		$this->groupManager->expects($this->never())
			->method('get');
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Group "' . $gid . '" does not exist.</error>'));

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testDeleteAdmin() {
		$gid = 'admin';
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) use ($gid) {
				if ($arg === 'groupid') {
					return $gid;
				}
				throw new \Exception();
			});

		$this->groupManager->expects($this->never())
			->method($this->anything());
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Group "' . $gid . '" could not be deleted.</error>'));

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testDeleteFailed() {
		$gid = 'myGroup';
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) use ($gid) {
				if ($arg === 'groupid') {
					return $gid;
				}
				throw new \Exception();
			});
		$group = $this->createMock(IGroup::class);
		$group->method('delete')
			->willReturn(false);
		$this->groupManager->method('groupExists')
			->with($gid)
			->willReturn(true);
		$this->groupManager->method('get')
			->with($gid)
			->willReturn($group);

		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Group "' . $gid . '" could not be deleted. Please check the logs.</error>'));

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testDelete() {
		$gid = 'myGroup';
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) use ($gid) {
				if ($arg === 'groupid') {
					return $gid;
				}
				throw new \Exception();
			});
		$group = $this->createMock(IGroup::class);
		$group->method('delete')
			->willReturn(true);
		$this->groupManager->method('groupExists')
			->with($gid)
			->willReturn(true);
		$this->groupManager->method('get')
			->with($gid)
			->willReturn($group);

		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('Group "' . $gid . '" was removed'));

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}
}
