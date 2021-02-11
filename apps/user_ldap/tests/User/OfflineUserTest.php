<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\User_LDAP\Tests\User;

use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\User\OfflineUser;
use OCP\IConfig;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Test\TestCase;

class OfflineUserTest extends TestCase {

	/** @var OfflineUser */
	protected $offlineUser;
	/** @var UserMapping|\PHPUnit\Framework\MockObject\MockObject */
	protected $mapping;
	/** @var string */
	protected $uid;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;
	/** @var IManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $shareManager;

	public function setUp(): void {
		$this->uid = 'deborah';
		$this->config = $this->createMock(IConfig::class);
		$this->mapping = $this->createMock(UserMapping::class);
		$this->shareManager = $this->createMock(IManager::class);

		$this->offlineUser = new OfflineUser(
			$this->uid,
			$this->config,
			$this->mapping,
			$this->shareManager
		);
	}

	public function shareOwnerProvider(): array {
		return [
			[[], false],
			[[IShare::TYPE_USER], true],
			[[IShare::TYPE_GROUP, IShare::TYPE_LINK], true],
			[[IShare::TYPE_EMAIL, IShare::TYPE_REMOTE, IShare::TYPE_CIRCLE], true],
			[[IShare::TYPE_GUEST, IShare::TYPE_REMOTE_GROUP, IShare::TYPE_ROOM], true],
		];
	}

	/**
	 * @dataProvider shareOwnerProvider
	 */
	public function testHasActiveShares(array $existingShareTypes, bool $expected) {
		$shareMock = $this->createMock(IShare::class);

		$this->shareManager->expects($this->atLeastOnce())
			->method('getSharesBy')
			->willReturnCallback(function (string $uid, int $shareType) use ($existingShareTypes, $shareMock) {
				if (in_array($shareType, $existingShareTypes)) {
					return [$shareMock];
				}
				return [];
			});

		$this->assertSame($expected, $this->offlineUser->getHasActiveShares());
	}
}
