<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Tests\unit\CardDAV;

use OC\Accounts\AccountManager;
use OCA\DAV\CardDAV\Converter;
use OCP\Accounts\IAccountManager;
use OCP\IImage;
use OCP\IUser;
use Test\TestCase;

class ConverterTest extends TestCase {

	/** @var AccountManager|\PHPUnit\Framework\MockObject\MockObject */
	private $accountManager;

	protected function setUp(): void {
		parent::setUp();

		$this->accountManager = $this->createMock(AccountManager::class);
	}

	public function getAccountManager(IUser $user) {
		$accountManager = $this->getMockBuilder(AccountManager::class)
			->disableOriginalConstructor()->getMock();
		$accountManager->expects($this->any())->method('getUser')->willReturn(
			[
				IAccountManager::PROPERTY_DISPLAYNAME =>
					[
						'value' => $user->getDisplayName(),
						'scope' => AccountManager::VISIBILITY_CONTACTS_ONLY,
					],
				IAccountManager::PROPERTY_ADDRESS =>
					[
						'value' => '',
						'scope' => AccountManager::VISIBILITY_PRIVATE,
					],
				IAccountManager::PROPERTY_WEBSITE =>
					[
						'value' => '',
						'scope' => AccountManager::VISIBILITY_PRIVATE,
					],
				IAccountManager::PROPERTY_EMAIL =>
					[
						'value' => $user->getEMailAddress(),
						'scope' => AccountManager::VISIBILITY_CONTACTS_ONLY,
					],
				IAccountManager::PROPERTY_AVATAR =>
					[
						'scope' => AccountManager::VISIBILITY_CONTACTS_ONLY
					],
				IAccountManager::PROPERTY_PHONE =>
					[
						'value' => '',
						'scope' => AccountManager::VISIBILITY_PRIVATE,
					],
				IAccountManager::PROPERTY_TWITTER =>
					[
						'value' => '',
						'scope' => AccountManager::VISIBILITY_PRIVATE,
					],
			]
		);

		return $accountManager;
	}

	/**
	 * @dataProvider providesNewUsers
	 */
	public function testCreation($expectedVCard, $displayName = null, $eMailAddress = null, $cloudId = null) {
		$user = $this->getUserMock($displayName, $eMailAddress, $cloudId);
		$accountManager = $this->getAccountManager($user);

		$converter = new Converter($accountManager);
		$vCard = $converter->createCardFromUser($user);
		if ($expectedVCard !== null) {
			$this->assertInstanceOf('Sabre\VObject\Component\VCard', $vCard);
			$cardData = $vCard->jsonSerialize();
			$this->compareData($expectedVCard, $cardData);
		} else {
			$this->assertSame($expectedVCard, $vCard);
		}
	}

	protected function compareData($expected, $data) {
		foreach ($expected as $key => $value) {
			$found = false;
			foreach ($data[1] as $d) {
				if ($d[0] === $key && $d[3] === $value) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				$this->assertTrue(false, 'Expected data: ' . $key . ' not found.');
			}
		}
	}

	public function providesNewUsers() {
		return [
			[
				null
			],
			[
				null,
				null,
				'foo@bar.net'
			],
			[
				[
					'cloud' => 'foo@cloud.net',
					'email' => 'foo@bar.net',
					'photo' => 'MTIzNDU2Nzg5',
				],
				null,
				'foo@bar.net',
				'foo@cloud.net'
			],
			[
				[
					'cloud' => 'foo@cloud.net',
					'email' => 'foo@bar.net',
					'fn' => 'Dr. Foo Bar',
					'photo' => 'MTIzNDU2Nzg5',
				],
				"Dr. Foo Bar",
				"foo@bar.net",
				'foo@cloud.net'
			],
			[
				[
					'cloud' => 'foo@cloud.net',
					'fn' => 'Dr. Foo Bar',
					'photo' => 'MTIzNDU2Nzg5',
				],
				"Dr. Foo Bar",
				null,
				"foo@cloud.net"
			],
			[
				[
					'cloud' => 'foo@cloud.net',
					'fn' => 'Dr. Foo Bar',
					'photo' => 'MTIzNDU2Nzg5',
				],
				'Dr. Foo Bar',
				'',
				'foo@cloud.net'
			],
		];
	}

	/**
	 * @dataProvider providesNames
	 * @param $expected
	 * @param $fullName
	 */
	public function testNameSplitter($expected, $fullName) {
		$converter = new Converter($this->accountManager);
		$r = $converter->splitFullName($fullName);
		$r = implode(';', $r);
		$this->assertEquals($expected, $r);
	}

	public function providesNames() {
		return [
			['Sauron;;;;', 'Sauron'],
			['Baggins;Bilbo;;;', 'Bilbo Baggins'],
			['Tolkien;John;Ronald Reuel;;', 'John Ronald Reuel Tolkien'],
		];
	}

	/**
	 * @param $displayName
	 * @param $eMailAddress
	 * @param $cloudId
	 * @return IUser | \PHPUnit\Framework\MockObject\MockObject
	 */
	protected function getUserMock($displayName, $eMailAddress, $cloudId) {
		$image0 = $this->getMockBuilder(IImage::class)->disableOriginalConstructor()->getMock();
		$image0->method('mimeType')->willReturn('image/jpeg');
		$image0->method('data')->willReturn('123456789');
		$user = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$user->method('getUID')->willReturn('12345');
		$user->method('getDisplayName')->willReturn($displayName);
		$user->method('getEMailAddress')->willReturn($eMailAddress);
		$user->method('getCloudId')->willReturn($cloudId);
		$user->method('getAvatarImage')->willReturn($image0);
		return $user;
	}
}
