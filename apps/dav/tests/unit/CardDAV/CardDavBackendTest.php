<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arne Hamann <kontakt+github@arne.email>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

use OCA\DAV\CalDAV\Proxy\ProxyMapper;
use OCA\DAV\CardDAV\AddressBook;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\Connector\Sabre\Principal;
use OCP\App\IAppManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IManager as ShareManager;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\PropPatch;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property\Text;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Test\TestCase;

/**
 * Class CardDavBackendTest
 *
 * @group DB
 *
 * @package OCA\DAV\Tests\unit\CardDAV
 */
class CardDavBackendTest extends TestCase {

	/** @var CardDavBackend */
	private $backend;

	/** @var Principal | \PHPUnit\Framework\MockObject\MockObject */
	private $principal;

	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;

	/** @var IGroupManager|\PHPUnit\Framework\MockObject\MockObject */
	private $groupManager;

	/** @var EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $legacyDispatcher;

	/** @var IEventDispatcher|\PHPUnit\Framework\MockObject\MockObject */
	private $dispatcher;

	/** @var  IDBConnection */
	private $db;

	/** @var string */
	private $dbCardsTable = 'cards';

	/** @var string */
	private $dbCardsPropertiesTable = 'cards_properties';

	public const UNIT_TEST_USER = 'principals/users/carddav-unit-test';
	public const UNIT_TEST_USER1 = 'principals/users/carddav-unit-test1';
	public const UNIT_TEST_GROUP = 'principals/groups/carddav-unit-test-group';

	private $vcardTest0 = 'BEGIN:VCARD'.PHP_EOL.
						 'VERSION:3.0'.PHP_EOL.
						 'PRODID:-//Sabre//Sabre VObject 4.1.2//EN'.PHP_EOL.
						 'UID:Test'.PHP_EOL.
						 'FN:Test'.PHP_EOL.
						 'N:Test;;;;'.PHP_EOL.
						 'END:VCARD';

	private $vcardTest1 = 'BEGIN:VCARD'.PHP_EOL.
						'VERSION:3.0'.PHP_EOL.
						'PRODID:-//Sabre//Sabre VObject 4.1.2//EN'.PHP_EOL.
						'UID:Test2'.PHP_EOL.
						'FN:Test2'.PHP_EOL.
						'N:Test2;;;;'.PHP_EOL.
						'END:VCARD';

	private $vcardTest2 = 'BEGIN:VCARD'.PHP_EOL.
						'VERSION:3.0'.PHP_EOL.
						'PRODID:-//Sabre//Sabre VObject 4.1.2//EN'.PHP_EOL.
						'UID:Test3'.PHP_EOL.
						'FN:Test3'.PHP_EOL.
						'N:Test3;;;;'.PHP_EOL.
						'END:VCARD';

	private $vcardTestNoUID = 'BEGIN:VCARD'.PHP_EOL.
						'VERSION:3.0'.PHP_EOL.
						'PRODID:-//Sabre//Sabre VObject 4.1.2//EN'.PHP_EOL.
						'FN:TestNoUID'.PHP_EOL.
						'N:TestNoUID;;;;'.PHP_EOL.
						'END:VCARD';

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->principal = $this->getMockBuilder(Principal::class)
			->setConstructorArgs([
				$this->userManager,
				$this->groupManager,
				$this->createMock(ShareManager::class),
				$this->createMock(IUserSession::class),
				$this->createMock(IAppManager::class),
				$this->createMock(ProxyMapper::class),
				$this->createMock(IConfig::class),
			])
			->setMethods(['getPrincipalByPath', 'getGroupMembership'])
			->getMock();
		$this->principal->method('getPrincipalByPath')
			->willReturn([
				'uri' => 'principals/best-friend',
				'{DAV:}displayname' => 'User\'s displayname',
			]);
		$this->principal->method('getGroupMembership')
			->withAnyParameters()
			->willReturn([self::UNIT_TEST_GROUP]);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->legacyDispatcher = $this->createMock(EventDispatcherInterface::class);

		$this->db = \OC::$server->getDatabaseConnection();

		$this->backend = new CardDavBackend($this->db, $this->principal, $this->userManager, $this->groupManager, $this->dispatcher, $this->legacyDispatcher);
		// start every test with a empty cards_properties and cards table
		$query = $this->db->getQueryBuilder();
		$query->delete('cards_properties')->execute();
		$query = $this->db->getQueryBuilder();
		$query->delete('cards')->execute();

		$this->tearDown();
	}

	protected function tearDown(): void {
		parent::tearDown();

		if (is_null($this->backend)) {
			return;
		}

		$this->principal->method('getGroupMembership')
			->withAnyParameters()
			->willReturn([self::UNIT_TEST_GROUP]);
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		foreach ($books as $book) {
			$this->backend->deleteAddressBook($book['id']);
		}
	}

	public function testAddressBookOperations() {

		// create a new address book
		$this->backend->createAddressBook(self::UNIT_TEST_USER, 'Example', []);

		$this->assertEquals(1, $this->backend->getAddressBooksForUserCount(self::UNIT_TEST_USER));
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		$this->assertEquals(1, count($books));
		$this->assertEquals('Example', $books[0]['{DAV:}displayname']);
		$this->assertEquals('User\'s displayname', $books[0]['{http://nextcloud.com/ns}owner-displayname']);

		// update it's display name
		$patch = new PropPatch([
			'{DAV:}displayname' => 'Unit test',
			'{urn:ietf:params:xml:ns:carddav}addressbook-description' => 'Addressbook used for unit testing'
		]);
		$this->backend->updateAddressBook($books[0]['id'], $patch);
		$patch->commit();
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		$this->assertEquals(1, count($books));
		$this->assertEquals('Unit test', $books[0]['{DAV:}displayname']);
		$this->assertEquals('Addressbook used for unit testing', $books[0]['{urn:ietf:params:xml:ns:carddav}addressbook-description']);

		// delete the address book
		$this->backend->deleteAddressBook($books[0]['id']);
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		$this->assertEquals(0, count($books));
	}

	public function testAddressBookSharing() {
		$this->userManager->expects($this->any())
			->method('userExists')
			->willReturn(true);

		$this->groupManager->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		$this->backend->createAddressBook(self::UNIT_TEST_USER, 'Example', []);
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		$this->assertEquals(1, count($books));
		$l = $this->createMock(IL10N::class);
		$addressBook = new AddressBook($this->backend, $books[0], $l);
		$this->backend->updateShares($addressBook, [
			[
				'href' => 'principal:' . self::UNIT_TEST_USER1,
			],
			[
				'href' => 'principal:' . self::UNIT_TEST_GROUP,
			]
		], []);
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER1);
		$this->assertEquals(1, count($books));

		// delete the address book
		$this->backend->deleteAddressBook($books[0]['id']);
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		$this->assertEquals(0, count($books));
	}

	public function testCardOperations() {

		/** @var CardDavBackend | \PHPUnit\Framework\MockObject\MockObject $backend */
		$backend = $this->getMockBuilder(CardDavBackend::class)
				->setConstructorArgs([$this->db, $this->principal, $this->userManager, $this->groupManager, $this->dispatcher, $this->legacyDispatcher])
				->setMethods(['updateProperties', 'purgeProperties'])->getMock();

		// create a new address book
		$backend->createAddressBook(self::UNIT_TEST_USER, 'Example', []);
		$books = $backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		$this->assertEquals(1, count($books));
		$bookId = $books[0]['id'];

		$uri = $this->getUniqueID('card');
		// updateProperties is expected twice, once for createCard and once for updateCard
		$backend->expects($this->at(0))->method('updateProperties')->with($bookId, $uri, $this->vcardTest0);
		$backend->expects($this->at(1))->method('updateProperties')->with($bookId, $uri, $this->vcardTest1);

		// Expect event
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CardDAV\CardDavBackend::createCard', $this->callback(function (GenericEvent $e) use ($bookId, $uri) {
				return $e->getArgument('addressBookId') === $bookId &&
					$e->getArgument('cardUri') === $uri &&
					$e->getArgument('cardData') === $this->vcardTest0;
			}));

		// create a card
		$backend->createCard($bookId, $uri, $this->vcardTest0);

		// get all the cards
		$cards = $backend->getCards($bookId);
		$this->assertEquals(1, count($cards));
		$this->assertEquals($this->vcardTest0, $cards[0]['carddata']);

		// get the cards
		$card = $backend->getCard($bookId, $uri);
		$this->assertNotNull($card);
		$this->assertArrayHasKey('id', $card);
		$this->assertArrayHasKey('uri', $card);
		$this->assertArrayHasKey('lastmodified', $card);
		$this->assertArrayHasKey('etag', $card);
		$this->assertArrayHasKey('size', $card);
		$this->assertEquals($this->vcardTest0, $card['carddata']);

		// Expect event
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CardDAV\CardDavBackend::updateCard', $this->callback(function (GenericEvent $e) use ($bookId, $uri) {
				return $e->getArgument('addressBookId') === $bookId &&
					$e->getArgument('cardUri') === $uri &&
					$e->getArgument('cardData') === $this->vcardTest1;
			}));

		// update the card
		$backend->updateCard($bookId, $uri, $this->vcardTest1);
		$card = $backend->getCard($bookId, $uri);
		$this->assertEquals($this->vcardTest1, $card['carddata']);

		// Expect event
		$this->legacyDispatcher->expects($this->at(0))
			->method('dispatch')
			->with('\OCA\DAV\CardDAV\CardDavBackend::deleteCard', $this->callback(function (GenericEvent $e) use ($bookId, $uri) {
				return $e->getArgument('addressBookId') === $bookId &&
					$e->getArgument('cardUri') === $uri;
			}));

		// delete the card
		$backend->expects($this->once())->method('purgeProperties')->with($bookId, $card['id']);
		$backend->deleteCard($bookId, $uri);
		$cards = $backend->getCards($bookId);
		$this->assertEquals(0, count($cards));
	}

	public function testMultiCard() {
		$this->backend = $this->getMockBuilder(CardDavBackend::class)
			->setConstructorArgs([$this->db, $this->principal, $this->userManager, $this->groupManager, $this->dispatcher, $this->legacyDispatcher])
			->setMethods(['updateProperties'])->getMock();

		// create a new address book
		$this->backend->createAddressBook(self::UNIT_TEST_USER, 'Example', []);
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		$this->assertEquals(1, count($books));
		$bookId = $books[0]['id'];

		// create a card
		$uri0 = self::getUniqueID('card');
		$this->backend->createCard($bookId, $uri0, $this->vcardTest0);
		$uri1 = self::getUniqueID('card');
		$this->backend->createCard($bookId, $uri1, $this->vcardTest1);
		$uri2 = self::getUniqueID('card');
		$this->backend->createCard($bookId, $uri2, $this->vcardTest2);

		// get all the cards
		$cards = $this->backend->getCards($bookId);
		$this->assertEquals(3, count($cards));
		usort($cards, function ($a, $b) {
			return $a['id'] < $b['id'] ? -1 : 1;
		});

		$this->assertEquals($this->vcardTest0, $cards[0]['carddata']);
		$this->assertEquals($this->vcardTest1, $cards[1]['carddata']);
		$this->assertEquals($this->vcardTest2, $cards[2]['carddata']);

		// get the cards 1 & 2 (not 0)
		$cards = $this->backend->getMultipleCards($bookId, [$uri1, $uri2]);
		$this->assertEquals(2, count($cards));
		usort($cards, function ($a, $b) {
			return $a['id'] < $b['id'] ? -1 : 1;
		});
		foreach ($cards as $index => $card) {
			$this->assertArrayHasKey('id', $card);
			$this->assertArrayHasKey('uri', $card);
			$this->assertArrayHasKey('lastmodified', $card);
			$this->assertArrayHasKey('etag', $card);
			$this->assertArrayHasKey('size', $card);
			$this->assertEquals($this->{ 'vcardTest'.($index + 1) }, $card['carddata']);
		}

		// delete the card
		$this->backend->deleteCard($bookId, $uri0);
		$this->backend->deleteCard($bookId, $uri1);
		$this->backend->deleteCard($bookId, $uri2);
		$cards = $this->backend->getCards($bookId);
		$this->assertEquals(0, count($cards));
	}

	public function testMultipleUIDOnDifferentAddressbooks() {
		$this->backend = $this->getMockBuilder(CardDavBackend::class)
			->setConstructorArgs([$this->db, $this->principal, $this->userManager, $this->groupManager, $this->dispatcher, $this->legacyDispatcher])
			->setMethods(['updateProperties'])->getMock();

		// create 2 new address books
		$this->backend->createAddressBook(self::UNIT_TEST_USER, 'Example', []);
		$this->backend->createAddressBook(self::UNIT_TEST_USER, 'Example2', []);
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		$this->assertEquals(2, count($books));
		$bookId0 = $books[0]['id'];
		$bookId1 = $books[1]['id'];

		// create a card
		$uri0 = $this->getUniqueID('card');
		$this->backend->createCard($bookId0, $uri0, $this->vcardTest0);

		// create another card with same uid but in second address book
		$uri1 = $this->getUniqueID('card');
		$this->backend->createCard($bookId1, $uri1, $this->vcardTest0);
	}

	public function testMultipleUIDDenied() {
		$this->backend = $this->getMockBuilder(CardDavBackend::class)
			->setConstructorArgs([$this->db, $this->principal, $this->userManager, $this->groupManager, $this->dispatcher, $this->legacyDispatcher])
			->setMethods(['updateProperties'])->getMock();

		// create a new address book
		$this->backend->createAddressBook(self::UNIT_TEST_USER, 'Example', []);
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		$this->assertEquals(1, count($books));
		$bookId = $books[0]['id'];

		// create a card
		$uri0 = $this->getUniqueID('card');
		$this->backend->createCard($bookId, $uri0, $this->vcardTest0);

		// create another card with same uid
		$uri1 = $this->getUniqueID('card');
		$this->expectException(BadRequest::class);
		$test = $this->backend->createCard($bookId, $uri1, $this->vcardTest0);
	}

	public function testNoValidUID() {
		$this->backend = $this->getMockBuilder(CardDavBackend::class)
			->setConstructorArgs([$this->db, $this->principal, $this->userManager, $this->groupManager, $this->dispatcher, $this->legacyDispatcher])
			->setMethods(['updateProperties'])->getMock();

		// create a new address book
		$this->backend->createAddressBook(self::UNIT_TEST_USER, 'Example', []);
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		$this->assertEquals(1, count($books));
		$bookId = $books[0]['id'];

		// create a card without uid
		$uri1 = $this->getUniqueID('card');
		$this->expectException(BadRequest::class);
		$test = $this->backend->createCard($bookId, $uri1, $this->vcardTestNoUID);
	}

	public function testDeleteWithoutCard() {
		$this->backend = $this->getMockBuilder(CardDavBackend::class)
			->setConstructorArgs([$this->db, $this->principal, $this->userManager, $this->groupManager, $this->dispatcher, $this->legacyDispatcher])
			->setMethods([
				'getCardId',
				'addChange',
				'purgeProperties',
				'updateProperties',
			])
			->getMock();

		// create a new address book
		$this->backend->createAddressBook(self::UNIT_TEST_USER, 'Example', []);
		$books = $this->backend->getUsersOwnAddressBooks(self::UNIT_TEST_USER);
		$this->assertEquals(1, count($books));

		$bookId = $books[0]['id'];
		$uri = $this->getUniqueID('card');

		// create a new address book
		$this->backend->expects($this->once())
			->method('getCardId')
			->with($bookId, $uri)
			->willThrowException(new \InvalidArgumentException());
		$this->backend->expects($this->exactly(2))
			->method('addChange')
			->withConsecutive(
				[$bookId, $uri, 1],
				[$bookId, $uri, 3]
			);
		$this->backend->expects($this->never())
			->method('purgeProperties');

		// create a card
		$this->backend->createCard($bookId, $uri, $this->vcardTest0);

		// delete the card
		$this->assertTrue($this->backend->deleteCard($bookId, $uri));
	}

	public function testSyncSupport() {
		$this->backend = $this->getMockBuilder(CardDavBackend::class)
			->setConstructorArgs([$this->db, $this->principal, $this->userManager, $this->groupManager, $this->dispatcher, $this->legacyDispatcher])
			->setMethods(['updateProperties'])->getMock();

		// create a new address book
		$this->backend->createAddressBook(self::UNIT_TEST_USER, 'Example', []);
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		$this->assertEquals(1, count($books));
		$bookId = $books[0]['id'];

		// fist call without synctoken
		$changes = $this->backend->getChangesForAddressBook($bookId, '', 1);
		$syncToken = $changes['syncToken'];

		// add a change
		$uri0 = $this->getUniqueID('card');
		$this->backend->createCard($bookId, $uri0, $this->vcardTest0);

		// look for changes
		$changes = $this->backend->getChangesForAddressBook($bookId, $syncToken, 1);
		$this->assertEquals($uri0, $changes['added'][0]);
	}

	public function testSharing() {
		$this->userManager->expects($this->any())
			->method('userExists')
			->willReturn(true);

		$this->groupManager->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		$this->backend->createAddressBook(self::UNIT_TEST_USER, 'Example', []);
		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER);
		$this->assertEquals(1, count($books));

		$l = $this->createMock(IL10N::class);
		$exampleBook = new AddressBook($this->backend, $books[0], $l);
		$this->backend->updateShares($exampleBook, [['href' => 'principal:' . self::UNIT_TEST_USER1]], []);

		$shares = $this->backend->getShares($exampleBook->getResourceId());
		$this->assertEquals(1, count($shares));

		// adding the same sharee again has no effect
		$this->backend->updateShares($exampleBook, [['href' => 'principal:' . self::UNIT_TEST_USER1]], []);

		$shares = $this->backend->getShares($exampleBook->getResourceId());
		$this->assertEquals(1, count($shares));

		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER1);
		$this->assertEquals(1, count($books));

		$this->backend->updateShares($exampleBook, [], ['principal:' . self::UNIT_TEST_USER1]);

		$shares = $this->backend->getShares($exampleBook->getResourceId());
		$this->assertEquals(0, count($shares));

		$books = $this->backend->getAddressBooksForUser(self::UNIT_TEST_USER1);
		$this->assertEquals(0, count($books));
	}

	public function testUpdateProperties() {
		$bookId = 42;
		$cardUri = 'card-uri';
		$cardId = 2;

		$backend = $this->getMockBuilder(CardDavBackend::class)
			->setConstructorArgs([$this->db, $this->principal, $this->userManager, $this->groupManager, $this->dispatcher, $this->legacyDispatcher])
			->setMethods(['getCardId'])->getMock();

		$backend->expects($this->any())->method('getCardId')->willReturn($cardId);

		// add properties for new vCard
		$vCard = new VCard();
		$vCard->UID = $cardUri;
		$vCard->FN = 'John Doe';
		$this->invokePrivate($backend, 'updateProperties', [$bookId, $cardUri, $vCard->serialize()]);

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('cards_properties');

		$qResult = $query->execute();
		$result = $qResult->fetchAll();
		$qResult->closeCursor();

		$this->assertSame(2, count($result));

		$this->assertSame('UID', $result[0]['name']);
		$this->assertSame($cardUri, $result[0]['value']);
		$this->assertSame($bookId, (int)$result[0]['addressbookid']);
		$this->assertSame($cardId, (int)$result[0]['cardid']);

		$this->assertSame('FN', $result[1]['name']);
		$this->assertSame('John Doe', $result[1]['value']);
		$this->assertSame($bookId, (int)$result[1]['addressbookid']);
		$this->assertSame($cardId, (int)$result[1]['cardid']);

		// update properties for existing vCard
		$vCard = new VCard();
		$vCard->UID = $cardUri;
		$this->invokePrivate($backend, 'updateProperties', [$bookId, $cardUri, $vCard->serialize()]);

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('cards_properties');

		$qResult = $query->execute();
		$result = $qResult->fetchAll();
		$qResult->closeCursor();

		$this->assertSame(1, count($result));

		$this->assertSame('UID', $result[0]['name']);
		$this->assertSame($cardUri, $result[0]['value']);
		$this->assertSame($bookId, (int)$result[0]['addressbookid']);
		$this->assertSame($cardId, (int)$result[0]['cardid']);
	}

	public function testPurgeProperties() {
		$query = $this->db->getQueryBuilder();
		$query->insert('cards_properties')
			->values(
				[
					'addressbookid' => $query->createNamedParameter(1),
					'cardid' => $query->createNamedParameter(1),
					'name' => $query->createNamedParameter('name1'),
					'value' => $query->createNamedParameter('value1'),
					'preferred' => $query->createNamedParameter(0)
				]
			);
		$query->execute();

		$query = $this->db->getQueryBuilder();
		$query->insert('cards_properties')
			->values(
				[
					'addressbookid' => $query->createNamedParameter(1),
					'cardid' => $query->createNamedParameter(2),
					'name' => $query->createNamedParameter('name2'),
					'value' => $query->createNamedParameter('value2'),
					'preferred' => $query->createNamedParameter(0)
				]
			);
		$query->execute();

		$this->invokePrivate($this->backend, 'purgeProperties', [1, 1]);

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('cards_properties');

		$qResult = $query->execute();
		$result = $qResult->fetchAll();
		$qResult->closeCursor();

		$this->assertSame(1, count($result));
		$this->assertSame(1 ,(int)$result[0]['addressbookid']);
		$this->assertSame(2 ,(int)$result[0]['cardid']);
	}

	public function testGetCardId() {
		$query = $this->db->getQueryBuilder();

		$query->insert('cards')
			->values(
				[
					'addressbookid' => $query->createNamedParameter(1),
					'carddata' => $query->createNamedParameter(''),
					'uri' => $query->createNamedParameter('uri'),
					'lastmodified' => $query->createNamedParameter(4738743),
					'etag' => $query->createNamedParameter('etag'),
					'size' => $query->createNamedParameter(120)
				]
			);
		$query->execute();
		$id = $query->getLastInsertId();

		$this->assertSame($id,
			$this->invokePrivate($this->backend, 'getCardId', [1, 'uri']));
	}


	public function testGetCardIdFailed() {
		$this->expectException(\InvalidArgumentException::class);

		$this->invokePrivate($this->backend, 'getCardId', [1, 'uri']);
	}

	/**
	 * @dataProvider dataTestSearch
	 *
	 * @param string $pattern
	 * @param array $properties
	 * @param array $options
	 * @param array $expected
	 */
	public function testSearch($pattern, $properties, $options, $expected) {
		/** @var VCard $vCards */
		$vCards = [];
		$vCards[0] = new VCard();
		$vCards[0]->add(new Text($vCards[0], 'UID', 'uid'));
		$vCards[0]->add(new Text($vCards[0], 'FN', 'John Doe'));
		$vCards[0]->add(new Text($vCards[0], 'CLOUD', 'john@nextcloud.com'));
		$vCards[1] = new VCard();
		$vCards[1]->add(new Text($vCards[1], 'UID', 'uid'));
		$vCards[1]->add(new Text($vCards[1], 'FN', 'John M. Doe'));
		$vCards[2] = new VCard();
		$vCards[2]->add(new Text($vCards[2], 'UID', 'uid'));
		$vCards[2]->add(new Text($vCards[2], 'FN', 'find without options'));
		$vCards[2]->add(new Text($vCards[2], 'CLOUD', 'peter_pan@nextcloud.com'));

		$vCardIds = [];
		$query = $this->db->getQueryBuilder();
		for ($i = 0; $i < 3; $i++) {
			$query->insert($this->dbCardsTable)
					->values(
							[
								'addressbookid' => $query->createNamedParameter(0),
								'carddata' => $query->createNamedParameter($vCards[$i]->serialize(), IQueryBuilder::PARAM_LOB),
								'uri' => $query->createNamedParameter('uri' . $i),
								'lastmodified' => $query->createNamedParameter(time()),
								'etag' => $query->createNamedParameter('etag' . $i),
								'size' => $query->createNamedParameter(120),
							]
					);
			$query->execute();
			$vCardIds[] = $query->getLastInsertId();
		}

		$query->insert($this->dbCardsPropertiesTable)
			->values(
				[
					'addressbookid' => $query->createNamedParameter(0),
					'cardid' => $query->createNamedParameter($vCardIds[0]),
					'name' => $query->createNamedParameter('FN'),
					'value' => $query->createNamedParameter('John Doe'),
					'preferred' => $query->createNamedParameter(0)
				]
			);
		$query->execute();
		$query->insert($this->dbCardsPropertiesTable)
			->values(
				[
					'addressbookid' => $query->createNamedParameter(0),
					'cardid' => $query->createNamedParameter($vCardIds[0]),
					'name' => $query->createNamedParameter('CLOUD'),
					'value' => $query->createNamedParameter('John@nextcloud.com'),
					'preferred' => $query->createNamedParameter(0)
				]
			);
		$query->execute();
		$query->insert($this->dbCardsPropertiesTable)
			->values(
				[
					'addressbookid' => $query->createNamedParameter(0),
					'cardid' => $query->createNamedParameter($vCardIds[1]),
					'name' => $query->createNamedParameter('FN'),
					'value' => $query->createNamedParameter('John M. Doe'),
					'preferred' => $query->createNamedParameter(0)
				]
			);
		$query->execute();
		$query->insert($this->dbCardsPropertiesTable)
			->values(
				[
					'addressbookid' => $query->createNamedParameter(0),
					'cardid' => $query->createNamedParameter($vCardIds[2]),
					'name' => $query->createNamedParameter('FN'),
					'value' => $query->createNamedParameter('find without options'),
					'preferred' => $query->createNamedParameter(0)
				]
			);
		$query->execute();
		$query->insert($this->dbCardsPropertiesTable)
			->values(
				[
					'addressbookid' => $query->createNamedParameter(0),
					'cardid' => $query->createNamedParameter($vCardIds[2]),
					'name' => $query->createNamedParameter('CLOUD'),
					'value' => $query->createNamedParameter('peter_pan@nextcloud.com'),
					'preferred' => $query->createNamedParameter(0)
				]
			);
		$query->execute();

		$result = $this->backend->search(0, $pattern, $properties, $options);

		// check result
		$this->assertSame(count($expected), count($result));
		$found = [];
		foreach ($result as $r) {
			foreach ($expected as $exp) {
				if ($r['uri'] === $exp[0] && strpos($r['carddata'], $exp[1]) > 0) {
					$found[$exp[1]] = true;
					break;
				}
			}
		}

		$this->assertSame(count($expected), count($found));
	}

	public function dataTestSearch() {
		return [
			['John', ['FN'], [], [['uri0', 'John Doe'], ['uri1', 'John M. Doe']]],
			['M. Doe', ['FN'], [], [['uri1', 'John M. Doe']]],
			['Do', ['FN'], [], [['uri0', 'John Doe'], ['uri1', 'John M. Doe']]],
			'check if duplicates are handled correctly' => ['John', ['FN', 'CLOUD'], [], [['uri0', 'John Doe'], ['uri1', 'John M. Doe']]],
			'case insensitive' => ['john', ['FN'], [], [['uri0', 'John Doe'], ['uri1', 'John M. Doe']]],
			'limit' => ['john', ['FN'], ['limit' => 1], [['uri0', 'John Doe']]],
			'limit and offset' => ['john', ['FN'], ['limit' => 1, 'offset' => 1], [['uri1', 'John M. Doe']]],
			'find "_" escaped' => ['_', ['CLOUD'], [], [['uri2', 'find without options']]],
			'find not empty CLOUD' => ['%_%', ['CLOUD'], ['escape_like_param' => false], [['uri0', 'John Doe'], ['uri2', 'find without options']]],
		];
	}

	public function testGetCardUri() {
		$query = $this->db->getQueryBuilder();
		$query->insert($this->dbCardsTable)
				->values(
						[
							'addressbookid' => $query->createNamedParameter(1),
							'carddata' => $query->createNamedParameter('carddata', IQueryBuilder::PARAM_LOB),
							'uri' => $query->createNamedParameter('uri'),
							'lastmodified' => $query->createNamedParameter(5489543),
							'etag' => $query->createNamedParameter('etag'),
							'size' => $query->createNamedParameter(120),
						]
				);
		$query->execute();

		$id = $query->getLastInsertId();

		$this->assertSame('uri', $this->backend->getCardUri($id));
	}


	public function testGetCardUriFailed() {
		$this->expectException(\InvalidArgumentException::class);

		$this->backend->getCardUri(1);
	}

	public function testGetContact() {
		$query = $this->db->getQueryBuilder();
		for ($i = 0; $i < 2; $i++) {
			$query->insert($this->dbCardsTable)
					->values(
							[
								'addressbookid' => $query->createNamedParameter($i),
								'carddata' => $query->createNamedParameter('carddata' . $i, IQueryBuilder::PARAM_LOB),
								'uri' => $query->createNamedParameter('uri' . $i),
								'lastmodified' => $query->createNamedParameter(5489543),
								'etag' => $query->createNamedParameter('etag' . $i),
								'size' => $query->createNamedParameter(120),
							]
					);
			$query->execute();
		}

		$result = $this->backend->getContact(0, 'uri0');
		$this->assertSame(8, count($result));
		$this->assertSame(0, (int)$result['addressbookid']);
		$this->assertSame('uri0', $result['uri']);
		$this->assertSame(5489543, (int)$result['lastmodified']);
		$this->assertSame('"etag0"', $result['etag']);
		$this->assertSame(120, (int)$result['size']);

		// this shouldn't return any result because 'uri1' is in address book 1
		// see https://github.com/nextcloud/server/issues/229
		$result = $this->backend->getContact(0, 'uri1');
		$this->assertEmpty($result);
	}

	public function testGetContactFail() {
		$this->assertEmpty($this->backend->getContact(0, 'uri'));
	}

	public function testCollectCardProperties() {
		$query = $this->db->getQueryBuilder();
		$query->insert($this->dbCardsPropertiesTable)
			->values(
				[
					'addressbookid' => $query->createNamedParameter(666),
					'cardid' => $query->createNamedParameter(777),
					'name' => $query->createNamedParameter('FN'),
					'value' => $query->createNamedParameter('John Doe'),
					'preferred' => $query->createNamedParameter(0)
				]
			)
		->execute();

		$result = $this->backend->collectCardProperties(666, 'FN');
		$this->assertEquals(['John Doe'], $result);
	}
}
