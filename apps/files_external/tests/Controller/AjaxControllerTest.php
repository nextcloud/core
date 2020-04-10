<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files_External\Tests\Controller;

use OCA\Files_External\Controller\AjaxController;
use OCA\Files_External\Lib\Auth\Password\GlobalAuth;
use OCA\Files_External\Lib\Auth\PublicKey\RSA;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use Test\TestCase;

class AjaxControllerTest extends TestCase {
	/** @var IRequest */
	private $request;
	/** @var RSA */
	private $rsa;
	/** @var GlobalAuth */
	private $globalAuth;
	/** @var IUserSession */
	private $userSession;
	/** @var IGroupManager */
	private $groupManager;
	/** @var AjaxController */
	private $ajaxController;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->rsa = $this->getMockBuilder('\\OCA\\Files_External\\Lib\\Auth\\PublicKey\\RSA')
			->disableOriginalConstructor()
			->getMock();
		$this->globalAuth = $this->getMockBuilder('\\OCA\\Files_External\\Lib\\Auth\\Password\GlobalAuth')
			->disableOriginalConstructor()
			->getMock();
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		$this->ajaxController = new AjaxController(
			'files_external',
			$this->request,
			$this->rsa,
			$this->globalAuth,
			$this->userSession,
			$this->groupManager
		);

		parent::setUp();
	}

	public function testGetSshKeys() {
		$this->rsa
			->expects($this->once())
			->method('createKey')
			->willReturn([
				'privatekey' => 'MyPrivateKey',
				'publickey' => 'MyPublicKey',
			]);

		$expected =  new JSONResponse(
			[
				'data' => [
					'private_key' => 'MyPrivateKey',
					'public_key' => 'MyPublicKey',
				],
				'status' => 'success',
			]
		);
		$this->assertEquals($expected, $this->ajaxController->getSshKeys());
	}

	public function testSaveGlobalCredentialsAsAdminForAnotherUser() {
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('MyAdminUid');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('MyAdminUid')
			->willReturn(true);
		$this->globalAuth
			->expects($this->once())
			->method('saveAuth')
			->with('UidOfTestUser', 'test', 'password');

		$this->assertSame(true, $this->ajaxController->saveGlobalCredentials('UidOfTestUser', 'test', 'password'));
	}

	public function testSaveGlobalCredentialsAsAdminForSelf() {
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('MyAdminUid');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('MyAdminUid')
			->willReturn(true);
		$this->globalAuth
			->expects($this->once())
			->method('saveAuth')
			->with('MyAdminUid', 'test', 'password');

		$this->assertSame(true, $this->ajaxController->saveGlobalCredentials('MyAdminUid', 'test', 'password'));
	}

	public function testSaveGlobalCredentialsAsNormalUserForSelf() {
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->exactly(2))
			->method('getUID')
			->willReturn('MyUserUid');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('MyUserUid')
			->willReturn(false);
		$this->globalAuth
			->expects($this->once())
			->method('saveAuth')
			->with('MyUserUid', 'test', 'password');

		$this->assertSame(true, $this->ajaxController->saveGlobalCredentials('MyUserUid', 'test', 'password'));
	}

	public function testSaveGlobalCredentialsAsNormalUserForAnotherUser() {
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->exactly(2))
			->method('getUID')
			->willReturn('MyUserUid');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('MyUserUid')
			->willReturn(false);

		$this->assertSame(false, $this->ajaxController->saveGlobalCredentials('AnotherUserUid', 'test', 'password'));
	}
}
