<?php
/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\files_external\tests\Config;

use OCA\Files_External\Config\UserPlaceholderHandler;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;

class UserPlaceholderHandlerTest extends \Test\TestCase {
	/** @var IUser|\PHPUnit_Framework_MockObject_MockObject */
	protected $user;

	/** @var IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	protected $session;

	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $shareManager;

	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;

	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	private $userManager;

	/** @var UserPlaceholderHandler */
	protected $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock(IUser::class);
		$this->user->expects($this->any())
			->method('getUid')
			->willReturn('alice');
		$this->session = $this->createMock(IUserSession::class);
		$this->shareManager = $this->createMock(IManager::class);
		$this->request = $this->createMock(IRequest::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->handler = new UserPlaceholderHandler($this->session, $this->shareManager, $this->request, $this->userManager);
	}

	protected function setUser() {
		$this->session->expects($this->any())
			->method('getUser')
			->willReturn($this->user);
	}

	public function optionProvider() {
		return [
			['/foo/bar/$user/foobar', '/foo/bar/alice/foobar'],
			[['/foo/bar/$user/foobar'], ['/foo/bar/alice/foobar']],
			[['/FOO/BAR/$USER/FOOBAR'], ['/FOO/BAR/alice/FOOBAR']],
		];
	}

	/**
	 * @dataProvider optionProvider
	 */
	public function testHandle($option, $expected) {
		$this->setUser();
		$this->assertSame($expected, $this->handler->handle($option));
	}

	/**
	 * @dataProvider optionProvider
	 */
	public function testHandleNoUser($option) {
		$this->shareManager->expects($this->once())
			->method('getShareByToken')
			->willThrowException(new ShareNotFound());
		$this->assertSame($option, $this->handler->handle($option));
	}

}
