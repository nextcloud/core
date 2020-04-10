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

use OC\Core\Command\Group\Add;
use OCP\IGroup;
use OCP\IGroupManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class AddTest extends TestCase {

	/** @var IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;

	/** @var Add */
	private $command;

	/** @var InputInterface|\PHPUnit_Framework_MockObject_MockObject */
	private $input;

	/** @var OutputInterface|\PHPUnit_Framework_MockObject_MockObject */
	private $output;

	protected function setUp(): void {
		parent::setUp();

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->command = new Add($this->groupManager);

		$this->input = $this->createMock(InputInterface::class);
		$this->input->method('getArgument')
			->willReturnCallback(function ($arg) {
				if ($arg === 'groupid') {
					return 'myGroup';
				}
				throw new \Exception();
			});
		$this->output = $this->createMock(OutputInterface::class);
	}

	public function testGroupExists() {
		$gid = 'myGroup';
		$group = $this->createMock(IGroup::class);
		$this->groupManager->method('get')
			->with($gid)
			->willReturn($group);

		$this->groupManager->expects($this->never())
			->method('createGroup');
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<error>Group "' . $gid . '" already exists.</error>'));

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testAdd() {
		$gid = 'myGroup';
		$group = $this->createMock(IGroup::class);
		$group->method('getGID')
			->willReturn($gid);
		$this->groupManager->method('createGroup')
			->willReturn($group);

		$this->groupManager->expects($this->once())
			->method('createGroup')
			->with($this->equalTo($gid));
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('Created group "' . $group->getGID() . '"'));

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}


}
