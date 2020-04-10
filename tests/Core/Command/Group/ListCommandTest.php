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

use OC\Core\Command\Group\ListCommand;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class ListCommandTest extends TestCase {

	/** @var IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;

	/** @var ListCommand|\PHPUnit_Framework_MockObject_MockObject */
	private $command;

	/** @var InputInterface|\PHPUnit_Framework_MockObject_MockObject */
	private $input;

	/** @var OutputInterface|\PHPUnit_Framework_MockObject_MockObject */
	private $output;

	protected function setUp(): void {
		parent::setUp();

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->command = $this->getMockBuilder(ListCommand::class)
			->setConstructorArgs([$this->groupManager])
			->setMethods(['writeArrayInOutputFormat'])
			->getMock();

		$this->input = $this->createMock(InputInterface::class);
		$this->input->method('getOption')
			->willReturnCallback(function ($arg) {
				if ($arg === 'limit') {
					return '100';
				} else if ($arg === 'offset') {
					return '42';
				}
				throw new \Exception();
			});


		$this->output = $this->createMock(OutputInterface::class);
	}

	public function testExecute() {
		$group1 = $this->createMock(IGroup::class);
		$group1->method('getGID')->willReturn('group1');
		$group2 = $this->createMock(IGroup::class);
		$group2->method('getGID')->willReturn('group2');
		$group3 = $this->createMock(IGroup::class);
		$group3->method('getGID')->willReturn('group3');

		$user = $this->createMock(IUser::class);

		$this->groupManager->method('search')
			->with(
				'',
				100,
				42
			)->willReturn([$group1, $group2, $group3]);

		$group1->method('getUsers')
			->willReturn([
				'user1' => $user,
				'user2' => $user,
			]);

		$group2->method('getUsers')
			->willReturn([
			]);

		$group3->method('getUsers')
			->willReturn([
				'user1' => $user,
				'user3' => $user,
			]);

		$this->command->expects($this->once())
			->method('writeArrayInOutputFormat')
			->with(
				$this->equalTo($this->input),
				$this->equalTo($this->output),
				[
					'group1' => [
						'user1',
						'user2',
					],
					'group2' => [
					],
					'group3' => [
						'user1',
						'user3',
					]
				]
			);

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}


}
