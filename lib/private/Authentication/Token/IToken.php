<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Authentication\Token;

use JsonSerializable;

interface IToken extends JsonSerializable {

	const TEMPORARY_TOKEN = 0;
	const PERMANENT_TOKEN = 1;
	const WIPE_TOKEN = 2;
	const DO_NOT_REMEMBER = 0;
	const REMEMBER = 1;

	/**
	 * Get the token ID
	 *
	 * @return int
	 */
	public function getId(): int;

	/**
	 * Get the user UID
	 *
	 * @return string
	 */
	public function getUID(): string;

	/**
	 * Get the login name used when generating the token
	 *
	 * @return string
	 */
	public function getLoginName(): string;

	/**
	 * Get the (encrypted) login password
	 *
	 * @return string|null
	 */
	public function getPassword();

	/**
	 * Get the timestamp of the last password check
	 *
	 * @return int
	 */
	public function getLastCheck(): int;

	/**
	 * Set the timestamp of the last password check
	 *
	 * @param int $time
	 */
	public function setLastCheck(int $time);

	/**
	 * Get the authentication scope for this token
	 *
	 * @return string
	 */
	public function getScope(): string;

	/**
	 * Get the authentication scope for this token
	 *
	 * @return array
	 */
	public function getScopeAsArray(): array;

	/**
	 * Set the authentication scope for this token
	 *
	 * @param array $scope
	 */
	public function setScope($scope);

	/**
	 * Get the name of the token
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Get the remember state of the token
	 *
	 * @return int
	 */
	public function getRemember(): int;

	/**
	 * Set the token
	 *
	 * @param string $token
	 */
	public function setToken(string $token);

	/**
	 * Set the password
	 *
	 * @param string $password
	 */
	public function setPassword(string $password);

	/**
	 * Set the expiration time of the token
	 *
	 * @param int|null $expires
	 */
	public function setExpires($expires);
}
