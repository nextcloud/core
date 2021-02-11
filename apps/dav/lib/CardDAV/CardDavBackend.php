<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arne Hamann <kontakt+github@arne.email>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author matt <34400929+call-me-matt@users.noreply.github.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\DAV\CardDAV;

use OCA\DAV\Connector\Sabre\Principal;
use OCA\DAV\DAV\Sharing\Backend;
use OCA\DAV\DAV\Sharing\IShareable;
use OCA\DAV\Events\AddressBookCreatedEvent;
use OCA\DAV\Events\AddressBookDeletedEvent;
use OCA\DAV\Events\AddressBookShareUpdatedEvent;
use OCA\DAV\Events\AddressBookUpdatedEvent;
use OCA\DAV\Events\CardCreatedEvent;
use OCA\DAV\Events\CardDeletedEvent;
use OCA\DAV\Events\CardUpdatedEvent;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PDO;
use Sabre\CardDAV\Backend\BackendInterface;
use Sabre\CardDAV\Backend\SyncSupport;
use Sabre\CardDAV\Plugin;
use Sabre\DAV\Exception\BadRequest;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class CardDavBackend implements BackendInterface, SyncSupport {
	public const PERSONAL_ADDRESSBOOK_URI = 'contacts';
	public const PERSONAL_ADDRESSBOOK_NAME = 'Contacts';

	/** @var Principal */
	private $principalBackend;

	/** @var string */
	private $dbCardsTable = 'cards';

	/** @var string */
	private $dbCardsPropertiesTable = 'cards_properties';

	/** @var IDBConnection */
	private $db;

	/** @var Backend */
	private $sharingBackend;

	/** @var array properties to index */
	public static $indexProperties = [
		'BDAY', 'UID', 'N', 'FN', 'TITLE', 'ROLE', 'NOTE', 'NICKNAME',
		'ORG', 'CATEGORIES', 'EMAIL', 'TEL', 'IMPP', 'ADR', 'URL', 'GEO',
		'CLOUD', 'X-SOCIALPROFILE'];

	/**
	 * @var string[] Map of uid => display name
	 */
	protected $userDisplayNames;

	/** @var IUserManager */
	private $userManager;

	/** @var IEventDispatcher */
	private $dispatcher;

	/** @var EventDispatcherInterface */
	private $legacyDispatcher;

	private $etagCache = [];

	/**
	 * CardDavBackend constructor.
	 *
	 * @param IDBConnection $db
	 * @param Principal $principalBackend
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param IEventDispatcher $dispatcher
	 * @param EventDispatcherInterface $legacyDispatcher
	 */
	public function __construct(IDBConnection $db,
								Principal $principalBackend,
								IUserManager $userManager,
								IGroupManager $groupManager,
								IEventDispatcher $dispatcher,
								EventDispatcherInterface $legacyDispatcher) {
		$this->db = $db;
		$this->principalBackend = $principalBackend;
		$this->userManager = $userManager;
		$this->dispatcher = $dispatcher;
		$this->legacyDispatcher = $legacyDispatcher;
		$this->sharingBackend = new Backend($this->db, $this->userManager, $groupManager, $principalBackend, 'addressbook');
	}

	/**
	 * Return the number of address books for a principal
	 *
	 * @param $principalUri
	 * @return int
	 */
	public function getAddressBooksForUserCount($principalUri) {
		$principalUri = $this->convertPrincipal($principalUri, true);
		$query = $this->db->getQueryBuilder();
		$query->select($query->func()->count('*'))
			->from('addressbooks')
			->where($query->expr()->eq('principaluri', $query->createNamedParameter($principalUri)));

		$result = $query->execute();
		$column = (int) $result->fetchOne();
		$result->closeCursor();
		return $column;
	}

	/**
	 * Returns the list of address books for a specific user.
	 *
	 * Every addressbook should have the following properties:
	 *   id - an arbitrary unique id
	 *   uri - the 'basename' part of the url
	 *   principaluri - Same as the passed parameter
	 *
	 * Any additional clark-notation property may be passed besides this. Some
	 * common ones are :
	 *   {DAV:}displayname
	 *   {urn:ietf:params:xml:ns:carddav}addressbook-description
	 *   {http://calendarserver.org/ns/}getctag
	 *
	 * @param string $principalUri
	 * @return array
	 */
	public function getAddressBooksForUser($principalUri) {
		$principalUriOriginal = $principalUri;
		$principalUri = $this->convertPrincipal($principalUri, true);
		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'uri', 'displayname', 'principaluri', 'description', 'synctoken'])
			->from('addressbooks')
			->where($query->expr()->eq('principaluri', $query->createNamedParameter($principalUri)));

		$addressBooks = [];

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$addressBooks[$row['id']] = [
				'id' => $row['id'],
				'uri' => $row['uri'],
				'principaluri' => $this->convertPrincipal($row['principaluri'], false),
				'{DAV:}displayname' => $row['displayname'],
				'{' . Plugin::NS_CARDDAV . '}addressbook-description' => $row['description'],
				'{http://calendarserver.org/ns/}getctag' => $row['synctoken'],
				'{http://sabredav.org/ns}sync-token' => $row['synctoken'] ?: '0',
			];

			$this->addOwnerPrincipal($addressBooks[$row['id']]);
		}
		$result->closeCursor();

		// query for shared addressbooks
		$principals = $this->principalBackend->getGroupMembership($principalUriOriginal, true);
		$principals = array_merge($principals, $this->principalBackend->getCircleMembership($principalUriOriginal));

		$principals[] = $principalUri;

		$query = $this->db->getQueryBuilder();
		$result = $query->select(['a.id', 'a.uri', 'a.displayname', 'a.principaluri', 'a.description', 'a.synctoken', 's.access'])
			->from('dav_shares', 's')
			->join('s', 'addressbooks', 'a', $query->expr()->eq('s.resourceid', 'a.id'))
			->where($query->expr()->in('s.principaluri', $query->createParameter('principaluri')))
			->andWhere($query->expr()->eq('s.type', $query->createParameter('type')))
			->setParameter('type', 'addressbook')
			->setParameter('principaluri', $principals, IQueryBuilder::PARAM_STR_ARRAY)
			->execute();

		$readOnlyPropertyName = '{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}read-only';
		while ($row = $result->fetch()) {
			if ($row['principaluri'] === $principalUri) {
				continue;
			}

			$readOnly = (int)$row['access'] === Backend::ACCESS_READ;
			if (isset($addressBooks[$row['id']])) {
				if ($readOnly) {
					// New share can not have more permissions then the old one.
					continue;
				}
				if (isset($addressBooks[$row['id']][$readOnlyPropertyName]) &&
					$addressBooks[$row['id']][$readOnlyPropertyName] === 0) {
					// Old share is already read-write, no more permissions can be gained
					continue;
				}
			}

			list(, $name) = \Sabre\Uri\split($row['principaluri']);
			$uri = $row['uri'] . '_shared_by_' . $name;
			$displayName = $row['displayname'] . ' (' . $this->getUserDisplayName($name) . ')';

			$addressBooks[$row['id']] = [
				'id' => $row['id'],
				'uri' => $uri,
				'principaluri' => $principalUriOriginal,
				'{DAV:}displayname' => $displayName,
				'{' . Plugin::NS_CARDDAV . '}addressbook-description' => $row['description'],
				'{http://calendarserver.org/ns/}getctag' => $row['synctoken'],
				'{http://sabredav.org/ns}sync-token' => $row['synctoken'] ?: '0',
				'{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}owner-principal' => $row['principaluri'],
				$readOnlyPropertyName => $readOnly,
			];

			$this->addOwnerPrincipal($addressBooks[$row['id']]);
		}
		$result->closeCursor();

		return array_values($addressBooks);
	}

	public function getUsersOwnAddressBooks($principalUri) {
		$principalUri = $this->convertPrincipal($principalUri, true);
		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'uri', 'displayname', 'principaluri', 'description', 'synctoken'])
			->from('addressbooks')
			->where($query->expr()->eq('principaluri', $query->createNamedParameter($principalUri)));

		$addressBooks = [];

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$addressBooks[$row['id']] = [
				'id' => $row['id'],
				'uri' => $row['uri'],
				'principaluri' => $this->convertPrincipal($row['principaluri'], false),
				'{DAV:}displayname' => $row['displayname'],
				'{' . Plugin::NS_CARDDAV . '}addressbook-description' => $row['description'],
				'{http://calendarserver.org/ns/}getctag' => $row['synctoken'],
				'{http://sabredav.org/ns}sync-token' => $row['synctoken'] ?: '0',
			];

			$this->addOwnerPrincipal($addressBooks[$row['id']]);
		}
		$result->closeCursor();

		return array_values($addressBooks);
	}

	private function getUserDisplayName($uid) {
		if (!isset($this->userDisplayNames[$uid])) {
			$user = $this->userManager->get($uid);

			if ($user instanceof IUser) {
				$this->userDisplayNames[$uid] = $user->getDisplayName();
			} else {
				$this->userDisplayNames[$uid] = $uid;
			}
		}

		return $this->userDisplayNames[$uid];
	}

	/**
	 * @param int $addressBookId
	 */
	public function getAddressBookById($addressBookId) {
		$query = $this->db->getQueryBuilder();
		$result = $query->select(['id', 'uri', 'displayname', 'principaluri', 'description', 'synctoken'])
			->from('addressbooks')
			->where($query->expr()->eq('id', $query->createNamedParameter($addressBookId)))
			->execute();

		$row = $result->fetch();
		$result->closeCursor();
		if ($row === false) {
			return null;
		}

		$addressBook = [
			'id' => $row['id'],
			'uri' => $row['uri'],
			'principaluri' => $row['principaluri'],
			'{DAV:}displayname' => $row['displayname'],
			'{' . Plugin::NS_CARDDAV . '}addressbook-description' => $row['description'],
			'{http://calendarserver.org/ns/}getctag' => $row['synctoken'],
			'{http://sabredav.org/ns}sync-token' => $row['synctoken'] ?: '0',
		];

		$this->addOwnerPrincipal($addressBook);

		return $addressBook;
	}

	/**
	 * @param $addressBookUri
	 * @return array|null
	 */
	public function getAddressBooksByUri($principal, $addressBookUri) {
		$query = $this->db->getQueryBuilder();
		$result = $query->select(['id', 'uri', 'displayname', 'principaluri', 'description', 'synctoken'])
			->from('addressbooks')
			->where($query->expr()->eq('uri', $query->createNamedParameter($addressBookUri)))
			->andWhere($query->expr()->eq('principaluri', $query->createNamedParameter($principal)))
			->setMaxResults(1)
			->execute();

		$row = $result->fetch();
		$result->closeCursor();
		if ($row === false) {
			return null;
		}

		$addressBook = [
			'id' => $row['id'],
			'uri' => $row['uri'],
			'principaluri' => $row['principaluri'],
			'{DAV:}displayname' => $row['displayname'],
			'{' . Plugin::NS_CARDDAV . '}addressbook-description' => $row['description'],
			'{http://calendarserver.org/ns/}getctag' => $row['synctoken'],
			'{http://sabredav.org/ns}sync-token' => $row['synctoken'] ?: '0',
		];

		$this->addOwnerPrincipal($addressBook);

		return $addressBook;
	}

	/**
	 * Updates properties for an address book.
	 *
	 * The list of mutations is stored in a Sabre\DAV\PropPatch object.
	 * To do the actual updates, you must tell this object which properties
	 * you're going to process with the handle() method.
	 *
	 * Calling the handle method is like telling the PropPatch object "I
	 * promise I can handle updating this property".
	 *
	 * Read the PropPatch documentation for more info and examples.
	 *
	 * @param string $addressBookId
	 * @param \Sabre\DAV\PropPatch $propPatch
	 * @return void
	 */
	public function updateAddressBook($addressBookId, \Sabre\DAV\PropPatch $propPatch) {
		$supportedProperties = [
			'{DAV:}displayname',
			'{' . Plugin::NS_CARDDAV . '}addressbook-description',
		];

		$propPatch->handle($supportedProperties, function ($mutations) use ($addressBookId) {
			$updates = [];
			foreach ($mutations as $property => $newValue) {
				switch ($property) {
					case '{DAV:}displayname':
						$updates['displayname'] = $newValue;
						break;
					case '{' . Plugin::NS_CARDDAV . '}addressbook-description':
						$updates['description'] = $newValue;
						break;
				}
			}
			$query = $this->db->getQueryBuilder();
			$query->update('addressbooks');

			foreach ($updates as $key => $value) {
				$query->set($key, $query->createNamedParameter($value));
			}
			$query->where($query->expr()->eq('id', $query->createNamedParameter($addressBookId)))
				->execute();

			$this->addChange($addressBookId, "", 2);

			$addressBookRow = $this->getAddressBookById((int)$addressBookId);
			$shares = $this->getShares($addressBookId);
			$this->dispatcher->dispatchTyped(new AddressBookUpdatedEvent((int)$addressBookId, $addressBookRow, $shares, $mutations));

			return true;
		});
	}

	/**
	 * Creates a new address book
	 *
	 * @param string $principalUri
	 * @param string $url Just the 'basename' of the url.
	 * @param array $properties
	 * @return int
	 * @throws BadRequest
	 */
	public function createAddressBook($principalUri, $url, array $properties) {
		$values = [
			'displayname' => null,
			'description' => null,
			'principaluri' => $principalUri,
			'uri' => $url,
			'synctoken' => 1
		];

		foreach ($properties as $property => $newValue) {
			switch ($property) {
				case '{DAV:}displayname':
					$values['displayname'] = $newValue;
					break;
				case '{' . Plugin::NS_CARDDAV . '}addressbook-description':
					$values['description'] = $newValue;
					break;
				default:
					throw new BadRequest('Unknown property: ' . $property);
			}
		}

		// Fallback to make sure the displayname is set. Some clients may refuse
		// to work with addressbooks not having a displayname.
		if (is_null($values['displayname'])) {
			$values['displayname'] = $url;
		}

		$query = $this->db->getQueryBuilder();
		$query->insert('addressbooks')
			->values([
				'uri' => $query->createParameter('uri'),
				'displayname' => $query->createParameter('displayname'),
				'description' => $query->createParameter('description'),
				'principaluri' => $query->createParameter('principaluri'),
				'synctoken' => $query->createParameter('synctoken'),
			])
			->setParameters($values)
			->execute();

		$addressBookId = $query->getLastInsertId();
		$addressBookRow = $this->getAddressBookById($addressBookId);
		$this->dispatcher->dispatchTyped(new AddressBookCreatedEvent((int)$addressBookId, $addressBookRow));

		return $addressBookId;
	}

	/**
	 * Deletes an entire addressbook and all its contents
	 *
	 * @param mixed $addressBookId
	 * @return void
	 */
	public function deleteAddressBook($addressBookId) {
		$addressBookData = $this->getAddressBookById($addressBookId);
		$shares = $this->getShares($addressBookId);

		$query = $this->db->getQueryBuilder();
		$query->delete($this->dbCardsTable)
			->where($query->expr()->eq('addressbookid', $query->createParameter('addressbookid')))
			->setParameter('addressbookid', $addressBookId)
			->execute();

		$query->delete('addressbookchanges')
			->where($query->expr()->eq('addressbookid', $query->createParameter('addressbookid')))
			->setParameter('addressbookid', $addressBookId)
			->execute();

		$query->delete('addressbooks')
			->where($query->expr()->eq('id', $query->createParameter('id')))
			->setParameter('id', $addressBookId)
			->execute();

		$this->sharingBackend->deleteAllShares($addressBookId);

		$query->delete($this->dbCardsPropertiesTable)
			->where($query->expr()->eq('addressbookid', $query->createNamedParameter($addressBookId)))
			->execute();

		if ($addressBookData) {
			$this->dispatcher->dispatchTyped(new AddressBookDeletedEvent((int) $addressBookId, $addressBookData, $shares));
		}
	}

	/**
	 * Returns all cards for a specific addressbook id.
	 *
	 * This method should return the following properties for each card:
	 *   * carddata - raw vcard data
	 *   * uri - Some unique url
	 *   * lastmodified - A unix timestamp
	 *
	 * It's recommended to also return the following properties:
	 *   * etag - A unique etag. This must change every time the card changes.
	 *   * size - The size of the card in bytes.
	 *
	 * If these last two properties are provided, less time will be spent
	 * calculating them. If they are specified, you can also ommit carddata.
	 * This may speed up certain requests, especially with large cards.
	 *
	 * @param mixed $addressBookId
	 * @return array
	 */
	public function getCards($addressBookId) {
		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'uri', 'lastmodified', 'etag', 'size', 'carddata', 'uid'])
			->from($this->dbCardsTable)
			->where($query->expr()->eq('addressbookid', $query->createNamedParameter($addressBookId)));

		$cards = [];

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$row['etag'] = '"' . $row['etag'] . '"';

			$modified = false;
			$row['carddata'] = $this->readBlob($row['carddata'], $modified);
			if ($modified) {
				$row['size'] = strlen($row['carddata']);
			}

			$cards[] = $row;
		}
		$result->closeCursor();

		return $cards;
	}

	/**
	 * Returns a specific card.
	 *
	 * The same set of properties must be returned as with getCards. The only
	 * exception is that 'carddata' is absolutely required.
	 *
	 * If the card does not exist, you must return false.
	 *
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @return array
	 */
	public function getCard($addressBookId, $cardUri) {
		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'uri', 'lastmodified', 'etag', 'size', 'carddata', 'uid'])
			->from($this->dbCardsTable)
			->where($query->expr()->eq('addressbookid', $query->createNamedParameter($addressBookId)))
			->andWhere($query->expr()->eq('uri', $query->createNamedParameter($cardUri)))
			->setMaxResults(1);

		$result = $query->execute();
		$row = $result->fetch();
		if (!$row) {
			return false;
		}
		$row['etag'] = '"' . $row['etag'] . '"';

		$modified = false;
		$row['carddata'] = $this->readBlob($row['carddata'], $modified);
		if ($modified) {
			$row['size'] = strlen($row['carddata']);
		}

		return $row;
	}

	/**
	 * Returns a list of cards.
	 *
	 * This method should work identical to getCard, but instead return all the
	 * cards in the list as an array.
	 *
	 * If the backend supports this, it may allow for some speed-ups.
	 *
	 * @param mixed $addressBookId
	 * @param string[] $uris
	 * @return array
	 */
	public function getMultipleCards($addressBookId, array $uris) {
		if (empty($uris)) {
			return [];
		}

		$chunks = array_chunk($uris, 100);
		$cards = [];

		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'uri', 'lastmodified', 'etag', 'size', 'carddata', 'uid'])
			->from($this->dbCardsTable)
			->where($query->expr()->eq('addressbookid', $query->createNamedParameter($addressBookId)))
			->andWhere($query->expr()->in('uri', $query->createParameter('uri')));

		foreach ($chunks as $uris) {
			$query->setParameter('uri', $uris, IQueryBuilder::PARAM_STR_ARRAY);
			$result = $query->execute();

			while ($row = $result->fetch()) {
				$row['etag'] = '"' . $row['etag'] . '"';

				$modified = false;
				$row['carddata'] = $this->readBlob($row['carddata'], $modified);
				if ($modified) {
					$row['size'] = strlen($row['carddata']);
				}

				$cards[] = $row;
			}
			$result->closeCursor();
		}
		return $cards;
	}

	/**
	 * Creates a new card.
	 *
	 * The addressbook id will be passed as the first argument. This is the
	 * same id as it is returned from the getAddressBooksForUser method.
	 *
	 * The cardUri is a base uri, and doesn't include the full path. The
	 * cardData argument is the vcard body, and is passed as a string.
	 *
	 * It is possible to return an ETag from this method. This ETag is for the
	 * newly created resource, and must be enclosed with double quotes (that
	 * is, the string itself must contain the double quotes).
	 *
	 * You should only return the ETag if you store the carddata as-is. If a
	 * subsequent GET request on the same card does not have the same body,
	 * byte-by-byte and you did return an ETag here, clients tend to get
	 * confused.
	 *
	 * If you don't return an ETag, you can just return null.
	 *
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @param string $cardData
	 * @return string
	 */
	public function createCard($addressBookId, $cardUri, $cardData) {
		$etag = md5($cardData);
		$uid = $this->getUID($cardData);

		$q = $this->db->getQueryBuilder();
		$q->select('uid')
			->from($this->dbCardsTable)
			->where($q->expr()->eq('addressbookid', $q->createNamedParameter($addressBookId)))
			->andWhere($q->expr()->eq('uid', $q->createNamedParameter($uid)))
			->setMaxResults(1);
		$result = $q->execute();
		$count = (bool)$result->fetchOne();
		$result->closeCursor();
		if ($count) {
			throw new \Sabre\DAV\Exception\BadRequest('VCard object with uid already exists in this addressbook collection.');
		}

		$query = $this->db->getQueryBuilder();
		$query->insert('cards')
			->values([
				'carddata' => $query->createNamedParameter($cardData, IQueryBuilder::PARAM_LOB),
				'uri' => $query->createNamedParameter($cardUri),
				'lastmodified' => $query->createNamedParameter(time()),
				'addressbookid' => $query->createNamedParameter($addressBookId),
				'size' => $query->createNamedParameter(strlen($cardData)),
				'etag' => $query->createNamedParameter($etag),
				'uid' => $query->createNamedParameter($uid),
			])
			->execute();

		$etagCacheKey = "$addressBookId#$cardUri";
		$this->etagCache[$etagCacheKey] = $etag;

		$this->addChange($addressBookId, $cardUri, 1);
		$this->updateProperties($addressBookId, $cardUri, $cardData);

		$addressBookData = $this->getAddressBookById($addressBookId);
		$shares = $this->getShares($addressBookId);
		$objectRow = $this->getCard($addressBookId, $cardUri);
		$this->dispatcher->dispatchTyped(new CardCreatedEvent((int)$addressBookId, $addressBookData, $shares, $objectRow));
		$this->legacyDispatcher->dispatch('\OCA\DAV\CardDAV\CardDavBackend::createCard',
			new GenericEvent(null, [
				'addressBookId' => $addressBookId,
				'cardUri' => $cardUri,
				'cardData' => $cardData]));

		return '"' . $etag . '"';
	}

	/**
	 * Updates a card.
	 *
	 * The addressbook id will be passed as the first argument. This is the
	 * same id as it is returned from the getAddressBooksForUser method.
	 *
	 * The cardUri is a base uri, and doesn't include the full path. The
	 * cardData argument is the vcard body, and is passed as a string.
	 *
	 * It is possible to return an ETag from this method. This ETag should
	 * match that of the updated resource, and must be enclosed with double
	 * quotes (that is: the string itself must contain the actual quotes).
	 *
	 * You should only return the ETag if you store the carddata as-is. If a
	 * subsequent GET request on the same card does not have the same body,
	 * byte-by-byte and you did return an ETag here, clients tend to get
	 * confused.
	 *
	 * If you don't return an ETag, you can just return null.
	 *
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @param string $cardData
	 * @return string
	 */
	public function updateCard($addressBookId, $cardUri, $cardData) {
		$uid = $this->getUID($cardData);
		$etag = md5($cardData);
		$query = $this->db->getQueryBuilder();

		// check for recently stored etag and stop if it is the same
		$etagCacheKey = "$addressBookId#$cardUri";
		if (isset($this->etagCache[$etagCacheKey]) && $this->etagCache[$etagCacheKey] === $etag) {
			return '"' . $etag . '"';
		}

		$query->update($this->dbCardsTable)
			->set('carddata', $query->createNamedParameter($cardData, IQueryBuilder::PARAM_LOB))
			->set('lastmodified', $query->createNamedParameter(time()))
			->set('size', $query->createNamedParameter(strlen($cardData)))
			->set('etag', $query->createNamedParameter($etag))
			->set('uid', $query->createNamedParameter($uid))
			->where($query->expr()->eq('uri', $query->createNamedParameter($cardUri)))
			->andWhere($query->expr()->eq('addressbookid', $query->createNamedParameter($addressBookId)))
			->execute();

		$this->etagCache[$etagCacheKey] = $etag;

		$this->addChange($addressBookId, $cardUri, 2);
		$this->updateProperties($addressBookId, $cardUri, $cardData);

		$addressBookData = $this->getAddressBookById($addressBookId);
		$shares = $this->getShares($addressBookId);
		$objectRow = $this->getCard($addressBookId, $cardUri);
		$this->dispatcher->dispatchTyped(new CardUpdatedEvent((int)$addressBookId, $addressBookData, $shares, $objectRow));
		$this->legacyDispatcher->dispatch('\OCA\DAV\CardDAV\CardDavBackend::updateCard',
			new GenericEvent(null, [
				'addressBookId' => $addressBookId,
				'cardUri' => $cardUri,
				'cardData' => $cardData]));

		return '"' . $etag . '"';
	}

	/**
	 * Deletes a card
	 *
	 * @param mixed $addressBookId
	 * @param string $cardUri
	 * @return bool
	 */
	public function deleteCard($addressBookId, $cardUri) {
		$addressBookData = $this->getAddressBookById($addressBookId);
		$shares = $this->getShares($addressBookId);
		$objectRow = $this->getCard($addressBookId, $cardUri);

		try {
			$cardId = $this->getCardId($addressBookId, $cardUri);
		} catch (\InvalidArgumentException $e) {
			$cardId = null;
		}
		$query = $this->db->getQueryBuilder();
		$ret = $query->delete($this->dbCardsTable)
			->where($query->expr()->eq('addressbookid', $query->createNamedParameter($addressBookId)))
			->andWhere($query->expr()->eq('uri', $query->createNamedParameter($cardUri)))
			->execute();

		$this->addChange($addressBookId, $cardUri, 3);

		if ($ret === 1) {
			if ($cardId !== null) {
				$this->dispatcher->dispatchTyped(new CardDeletedEvent((int)$addressBookId, $addressBookData, $shares, $objectRow));
				$this->legacyDispatcher->dispatch('\OCA\DAV\CardDAV\CardDavBackend::deleteCard',
					new GenericEvent(null, [
						'addressBookId' => $addressBookId,
						'cardUri' => $cardUri]));

				$this->purgeProperties($addressBookId, $cardId);
			}
			return true;
		}

		return false;
	}

	/**
	 * The getChanges method returns all the changes that have happened, since
	 * the specified syncToken in the specified address book.
	 *
	 * This function should return an array, such as the following:
	 *
	 * [
	 *   'syncToken' => 'The current synctoken',
	 *   'added'   => [
	 *      'new.txt',
	 *   ],
	 *   'modified'   => [
	 *      'modified.txt',
	 *   ],
	 *   'deleted' => [
	 *      'foo.php.bak',
	 *      'old.txt'
	 *   ]
	 * ];
	 *
	 * The returned syncToken property should reflect the *current* syncToken
	 * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
	 * property. This is needed here too, to ensure the operation is atomic.
	 *
	 * If the $syncToken argument is specified as null, this is an initial
	 * sync, and all members should be reported.
	 *
	 * The modified property is an array of nodenames that have changed since
	 * the last token.
	 *
	 * The deleted property is an array with nodenames, that have been deleted
	 * from collection.
	 *
	 * The $syncLevel argument is basically the 'depth' of the report. If it's
	 * 1, you only have to report changes that happened only directly in
	 * immediate descendants. If it's 2, it should also include changes from
	 * the nodes below the child collections. (grandchildren)
	 *
	 * The $limit argument allows a client to specify how many results should
	 * be returned at most. If the limit is not specified, it should be treated
	 * as infinite.
	 *
	 * If the limit (infinite or not) is higher than you're willing to return,
	 * you should throw a Sabre\DAV\Exception\TooMuchMatches() exception.
	 *
	 * If the syncToken is expired (due to data cleanup) or unknown, you must
	 * return null.
	 *
	 * The limit is 'suggestive'. You are free to ignore it.
	 *
	 * @param string $addressBookId
	 * @param string $syncToken
	 * @param int $syncLevel
	 * @param int $limit
	 * @return array
	 */
	public function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null) {
		// Current synctoken
		$stmt = $this->db->prepare('SELECT `synctoken` FROM `*PREFIX*addressbooks` WHERE `id` = ?');
		$stmt->execute([$addressBookId]);
		$currentToken = $stmt->fetchOne();

		if (is_null($currentToken)) {
			return null;
		}

		$result = [
			'syncToken' => $currentToken,
			'added' => [],
			'modified' => [],
			'deleted' => [],
		];

		if ($syncToken) {
			$query = "SELECT `uri`, `operation` FROM `*PREFIX*addressbookchanges` WHERE `synctoken` >= ? AND `synctoken` < ? AND `addressbookid` = ? ORDER BY `synctoken`";
			if ($limit > 0) {
				$query .= " LIMIT " . (int)$limit;
			}

			// Fetching all changes
			$stmt = $this->db->prepare($query);
			$stmt->execute([$syncToken, $currentToken, $addressBookId]);

			$changes = [];

			// This loop ensures that any duplicates are overwritten, only the
			// last change on a node is relevant.
			while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
				$changes[$row['uri']] = $row['operation'];
			}

			foreach ($changes as $uri => $operation) {
				switch ($operation) {
					case 1:
						$result['added'][] = $uri;
						break;
					case 2:
						$result['modified'][] = $uri;
						break;
					case 3:
						$result['deleted'][] = $uri;
						break;
				}
			}
		} else {
			// No synctoken supplied, this is the initial sync.
			$query = "SELECT `uri` FROM `*PREFIX*cards` WHERE `addressbookid` = ?";
			$stmt = $this->db->prepare($query);
			$stmt->execute([$addressBookId]);

			$result['added'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
		}
		return $result;
	}

	/**
	 * Adds a change record to the addressbookchanges table.
	 *
	 * @param mixed $addressBookId
	 * @param string $objectUri
	 * @param int $operation 1 = add, 2 = modify, 3 = delete
	 * @return void
	 */
	protected function addChange($addressBookId, $objectUri, $operation) {
		$sql = 'INSERT INTO `*PREFIX*addressbookchanges`(`uri`, `synctoken`, `addressbookid`, `operation`) SELECT ?, `synctoken`, ?, ? FROM `*PREFIX*addressbooks` WHERE `id` = ?';
		$stmt = $this->db->prepare($sql);
		$stmt->execute([
			$objectUri,
			$addressBookId,
			$operation,
			$addressBookId
		]);
		$stmt = $this->db->prepare('UPDATE `*PREFIX*addressbooks` SET `synctoken` = `synctoken` + 1 WHERE `id` = ?');
		$stmt->execute([
			$addressBookId
		]);
	}

	/**
	 * @param resource|string $cardData
	 * @param bool $modified
	 * @return string
	 */
	private function readBlob($cardData, &$modified = false) {
		if (is_resource($cardData)) {
			$cardData = stream_get_contents($cardData);
		}

		$cardDataArray = explode("\r\n", $cardData);

		$cardDataFiltered = [];
		$removingPhoto = false;
		foreach ($cardDataArray as $line) {
			if (strpos($line, 'PHOTO:data:') === 0
				&& strpos($line, 'PHOTO:data:image/') !== 0) {
				// Filter out PHOTO data of non-images
				$removingPhoto = true;
				$modified = true;
				continue;
			}

			if ($removingPhoto) {
				if (strpos($line, ' ') === 0) {
					continue;
				}
				// No leading space means this is a new property
				$removingPhoto = false;
			}

			$cardDataFiltered[] = $line;
		}

		return implode("\r\n", $cardDataFiltered);
	}

	/**
	 * @param IShareable $shareable
	 * @param string[] $add
	 * @param string[] $remove
	 */
	public function updateShares(IShareable $shareable, $add, $remove) {
		$addressBookId = $shareable->getResourceId();
		$addressBookData = $this->getAddressBookById($addressBookId);
		$oldShares = $this->getShares($addressBookId);

		$this->sharingBackend->updateShares($shareable, $add, $remove);

		$this->dispatcher->dispatchTyped(new AddressBookShareUpdatedEvent($addressBookId, $addressBookData, $oldShares, $add, $remove));
	}

	/**
	 * Search contacts in a specific address-book
	 *
	 * @param int $addressBookId
	 * @param string $pattern which should match within the $searchProperties
	 * @param array $searchProperties defines the properties within the query pattern should match
	 * @param array $options = array() to define the search behavior
	 *    - 'escape_like_param' - If set to false wildcards _ and % are not escaped, otherwise they are
	 *    - 'limit' - Set a numeric limit for the search results
	 *    - 'offset' - Set the offset for the limited search results
	 * @return array an array of contacts which are arrays of key-value-pairs
	 */
	public function search($addressBookId, $pattern, $searchProperties, $options = []): array {
		return $this->searchByAddressBookIds([$addressBookId], $pattern, $searchProperties, $options);
	}

	/**
	 * Search contacts in all address-books accessible by a user
	 *
	 * @param string $principalUri
	 * @param string $pattern
	 * @param array $searchProperties
	 * @param array $options
	 * @return array
	 */
	public function searchPrincipalUri(string $principalUri,
									   string $pattern,
									   array $searchProperties,
									   array $options = []): array {
		$addressBookIds = array_map(static function ($row):int {
			return (int) $row['id'];
		}, $this->getAddressBooksForUser($principalUri));

		return $this->searchByAddressBookIds($addressBookIds, $pattern, $searchProperties, $options);
	}

	/**
	 * @param array $addressBookIds
	 * @param string $pattern
	 * @param array $searchProperties
	 * @param array $options
	 * @return array
	 */
	private function searchByAddressBookIds(array $addressBookIds,
											string $pattern,
											array $searchProperties,
											array $options = []): array {
		$escapePattern = !\array_key_exists('escape_like_param', $options) || $options['escape_like_param'] !== false;

		$query2 = $this->db->getQueryBuilder();

		$addressBookOr = $query2->expr()->orX();
		foreach ($addressBookIds as $addressBookId) {
			$addressBookOr->add($query2->expr()->eq('cp.addressbookid', $query2->createNamedParameter($addressBookId)));
		}

		if ($addressBookOr->count() === 0) {
			return [];
		}

		$propertyOr = $query2->expr()->orX();
		foreach ($searchProperties as $property) {
			if ($escapePattern) {
				if ($property === 'EMAIL' && strpos($pattern, ' ') !== false) {
					// There can be no spaces in emails
					continue;
				}

				if ($property === 'CLOUD' && preg_match('/[^a-zA-Z0-9 :_.@\/\-\']/', $pattern) === 1) {
					// There can be no chars in cloud ids which are not valid for user ids plus :/
					// worst case: CA61590A-BBBC-423E-84AF-E6DF01455A53@https://my.nxt/srv/
					continue;
				}
			}

			$propertyOr->add($query2->expr()->eq('cp.name', $query2->createNamedParameter($property)));
		}

		if ($propertyOr->count() === 0) {
			return [];
		}

		$query2->selectDistinct('cp.cardid')
			->from($this->dbCardsPropertiesTable, 'cp')
			->andWhere($addressBookOr)
			->andWhere($propertyOr);

		// No need for like when the pattern is empty
		if ('' !== $pattern) {
			if (!$escapePattern) {
				$query2->andWhere($query2->expr()->ilike('cp.value', $query2->createNamedParameter($pattern)));
			} else {
				$query2->andWhere($query2->expr()->ilike('cp.value', $query2->createNamedParameter('%' . $this->db->escapeLikeParameter($pattern) . '%')));
			}
		}

		if (isset($options['limit'])) {
			$query2->setMaxResults($options['limit']);
		}
		if (isset($options['offset'])) {
			$query2->setFirstResult($options['offset']);
		}

		$result = $query2->execute();
		$matches = $result->fetchAll();
		$result->closeCursor();
		$matches = array_map(function ($match) {
			return (int)$match['cardid'];
		}, $matches);

		$query = $this->db->getQueryBuilder();
		$query->select('c.addressbookid', 'c.carddata', 'c.uri')
			->from($this->dbCardsTable, 'c')
			->where($query->expr()->in('c.id', $query->createNamedParameter($matches, IQueryBuilder::PARAM_INT_ARRAY)));

		$result = $query->execute();
		$cards = $result->fetchAll();

		$result->closeCursor();

		return array_map(function ($array) {
			$array['addressbookid'] = (int) $array['addressbookid'];
			$modified = false;
			$array['carddata'] = $this->readBlob($array['carddata'], $modified);
			if ($modified) {
				$array['size'] = strlen($array['carddata']);
			}
			return $array;
		}, $cards);
	}

	/**
	 * @param int $bookId
	 * @param string $name
	 * @return array
	 */
	public function collectCardProperties($bookId, $name) {
		$query = $this->db->getQueryBuilder();
		$result = $query->selectDistinct('value')
			->from($this->dbCardsPropertiesTable)
			->where($query->expr()->eq('name', $query->createNamedParameter($name)))
			->andWhere($query->expr()->eq('addressbookid', $query->createNamedParameter($bookId)))
			->execute();

		$all = $result->fetchAll(PDO::FETCH_COLUMN);
		$result->closeCursor();

		return $all;
	}

	/**
	 * get URI from a given contact
	 *
	 * @param int $id
	 * @return string
	 */
	public function getCardUri($id) {
		$query = $this->db->getQueryBuilder();
		$query->select('uri')->from($this->dbCardsTable)
			->where($query->expr()->eq('id', $query->createParameter('id')))
			->setParameter('id', $id);

		$result = $query->execute();
		$uri = $result->fetch();
		$result->closeCursor();

		if (!isset($uri['uri'])) {
			throw new \InvalidArgumentException('Card does not exists: ' . $id);
		}

		return $uri['uri'];
	}

	/**
	 * return contact with the given URI
	 *
	 * @param int $addressBookId
	 * @param string $uri
	 * @returns array
	 */
	public function getContact($addressBookId, $uri) {
		$result = [];
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->dbCardsTable)
			->where($query->expr()->eq('uri', $query->createNamedParameter($uri)))
			->andWhere($query->expr()->eq('addressbookid', $query->createNamedParameter($addressBookId)));
		$queryResult = $query->execute();
		$contact = $queryResult->fetch();
		$queryResult->closeCursor();

		if (is_array($contact)) {
			$modified = false;
			$contact['etag'] = '"' . $contact['etag'] . '"';
			$contact['carddata'] = $this->readBlob($contact['carddata'], $modified);
			if ($modified) {
				$contact['size'] = strlen($contact['carddata']);
			}

			$result = $contact;
		}

		return $result;
	}

	/**
	 * Returns the list of people whom this address book is shared with.
	 *
	 * Every element in this array should have the following properties:
	 *   * href - Often a mailto: address
	 *   * commonName - Optional, for example a first + last name
	 *   * status - See the Sabre\CalDAV\SharingPlugin::STATUS_ constants.
	 *   * readOnly - boolean
	 *   * summary - Optional, a description for the share
	 *
	 * @return array
	 */
	public function getShares($addressBookId) {
		return $this->sharingBackend->getShares($addressBookId);
	}

	/**
	 * update properties table
	 *
	 * @param int $addressBookId
	 * @param string $cardUri
	 * @param string $vCardSerialized
	 */
	protected function updateProperties($addressBookId, $cardUri, $vCardSerialized) {
		$cardId = $this->getCardId($addressBookId, $cardUri);
		$vCard = $this->readCard($vCardSerialized);

		$this->purgeProperties($addressBookId, $cardId);

		$query = $this->db->getQueryBuilder();
		$query->insert($this->dbCardsPropertiesTable)
			->values(
				[
					'addressbookid' => $query->createNamedParameter($addressBookId),
					'cardid' => $query->createNamedParameter($cardId),
					'name' => $query->createParameter('name'),
					'value' => $query->createParameter('value'),
					'preferred' => $query->createParameter('preferred')
				]
			);

		foreach ($vCard->children() as $property) {
			if (!in_array($property->name, self::$indexProperties)) {
				continue;
			}
			$preferred = 0;
			foreach ($property->parameters as $parameter) {
				if ($parameter->name === 'TYPE' && strtoupper($parameter->getValue()) === 'PREF') {
					$preferred = 1;
					break;
				}
			}
			$query->setParameter('name', $property->name);
			$query->setParameter('value', mb_substr($property->getValue(), 0, 254));
			$query->setParameter('preferred', $preferred);
			$query->execute();
		}
	}

	/**
	 * read vCard data into a vCard object
	 *
	 * @param string $cardData
	 * @return VCard
	 */
	protected function readCard($cardData) {
		return Reader::read($cardData);
	}

	/**
	 * delete all properties from a given card
	 *
	 * @param int $addressBookId
	 * @param int $cardId
	 */
	protected function purgeProperties($addressBookId, $cardId) {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->dbCardsPropertiesTable)
			->where($query->expr()->eq('cardid', $query->createNamedParameter($cardId)))
			->andWhere($query->expr()->eq('addressbookid', $query->createNamedParameter($addressBookId)));
		$query->execute();
	}

	/**
	 * get ID from a given contact
	 *
	 * @param int $addressBookId
	 * @param string $uri
	 * @return int
	 */
	protected function getCardId($addressBookId, $uri) {
		$query = $this->db->getQueryBuilder();
		$query->select('id')->from($this->dbCardsTable)
			->where($query->expr()->eq('uri', $query->createNamedParameter($uri)))
			->andWhere($query->expr()->eq('addressbookid', $query->createNamedParameter($addressBookId)));

		$result = $query->execute();
		$cardIds = $result->fetch();
		$result->closeCursor();

		if (!isset($cardIds['id'])) {
			throw new \InvalidArgumentException('Card does not exists: ' . $uri);
		}

		return (int)$cardIds['id'];
	}

	/**
	 * For shared address books the sharee is set in the ACL of the address book
	 *
	 * @param $addressBookId
	 * @param $acl
	 * @return array
	 */
	public function applyShareAcl($addressBookId, $acl) {
		return $this->sharingBackend->applyShareAcl($addressBookId, $acl);
	}

	private function convertPrincipal($principalUri, $toV2) {
		if ($this->principalBackend->getPrincipalPrefix() === 'principals') {
			list(, $name) = \Sabre\Uri\split($principalUri);
			if ($toV2 === true) {
				return "principals/users/$name";
			}
			return "principals/$name";
		}
		return $principalUri;
	}

	private function addOwnerPrincipal(&$addressbookInfo) {
		$ownerPrincipalKey = '{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}owner-principal';
		$displaynameKey = '{' . \OCA\DAV\DAV\Sharing\Plugin::NS_NEXTCLOUD . '}owner-displayname';
		if (isset($addressbookInfo[$ownerPrincipalKey])) {
			$uri = $addressbookInfo[$ownerPrincipalKey];
		} else {
			$uri = $addressbookInfo['principaluri'];
		}

		$principalInformation = $this->principalBackend->getPrincipalByPath($uri);
		if (isset($principalInformation['{DAV:}displayname'])) {
			$addressbookInfo[$displaynameKey] = $principalInformation['{DAV:}displayname'];
		}
	}

	/**
	 * Extract UID from vcard
	 *
	 * @param string $cardData the vcard raw data
	 * @return string the uid
	 * @throws BadRequest if no UID is available
	 */
	private function getUID($cardData) {
		if ($cardData != '') {
			$vCard = Reader::read($cardData);
			if ($vCard->UID) {
				$uid = $vCard->UID->getValue();
				return $uid;
			}
			// should already be handled, but just in case
			throw new BadRequest('vCards on CardDAV servers MUST have a UID property');
		}
		// should already be handled, but just in case
		throw new BadRequest('vCard can not be empty');
	}
}
