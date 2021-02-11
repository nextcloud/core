<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arne Hamann <kontakt+github@arne.email>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author call-me-matt <nextcloud@matthiasheinisch.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
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

use OCP\Constants;
use OCP\IAddressBook;
use OCP\IURLGenerator;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;
use Sabre\VObject\UUIDUtil;

class AddressBookImpl implements IAddressBook {

	/** @var CardDavBackend */
	private $backend;

	/** @var array */
	private $addressBookInfo;

	/** @var AddressBook */
	private $addressBook;

	/** @var IURLGenerator */
	private $urlGenerator;

	/**
	 * AddressBookImpl constructor.
	 *
	 * @param AddressBook $addressBook
	 * @param array $addressBookInfo
	 * @param CardDavBackend $backend
	 * @param IUrlGenerator $urlGenerator
	 */
	public function __construct(
			AddressBook $addressBook,
			array $addressBookInfo,
			CardDavBackend $backend,
			IURLGenerator $urlGenerator) {
		$this->addressBook = $addressBook;
		$this->addressBookInfo = $addressBookInfo;
		$this->backend = $backend;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @return string defining the technical unique key
	 * @since 5.0.0
	 */
	public function getKey() {
		return $this->addressBookInfo['id'];
	}

	/**
	 * @return string defining the unique uri
	 * @since 16.0.0
	 */
	public function getUri(): string {
		return $this->addressBookInfo['uri'];
	}

	/**
	 * In comparison to getKey() this function returns a human readable (maybe translated) name
	 *
	 * @return mixed
	 * @since 5.0.0
	 */
	public function getDisplayName() {
		return $this->addressBookInfo['{DAV:}displayname'];
	}

	/**
	 * @param string $pattern which should match within the $searchProperties
	 * @param array $searchProperties defines the properties within the query pattern should match
	 * @param array $options Options to define the output format and search behavior
	 * 	- 'types' boolean (since 15.0.0) If set to true, fields that come with a TYPE property will be an array
	 *    example: ['id' => 5, 'FN' => 'Thomas Tanghus', 'EMAIL' => ['type => 'HOME', 'value' => 'g@h.i']]
	 * 	- 'escape_like_param' - If set to false wildcards _ and % are not escaped
	 * 	- 'limit' - Set a numeric limit for the search results
	 * 	- 'offset' - Set the offset for the limited search results
	 * @return array an array of contacts which are arrays of key-value-pairs
	 *  example result:
	 *  [
	 *		['id' => 0, 'FN' => 'Thomas Müller', 'EMAIL' => 'a@b.c', 'GEO' => '37.386013;-122.082932'],
	 *		['id' => 5, 'FN' => 'Thomas Tanghus', 'EMAIL' => ['d@e.f', 'g@h.i']]
	 *	]
	 * @since 5.0.0
	 */
	public function search($pattern, $searchProperties, $options) {
		$results = $this->backend->search($this->getKey(), $pattern, $searchProperties, $options);

		$withTypes = \array_key_exists('types', $options) && $options['types'] === true;

		$vCards = [];
		foreach ($results as $result) {
			$vCards[] = $this->vCard2Array($result['uri'], $this->readCard($result['carddata']), $withTypes);
		}

		return $vCards;
	}

	/**
	 * @param array $properties this array if key-value-pairs defines a contact
	 * @return array an array representing the contact just created or updated
	 * @since 5.0.0
	 */
	public function createOrUpdate($properties) {
		$update = false;
		if (!isset($properties['URI'])) { // create a new contact
			$uid = $this->createUid();
			$uri = $uid . '.vcf';
			$vCard = $this->createEmptyVCard($uid);
		} else { // update existing contact
			$uri = $properties['URI'];
			$vCardData = $this->backend->getCard($this->getKey(), $uri);
			$vCard = $this->readCard($vCardData['carddata']);
			$update = true;
		}

		foreach ($properties as $key => $value) {
			if (is_array($value)) {
				$vCard->remove($key);
				foreach ($value as $entry) {
					if (($key === "ADR" || $key === "PHOTO") && is_string($entry["value"])) {
						$entry["value"] = stripslashes($entry["value"]);
						$entry["value"] = explode(';', $entry["value"]);
					}
					$property = $vCard->createProperty($key, $entry["value"]);
					if (isset($entry["type"])) {
						$property->add('TYPE', $entry["type"]);
					}
					$vCard->add($property);
				}
			} elseif ($key !== 'URI') {
				$vCard->$key = $vCard->createProperty($key, $value);
			}
		}

		if ($update) {
			$this->backend->updateCard($this->getKey(), $uri, $vCard->serialize());
		} else {
			$this->backend->createCard($this->getKey(), $uri, $vCard->serialize());
		}

		return $this->vCard2Array($uri, $vCard);
	}

	/**
	 * @return mixed
	 * @since 5.0.0
	 */
	public function getPermissions() {
		$permissions = $this->addressBook->getACL();
		$result = 0;
		foreach ($permissions as $permission) {
			switch ($permission['privilege']) {
				case '{DAV:}read':
					$result |= Constants::PERMISSION_READ;
					break;
				case '{DAV:}write':
					$result |= Constants::PERMISSION_CREATE;
					$result |= Constants::PERMISSION_UPDATE;
					break;
				case '{DAV:}all':
					$result |= Constants::PERMISSION_ALL;
					break;
			}
		}

		return $result;
	}

	/**
	 * @param object $id the unique identifier to a contact
	 * @return bool successful or not
	 * @since 5.0.0
	 */
	public function delete($id) {
		$uri = $this->backend->getCardUri($id);
		return $this->backend->deleteCard($this->addressBookInfo['id'], $uri);
	}

	/**
	 * read vCard data into a vCard object
	 *
	 * @param string $cardData
	 * @return VCard
	 */
	protected function readCard($cardData) {
		return  Reader::read($cardData);
	}

	/**
	 * create UID for contact
	 *
	 * @return string
	 */
	protected function createUid() {
		do {
			$uid = $this->getUid();
			$contact = $this->backend->getContact($this->getKey(), $uid . '.vcf');
		} while (!empty($contact));

		return $uid;
	}

	/**
	 * getUid is only there for testing, use createUid instead
	 */
	protected function getUid() {
		return UUIDUtil::getUUID();
	}

	/**
	 * create empty vcard
	 *
	 * @param string $uid
	 * @return VCard
	 */
	protected function createEmptyVCard($uid) {
		$vCard = new VCard();
		$vCard->UID = $uid;
		return $vCard;
	}

	/**
	 * create array with all vCard properties
	 *
	 * @param string $uri
	 * @param VCard $vCard
	 * @param boolean $withTypes (optional) return the values as arrays of value/type pairs
	 * @return array
	 */
	protected function vCard2Array($uri, VCard $vCard, $withTypes = false) {
		$result = [
			'URI' => $uri,
		];

		foreach ($vCard->children() as $property) {
			if ($property->name === 'PHOTO' && $property->getValueType() === 'BINARY') {
				$url = $this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->linkTo('', 'remote.php') . '/dav/');
				$url .= implode('/', [
					'addressbooks',
					substr($this->addressBookInfo['principaluri'], 11), //cut off 'principals/'
					$this->addressBookInfo['uri'],
					$uri
				]) . '?photo';

				$result['PHOTO'] = 'VALUE=uri:' . $url;
			} elseif (in_array($property->name, ['URL', 'GEO', 'CLOUD', 'ADR', 'EMAIL', 'IMPP', 'TEL', 'X-SOCIALPROFILE', 'RELATED', 'LANG', 'X-ADDRESSBOOKSERVER-MEMBER'])) {
				if (!isset($result[$property->name])) {
					$result[$property->name] = [];
				}

				$type = $this->getTypeFromProperty($property);
				if ($withTypes) {
					$result[$property->name][] = [
						'type' => $type,
						'value' => $property->getValue()
					];
				} else {
					$result[$property->name][] = $property->getValue();
				}
			} else {
				$result[$property->name] = $property->getValue();
			}
		}

		if ($this->isSystemAddressBook()) {
			$result['isLocalSystemBook'] = true;
		}
		return $result;
	}

	/**
	 * Get the type of the current property
	 *
	 * @param Property $property
	 * @return null|string
	 */
	protected function getTypeFromProperty(Property $property) {
		$parameters = $property->parameters();
		// Type is the social network, when it's empty we don't need this.
		if (isset($parameters['TYPE'])) {
			/** @var \Sabre\VObject\Parameter $type */
			$type = $parameters['TYPE'];
			return $type->getValue();
		}

		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function isShared(): bool {
		if (!isset($this->addressBookInfo['{http://owncloud.org/ns}owner-principal'])) {
			return false;
		}

		return $this->addressBookInfo['principaluri']
			!== $this->addressBookInfo['{http://owncloud.org/ns}owner-principal'];
	}

	/**
	 * @inheritDoc
	 */
	public function isSystemAddressBook(): bool {
		return $this->addressBookInfo['principaluri'] === 'principals/system/system' && (
			$this->addressBookInfo['uri'] === 'system' ||
			$this->addressBookInfo['{DAV:}displayname'] === $this->urlGenerator->getBaseUrl()
		);
	}
}
