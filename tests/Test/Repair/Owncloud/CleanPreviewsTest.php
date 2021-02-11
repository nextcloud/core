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
namespace Test\Repair\Owncloud;

use OC\Repair\Owncloud\CleanPreviews;
use OC\Repair\Owncloud\CleanPreviewsBackgroundJob;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use Test\TestCase;

class CleanPreviewsTest extends TestCase {


	/** @var IJobList|\PHPUnit_Framework_MockObject_MockObject */
	private $jobList;

	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	private $userManager;

	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;

	/** @var CleanPreviews */
	private $repair;

	public function setUp() {
		parent::setUp();

		$this->jobList = $this->createMock(IJobList::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->config = $this->createMock(IConfig::class);

		$this->repair = new CleanPreviews(
			$this->jobList,
			$this->userManager,
			$this->config
		);
	}

	public function testGetName() {
		$this->assertSame('Add preview cleanup background jobs', $this->repair->getName());
	}

	public function testRun() {
		$user1 = $this->createMock(IUser::class);
		$user1->method('getUID')
			->willReturn('user1');
		$user2 = $this->createMock(IUser::class);
		$user2->method('getUID')
			->willReturn('user2');

		$this->userManager->expects($this->once())
			->method('callForSeenUsers')
			->will($this->returnCallback(function (\Closure $function) use ($user1, $user2) {
				$function($user1);
				$function($user2);
			}));

		$this->jobList->expects($this->at(0))
			->method('add')
			->with(
				$this->equalTo(CleanPreviewsBackgroundJob::class),
				$this->equalTo(['uid' => 'user1'])
			);

		$this->jobList->expects($this->at(1))
			->method('add')
			->with(
				$this->equalTo(CleanPreviewsBackgroundJob::class),
				$this->equalTo(['uid' => 'user2'])
			);

		$this->config->expects($this->once())
			->method('getAppValue')
			->with(
				$this->equalTo('core'),
				$this->equalTo('previewsCleanedUp'),
				$this->equalTo(false)
			)->willReturn(false);
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				$this->equalTo('core'),
				$this->equalTo('previewsCleanedUp'),
				$this->equalTo(1)
			);

		$this->repair->run($this->createMock(IOutput::class));
	}


	public function testRunAlreadyDoone() {
		$this->userManager->expects($this->never())
			->method($this->anything());

		$this->jobList->expects($this->never())
			->method($this->anything());

		$this->config->expects($this->once())
			->method('getAppValue')
			->with(
				$this->equalTo('core'),
				$this->equalTo('previewsCleanedUp'),
				$this->equalTo(false)
			)->willReturn('1');
		$this->config->expects($this->never())
			->method('setAppValue');

		$this->repair->run($this->createMock(IOutput::class));
	}
}
