<?php
/**
 * @copyright Copyright (c) 2016 Bjoern Schiessle <bjoern@schiessle.org>
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

namespace Test\Accounts;

use OC\Accounts\AccountManager;
use OC\Accounts\Hooks;
use OCP\Accounts\IAccountManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * Class HooksTest
 *
 * @package Test\Accounts
 * @group DB
 */
class HooksTest extends TestCase {

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var AccountManager|MockObject */
	private $accountManager;

	/** @var Hooks|MockObject */
	private $hooks;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);
		$this->accountManager = $this->getMockBuilder(AccountManager::class)
			->disableOriginalConstructor()->getMock();

		$this->hooks = $this->getMockBuilder(Hooks::class)
			->setConstructorArgs([$this->logger])
			->setMethods(['getAccountManager'])
			->getMock();

		$this->hooks->method('getAccountManager')->willReturn($this->accountManager);
	}

	/**
	 * @dataProvider dataTestChangeUserHook
	 *
	 * @param $params
	 * @param $data
	 * @param $setEmail
	 * @param $setDisplayName
	 * @param $error
	 */
	public function testChangeUserHook($params, $data, $setEmail, $setDisplayName, $error) {
		if ($error) {
			$this->accountManager->expects($this->never())->method('getUser');
			$this->accountManager->expects($this->never())->method('updateUser');
		} else {
			$this->accountManager->expects($this->once())->method('getUser')->willReturn($data);
			$newData = $data;
			if ($setEmail) {
				$newData[IAccountManager::PROPERTY_EMAIL]['value'] = $params['value'];
				$this->accountManager->expects($this->once())->method('updateUser')
					->with($params['user'], $newData);
			} elseif ($setDisplayName) {
				$newData[IAccountManager::PROPERTY_DISPLAYNAME]['value'] = $params['value'];
				$this->accountManager->expects($this->once())->method('updateUser')
					->with($params['user'], $newData);
			} else {
				$this->accountManager->expects($this->never())->method('updateUser');
			}
		}

		$this->hooks->changeUserHook($params);
	}

	public function dataTestChangeUserHook() {
		$user = $this->createMock(IUser::class);
		return [
			[
				['feature' => '', 'value' => ''],
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => ''],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => '']
				],
				false, false, true
			],
			[
				['user' => $user, 'value' => ''],
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => ''],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => '']
				],
				false, false, true
			],
			[
				['user' => $user, 'feature' => ''],
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => ''],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => '']
				],
				false, false, true
			],
			[
				['user' => $user, 'feature' => 'foo', 'value' => 'bar'],
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => 'oldMail@example.com'],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => 'oldDisplayName']
				],
				false, false, false
			],
			[
				['user' => $user, 'feature' => 'eMailAddress', 'value' => 'newMail@example.com'],
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => 'oldMail@example.com'],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => 'oldDisplayName']
				],
				true, false, false
			],
			[
				['user' => $user, 'feature' => 'displayName', 'value' => 'newDisplayName'],
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => 'oldMail@example.com'],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => 'oldDisplayName']
				],
				false, true, false
			],
		];
	}

	public function testGetAccountManager() {
		$hooks = new Hooks($this->logger);
		$result = $this->invokePrivate($hooks, 'getAccountManager');
		$this->assertInstanceOf(AccountManager::class, $result);
	}
}
