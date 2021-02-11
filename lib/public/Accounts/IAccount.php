<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCP\Accounts;

use OCP\IUser;

/**
 * Interface IAccount
 *
 * @since 15.0.0
 */
interface IAccount extends \JsonSerializable {

	/**
	 * Set a property with data
	 *
	 * @since 15.0.0
	 *
	 * @param string $property  Must be one of the PROPERTY_ prefixed constants of \OCP\Accounts\IAccountManager
	 * @param string $value
	 * @param string $scope Must be one of the VISIBILITY_ prefixed constants of \OCP\Accounts\IAccountManager
	 * @param string $verified \OCP\Accounts\IAccountManager::NOT_VERIFIED | \OCP\Accounts\IAccountManager::VERIFICATION_IN_PROGRESS | \OCP\Accounts\IAccountManager::VERIFIED
	 * @return IAccount
	 */
	public function setProperty(string $property, string $value, string $scope, string $verified): IAccount;

	/**
	 * Get a property by its key
	 *
	 * @since 15.0.0
	 *
	 * @param string $property Must be one of the PROPERTY_ prefixed constants of \OCP\Accounts\IAccountManager
	 * @return IAccountProperty
	 * @throws PropertyDoesNotExistException
	 */
	public function getProperty(string $property): IAccountProperty;

	/**
	 * Get all properties of an account
	 *
	 * @since 15.0.0
	 *
	 * @return IAccountProperty[]
	 */
	public function getProperties(): array;

	/**
	 * Get all properties that match the provided filters for scope and verification status
	 *
	 * @since 15.0.0
	 *
	 * @param string $scope Must be one of the VISIBILITY_ prefixed constants of \OCP\Accounts\IAccountManager
	 * @param string $verified \OCP\Accounts\IAccountManager::NOT_VERIFIED | \OCP\Accounts\IAccountManager::VERIFICATION_IN_PROGRESS | \OCP\Accounts\IAccountManager::VERIFIED
	 * @return IAccountProperty[]
	 */
	public function getFilteredProperties(string $scope = null, string $verified = null): array;

	/**
	 * Get the related user for the account data
	 *
	 * @since 15.0.0
	 *
	 * @return IUser
	 */
	public function getUser(): IUser;
}
