<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace Test\Authentication\TwoFactorAuth;

use OC\Authentication\TwoFactorAuth\Db\ProviderUserAssignmentDao;
use OC\Authentication\TwoFactorAuth\Registry;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\Authentication\TwoFactorAuth\RegistryEvent;
use OCP\Authentication\TwoFactorAuth\TwoFactorProviderDisabled;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class RegistryTest extends TestCase {

	/** @var ProviderUserAssignmentDao|MockObject */
	private $dao;

	/** @var IEventDispatcher|MockObject */
	private $dispatcher;

	/** @var Registry */
	private $registry;

	protected function setUp(): void {
		parent::setUp();

		$this->dao = $this->createMock(ProviderUserAssignmentDao::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);

		$this->registry = new Registry($this->dao, $this->dispatcher);
	}

	public function testGetProviderStates() {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())->method('getUID')->willReturn('user123');
		$state = [
			'twofactor_totp' => true,
		];
		$this->dao->expects($this->once())->method('getState')->willReturn($state);

		$actual = $this->registry->getProviderStates($user);

		$this->assertEquals($state, $actual);
	}

	public function testEnableProvider() {
		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IProvider::class);
		$user->expects($this->once())->method('getUID')->willReturn('user123');
		$provider->expects($this->once())->method('getId')->willReturn('p1');
		$this->dao->expects($this->once())->method('persist')->with('p1', 'user123',
			true);

		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with(
				$this->equalTo(IRegistry::EVENT_PROVIDER_ENABLED),
				$this->callback(function (RegistryEvent $e) use ($user, $provider) {
					return $e->getUser() === $user && $e->getProvider() === $provider;
				})
			);

		$this->registry->enableProviderFor($provider, $user);
	}

	public function testDisableProvider() {
		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IProvider::class);
		$user->expects($this->once())->method('getUID')->willReturn('user123');
		$provider->expects($this->once())->method('getId')->willReturn('p1');
		$this->dao->expects($this->once())->method('persist')->with('p1', 'user123',
			false);


		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with(
				$this->equalTo(IRegistry::EVENT_PROVIDER_DISABLED),
				$this->callback(function (RegistryEvent $e) use ($user, $provider) {
					return $e->getUser() === $user && $e->getProvider() === $provider;
				})
			);

		$this->registry->disableProviderFor($provider, $user);
	}

	public function testDeleteUserData() {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())->method('getUID')->willReturn('user123');
		$this->dao->expects($this->once())
			->method('deleteByUser')
			->with('user123')
			->willReturn([
				[
					'provider_id' => 'twofactor_u2f',
				]
			]);
		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with(new TwoFactorProviderDisabled('twofactor_u2f'));

		$this->registry->deleteUserData($user);
	}

	public function testCleanUp() {
		$this->dao->expects($this->once())
			->method('deleteAll')
			->with('twofactor_u2f');

		$this->registry->cleanUp('twofactor_u2f');
	}
}
