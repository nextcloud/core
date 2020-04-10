<?php
/**
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

namespace Tests\Core\Controller;

use OC\HintException;
use OC\User\Session;
use OCA\Settings\Controller\ChangePasswordController;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;

class ChangePasswordControllerTest extends \Test\TestCase {
	/** @var string */
	private $userId = 'currentUser';
	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	private $userManager;
	/** @var Session|\PHPUnit_Framework_MockObject_MockObject */
	private $userSession;
	/** @var IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;
	/** @var IAppManager|\PHPUnit_Framework_MockObject_MockObject */
	private $appManager;
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l;
	/** @var ChangePasswordController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(\OC\User\Manager::class);
		$this->userSession = $this->createMock(Session::class);
		$this->groupManager = $this->createMock(\OC\Group\Manager::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->l = $this->createMock(IL10N::class);
		$this->l->method('t')->willReturnArgument(0);

		/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject $request */
		$request = $this->createMock(IRequest::class);

		$this->controller = new ChangePasswordController(
			'core',
			$request,
			$this->userId,
			$this->userManager,
			$this->userSession,
			$this->groupManager,
			$this->appManager,
			$this->l
		);
	}

	public function testChangePersonalPasswordWrongPassword() {
		$this->userManager->expects($this->once())
			->method('checkPassword')
			->with($this->userId, 'old')
			->willReturn(false);

		$expects = new JSONResponse([
			'status' => 'error',
			'data' => [
				'message' => 'Wrong password',
			],
		]);
		$expects->throttle();

		$actual = $this->controller->changePersonalPassword('old', 'new');
		$this->assertEquals($expects, $actual);
	}

	public function testChangePersonalPasswordCommonPassword() {
		$user = $this->getMockBuilder(IUser::class)->getMock();
		$this->userManager->expects($this->once())
			->method('checkPassword')
			->with($this->userId, 'old')
			->willReturn($user);

		$user->expects($this->once())
			->method('setPassword')
			->with('new')
			->will($this->throwException(new HintException('Common password')));

		$expects = new JSONResponse([
			'status' => 'error',
			'data' => [
				'message' => 'Common password',
			],
		]);

		$actual = $this->controller->changePersonalPassword('old', 'new');
		$this->assertEquals($expects, $actual);
	}

	public function testChangePersonalPasswordNoNewPassword() {
		$user = $this->getMockBuilder(IUser::class)->getMock();
		$this->userManager->expects($this->once())
			->method('checkPassword')
			->with($this->userId, 'old')
			->willReturn($user);

		$expects = [
			'status' => 'error',
		];

		$res = $this->controller->changePersonalPassword('old');

		$this->assertEquals($expects, $res->getData());
	}

	public function testChangePersonalPasswordCantSetPassword() {
		$user = $this->getMockBuilder(IUser::class)->getMock();
		$this->userManager->expects($this->once())
			->method('checkPassword')
			->with($this->userId, 'old')
			->willReturn($user);

		$user->expects($this->once())
			->method('setPassword')
			->with('new')
			->willReturn(false);

		$expects = new JSONResponse([
			'status' => 'error',
		]);

		$actual = $this->controller->changePersonalPassword('old', 'new');
		$this->assertEquals($expects, $actual);
	}

	public function testChangePersonalPassword() {
		$user = $this->getMockBuilder(IUser::class)->getMock();
		$this->userManager->expects($this->once())
			->method('checkPassword')
			->with($this->userId, 'old')
			->willReturn($user);

		$user->expects($this->once())
			->method('setPassword')
			->with('new')
			->willReturn(true);

		$this->userSession->expects($this->once())
			->method('updateSessionTokenPassword')
			->with('new');

		$expects = new JSONResponse([
			'status' => 'success',
			'data' => [
				'message' => 'Saved',
			],
		]);

		$actual = $this->controller->changePersonalPassword('old', 'new');
		$this->assertEquals($expects, $actual);
	}
}
