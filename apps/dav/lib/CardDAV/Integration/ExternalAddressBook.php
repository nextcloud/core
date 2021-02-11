<?php

declare(strict_types=1);

/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
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
namespace OCA\DAV\CardDAV\Integration;

use Sabre\CardDAV\IAddressBook;
use Sabre\DAV;

/**
 * @since 19.0.0
 */
abstract class ExternalAddressBook implements IAddressBook, DAV\IProperties {

	/** @var string */
	private const PREFIX = 'z-app-generated';

	/**
	 * @var string
	 *
	 * Double dash is a valid delimiter,
	 * because it will always split the URIs correctly:
	 * - our prefix contains only one dash and won't be split
	 * - appIds are not allowed to contain dashes as per spec:
	 * > must contain only lowercase ASCII characters and underscore
	 * - explode has a limit of three, so even if the app-generated
	 *   URI has double dashes, it won't be split
	 */
	private const DELIMITER = '--';

	/** @var string */
	private $appId;

	/** @var string */
	private $uri;

	/**
	 * @param string $appId
	 * @param string $uri
	 */
	public function __construct(string $appId, string $uri) {
		$this->appId = $appId;
		$this->uri = $uri;
	}

	/**
	 * @inheritDoc
	 */
	final public function getName() {
		return implode(self::DELIMITER, [
			self::PREFIX,
			$this->appId,
			$this->uri,
		]);
	}

	/**
	 * @inheritDoc
	 */
	final public function setName($name) {
		throw new DAV\Exception\MethodNotAllowed('Renaming address books is not yet supported');
	}

	/**
	 * @inheritDoc
	 */
	final public function createDirectory($name) {
		throw new DAV\Exception\MethodNotAllowed('Creating collections in address book objects is not allowed');
	}

	/**
	 * Checks whether the address book uri is app-generated
	 *
	 * @param string $uri
	 *
	 * @return bool
	 */
	public static function isAppGeneratedAddressBook(string $uri): bool {
		return strpos($uri, self::PREFIX) === 0 && substr_count($uri, self::DELIMITER) >= 2;
	}

	/**
	 * Splits an app-generated uri into appId and uri
	 *
	 * @param string $uri
	 *
	 * @return array
	 */
	public static function splitAppGeneratedAddressBookUri(string $uri): array {
		$array = array_slice(explode(self::DELIMITER, $uri, 3), 1);
		// Check the array has expected amount of elements
		// and none of them is an empty string
		if (\count($array) !== 2 || \in_array('', $array, true)) {
			throw new \InvalidArgumentException('Provided address book uri was not app-generated');
		}

		return $array;
	}

	/**
	 * Checks whether a address book name the user wants to create violates
	 * the reserved name for URIs
	 *
	 * @param string $uri
	 *
	 * @return bool
	 */
	public static function doesViolateReservedName(string $uri): bool {
		return strpos($uri, self::PREFIX) === 0;
	}
}
