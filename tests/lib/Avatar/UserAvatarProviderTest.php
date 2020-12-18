<?php
/**
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Lukas Reschke <lukas@statuscode.ch>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\Avatar;

use OC\Avatar\UserAvatar;
use OC\Avatar\UserAvatarProvider;
use OC\Files\AppData\AppData;
use OC\Files\AppData\Factory as AppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory as L10NFactory;
use Psr\Log\LoggerInterface;

/**
 * Class UserAvatarProviderTest
 */
class UserAvatarProviderTest extends \Test\TestCase {
	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;
	/** @var IAppData|\PHPUnit\Framework\MockObject\MockObject */
	private $appData;
	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;
	/** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $currentUser;
	/** @var UserAvatarProvider | \PHPUnit\Framework\MockObject\MockObject */
	private $userAvatarProvider;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		// The specific subclass rather than the interface needs to be mocked so
		// PHPUnit does not complain about the returned type from the mocked
		// "AppDataFactory::get".
		$this->appData = $this->createMock(AppData::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->config = $this->createMock(IConfig::class);
		$this->currentUser = $this->createMock(IUser::class);

		$appDataFactory = $this->createMock(AppDataFactory::class);
		$appDataFactory
			->method('get')
			->with('avatar')
			->willReturn($this->appData);
		$l10nFactory = $this->createMock(L10NFactory::class);
		$l10nFactory
			->method('get')
			->with('lib')
			->willReturn($this->l10n);
		$userSession = $this->createMock(IUserSession::class);
		$userSession
			->method('getUser')
			->willReturn($this->currentUser);

		$this->userAvatarProvider = new UserAvatarProvider(
			$this->userManager,
			$appDataFactory,
			$l10nFactory,
			$this->logger,
			$this->config,
			$userSession
		);
	}


	public function testGetAvatarInvalidUser() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('user invalidUser does not exist');

		$this->userManager
			->expects($this->once())
			->method('get')
			->with('invalidUser')
			->willReturn(null);

		$this->userAvatarProvider->getAvatar('invalidUser');
	}

	public function testGetAvatarValidUser() {
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('valid-user');
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('valid-user')
			->willReturn($user);
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('valid-user')
			->willReturn($folder);

		$expected = new UserAvatar($folder, $this->l10n, $user, $this->logger, $this->config);
		$this->assertEquals($expected, $this->userAvatarProvider->getAvatar('valid-user'));
	}

	public function testGetAvatarValidUserDifferentCasing() {
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('vaLid-USER')
			->willReturn($user);

		$user->expects($this->once())
			->method('getUID')
			->willReturn('valid-user');

		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('valid-user')
			->willReturn($folder);

		$expected = new UserAvatar($folder, $this->l10n, $user, $this->logger, $this->config);
		$this->assertEquals($expected, $this->userAvatarProvider->getAvatar('vaLid-USER'));
	}
}
