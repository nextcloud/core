<?php

declare(strict_types=1);

/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

/**
 * @since 19.0.0
 */
interface IAddressBookProvider {

	/**
	 * Provides the appId of the plugin
	 *
	 * @since 19.0.0
	 * @return string AppId
	 */
	public function getAppId(): string;

	/**
	 * Fetches all address books for a given principal uri
	 *
	 * @since 19.0.0
	 * @param string $principalUri E.g. principals/users/user1
	 * @return ExternalAddressBook[] Array of all address books
	 */
	public function fetchAllForAddressBookHome(string $principalUri): array;

	/**
	 * Checks whether plugin has an address book for a given principalUri and URI
	 *
	 * @since 19.0.0
	 * @param string $principalUri E.g. principals/users/user1
	 * @param string $uri E.g. personal
	 * @return bool True if address book for principalUri and URI exists, false otherwise
	 */
	public function hasAddressBookInAddressBookHome(string $principalUri, string $uri): bool;

	/**
	 * Fetches an address book for a given principalUri and URI
	 * Returns null if address book does not exist
	 *
	 * @param string $principalUri E.g. principals/users/user1
	 * @param string $uri E.g. personal
	 *
	 * @return ExternalAddressBook|null address book if it exists, null otherwise
	 *@since 19.0.0
	 */
	public function getAddressBookInAddressBookHome(string $principalUri, string $uri): ?ExternalAddressBook;

}
