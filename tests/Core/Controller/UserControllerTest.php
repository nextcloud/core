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

namespace Test\Core\Controller;

use OC\Core\Controller\UserController;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

class UserControllerTest extends TestCase {

	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	private $userManager;

	/** @var UserController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->controller = new UserController(
			'core',
			$this->createMock(IRequest::class),
			$this->userManager
		);
	}

	public function testGetDisplayNames() {
		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')
			->willReturn('FooDisplay Name');

		$this->userManager
			->method('get')
			->willReturnCallback(function ($uid) use ($user) {
				if ($uid === 'foo') {
					return $user;
				}
				return null;
			});

		$expected = new JSONResponse([
			'users' => [
				'foo' => 'FooDisplay Name',
				'bar' => 'bar',
			],
			'status' => 'success'
		]);

		$result = $this->controller->getDisplayNames(['foo', 'bar']);
		$this->assertEquals($expected, $result);
	}
}
