<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
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

use OCA\DAV\CardDAV\AddressBook;
use OCA\DAV\CardDAV\AddressBookImpl;
use OCA\DAV\CardDAV\CardDavBackend;
use OCP\IURLGenerator;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property\Text;
use Test\TestCase;

class AddressBookImplTest extends TestCase {

	/** @var AddressBookImpl  */
	private $addressBookImpl;

	/** @var  array */
	private $addressBookInfo;

	/** @var  AddressBook | \PHPUnit_Framework_MockObject_MockObject */
	private $addressBook;

	/** @var IURLGenerator | \PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;

	/** @var  CardDavBackend | \PHPUnit_Framework_MockObject_MockObject */
	private $backend;

	/** @var  VCard | \PHPUnit_Framework_MockObject_MockObject */
	private $vCard;

	protected function setUp(): void {
		parent::setUp();

		$this->addressBookInfo = [
			'id' => 42,
			'uri' => 'system',
			'principaluri' => 'principals/system/system',
			'{DAV:}displayname' => 'display name',
		];
		$this->addressBook = $this->getMockBuilder(AddressBook::class)
			->disableOriginalConstructor()->getMock();
		$this->backend = $this->getMockBuilder(CardDavBackend::class)
			->disableOriginalConstructor()->getMock();
		$this->vCard = $this->createMock(VCard::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->addressBookImpl = new AddressBookImpl(
			$this->addressBook,
			$this->addressBookInfo,
			$this->backend,
			$this->urlGenerator
		);
	}

	public function testGetKey() {
		$this->assertSame($this->addressBookInfo['id'],
			$this->addressBookImpl->getKey());
	}

	public function testGetDisplayName() {
		$this->assertSame($this->addressBookInfo['{DAV:}displayname'],
			$this->addressBookImpl->getDisplayName());
	}

	public function testSearch() {

		/** @var \PHPUnit_Framework_MockObject_MockObject | AddressBookImpl $addressBookImpl */
		$addressBookImpl = $this->getMockBuilder(AddressBookImpl::class)
			->setConstructorArgs(
				[
					$this->addressBook,
					$this->addressBookInfo,
					$this->backend,
					$this->urlGenerator,
				]
			)
			->setMethods(['vCard2Array', 'readCard'])
			->getMock();

		$pattern = 'pattern';
		$searchProperties = 'properties';

		$this->backend->expects($this->once())->method('search')
			->with($this->addressBookInfo['id'], $pattern, $searchProperties)
			->willReturn(
				[
					['uri' => 'foo.vcf', 'carddata' => 'cardData1'],
					['uri' => 'bar.vcf', 'carddata' => 'cardData2']
				]
			);

		$addressBookImpl->expects($this->exactly(2))->method('readCard')
			->willReturn($this->vCard);
		$addressBookImpl->expects($this->exactly(2))->method('vCard2Array')
			->withConsecutive(
				['foo.vcf', $this->vCard],
				['bar.vcf', $this->vCard]
			)->willReturn('vCard');

		$result = $addressBookImpl->search($pattern, $searchProperties, []);
		$this->assertTrue((is_array($result)));
		$this->assertSame(2, count($result));
	}

	/**
	 * @dataProvider dataTestCreate
	 *
	 * @param array $properties
	 */
	public function testCreate($properties) {

		$uid = 'uid';

		/** @var \PHPUnit_Framework_MockObject_MockObject | AddressBookImpl $addressBookImpl */
		$addressBookImpl = $this->getMockBuilder(AddressBookImpl::class)
			->setConstructorArgs(
				[
					$this->addressBook,
					$this->addressBookInfo,
					$this->backend,
					$this->urlGenerator,
				]
			)
			->setMethods(['vCard2Array', 'createUid', 'createEmptyVCard'])
			->getMock();

		$addressBookImpl->expects($this->once())->method('createUid')
			->willReturn($uid);
		$addressBookImpl->expects($this->once())->method('createEmptyVCard')
			->with($uid)->willReturn($this->vCard);
		$this->vCard->expects($this->exactly(count($properties)))
			->method('createProperty');
		$this->backend->expects($this->once())->method('createCard');
		$this->backend->expects($this->never())->method('updateCard');
		$this->backend->expects($this->never())->method('getCard');
		$addressBookImpl->expects($this->once())->method('vCard2Array')
			->with('uid.vcf', $this->vCard)->willReturn(true);

		$this->assertTrue($addressBookImpl->createOrUpdate($properties));
	}

	public function dataTestCreate() {
		return [
			[[]],
			[['FN' => 'John Doe']]
		];
	}

	public function testUpdate() {

		$uid = 'uid';
		$uri = 'bla.vcf';
		$properties = ['URI' => $uri, 'UID' => $uid, 'FN' => 'John Doe'];

		/** @var \PHPUnit_Framework_MockObject_MockObject | AddressBookImpl $addressBookImpl */
		$addressBookImpl = $this->getMockBuilder(AddressBookImpl::class)
			->setConstructorArgs(
				[
					$this->addressBook,
					$this->addressBookInfo,
					$this->backend,
					$this->urlGenerator,
				]
			)
			->setMethods(['vCard2Array', 'createUid', 'createEmptyVCard', 'readCard'])
			->getMock();

		$addressBookImpl->expects($this->never())->method('createUid');
		$addressBookImpl->expects($this->never())->method('createEmptyVCard');
		$this->backend->expects($this->once())->method('getCard')
			->with($this->addressBookInfo['id'], $uri)
			->willReturn(['carddata' => 'data']);
		$addressBookImpl->expects($this->once())->method('readCard')
			->with('data')->willReturn($this->vCard);
		$this->vCard->expects($this->exactly(count($properties)))
			->method('createProperty');
		$this->backend->expects($this->never())->method('createCard');
		$this->backend->expects($this->once())->method('updateCard');
		$addressBookImpl->expects($this->once())->method('vCard2Array')
			->with($uri, $this->vCard)->willReturn(true);

		$this->assertTrue($addressBookImpl->createOrUpdate($properties));
	}

	/**
	 * @dataProvider dataTestGetPermissions
	 *
	 * @param array $permissions
	 * @param int $expected
	 */
	public function testGetPermissions($permissions, $expected) {
		$this->addressBook->expects($this->once())->method('getACL')
			->willReturn($permissions);

		$this->assertSame($expected,
			$this->addressBookImpl->getPermissions()
		);
	}

	public function dataTestGetPermissions() {
		return [
			[[], 0],
			[[['privilege' => '{DAV:}read']], 1],
			[[['privilege' => '{DAV:}write']], 6],
			[[['privilege' => '{DAV:}all']], 31],
			[[['privilege' => '{DAV:}read'],['privilege' => '{DAV:}write']], 7],
			[[['privilege' => '{DAV:}read'],['privilege' => '{DAV:}all']], 31],
			[[['privilege' => '{DAV:}all'],['privilege' => '{DAV:}write']], 31],
			[[['privilege' => '{DAV:}read'],['privilege' => '{DAV:}write'],['privilege' => '{DAV:}all']], 31],
			[[['privilege' => '{DAV:}all'],['privilege' => '{DAV:}read'],['privilege' => '{DAV:}write']], 31],
		];
	}

	public function testDelete() {
		$cardId = 1;
		$cardUri = 'cardUri';
		$this->backend->expects($this->once())->method('getCardUri')
			->with($cardId)->willReturn($cardUri);
		$this->backend->expects($this->once())->method('deleteCard')
			->with($this->addressBookInfo['id'], $cardUri)
			->willReturn(true);

		$this->assertTrue($this->addressBookImpl->delete($cardId));
	}

	public function testReadCard() {
		$vCard = new VCard();
		$vCard->add(new Text($vCard, 'UID', 'uid'));
		$vCardSerialized = $vCard->serialize();

		$result = $this->invokePrivate($this->addressBookImpl, 'readCard', [$vCardSerialized]);
		$resultSerialized = $result->serialize();

		$this->assertSame($vCardSerialized, $resultSerialized);
	}

	public function testCreateUid() {
		/** @var \PHPUnit_Framework_MockObject_MockObject | AddressBookImpl $addressBookImpl */
		$addressBookImpl = $this->getMockBuilder(AddressBookImpl::class)
			->setConstructorArgs(
				[
					$this->addressBook,
					$this->addressBookInfo,
					$this->backend,
					$this->urlGenerator,
				]
			)
			->setMethods(['getUid'])
			->getMock();

		$addressBookImpl->expects($this->at(0))->method('getUid')->willReturn('uid0');
		$addressBookImpl->expects($this->at(1))->method('getUid')->willReturn('uid1');

		// simulate that 'uid0' already exists, so the second uid will be returned
		$this->backend->expects($this->exactly(2))->method('getContact')
			->willReturnCallback(
				function ($id, $uid) {
					return ($uid === 'uid0.vcf');
				}
			);

		$this->assertSame('uid1',
			$this->invokePrivate($addressBookImpl, 'createUid', [])
		);

	}

	public function testCreateEmptyVCard() {
		$uid = 'uid';
		$expectedVCard = new VCard();
		$expectedVCard->UID = $uid;
		$expectedVCardSerialized = $expectedVCard->serialize();

		$result = $this->invokePrivate($this->addressBookImpl, 'createEmptyVCard', [$uid]);
		$resultSerialized = $result->serialize();

		$this->assertSame($expectedVCardSerialized, $resultSerialized);
	}

	public function testVCard2Array() {
		$vCard = new VCard();

		$vCard->add($vCard->createProperty('FN', 'Full Name'));

		// Multi-value properties
		$vCard->add($vCard->createProperty('CLOUD', 'cloud-user1@localhost'));
		$vCard->add($vCard->createProperty('CLOUD', 'cloud-user2@example.tld'));
		$vCard->add($vCard->createProperty('EMAIL', 'email-user1@localhost'));
		$vCard->add($vCard->createProperty('EMAIL', 'email-user2@example.tld'));
		$vCard->add($vCard->createProperty('IMPP', 'impp-user1@localhost'));
		$vCard->add($vCard->createProperty('IMPP', 'impp-user2@example.tld'));
		$vCard->add($vCard->createProperty('TEL', '+49 123456789'));
		$vCard->add($vCard->createProperty('TEL', '+1 555 123456789'));
		$vCard->add($vCard->createProperty('URL', 'https://localhost'));
		$vCard->add($vCard->createProperty('URL', 'https://example.tld'));

		// Type depending properties
		$property = $vCard->createProperty('X-SOCIALPROFILE', 'tw-example');
		$property->add('TYPE', 'twitter');
		$vCard->add($property);
		$property = $vCard->createProperty('X-SOCIALPROFILE', 'fb-example');
		$property->add('TYPE', 'facebook');
		$vCard->add($property);

		$array = $this->invokePrivate($this->addressBookImpl, 'vCard2Array', ['uri', $vCard]);
		unset($array['PRODID']);
		unset($array['UID']);

		$this->assertEquals([
			'URI' => 'uri',
			'VERSION' => '4.0',
			'FN' => 'Full Name',
			'CLOUD' => [
				'cloud-user1@localhost',
				'cloud-user2@example.tld',
			],
			'EMAIL' => [
				'email-user1@localhost',
				'email-user2@example.tld',
			],
			'IMPP' => [
				'impp-user1@localhost',
				'impp-user2@example.tld',
			],
			'TEL' => [
				'+49 123456789',
				'+1 555 123456789',
			],
			'URL' => [
				'https://localhost',
				'https://example.tld',
			],

			'X-SOCIALPROFILE' => [
				'twitter'=> 'tw-example',
				'facebook'=> 'fb-example',
			],

			'isLocalSystemBook' => true,
		], $array);
	}
}
