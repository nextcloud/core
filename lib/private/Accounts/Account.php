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

namespace OC\Accounts;

use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountProperty;
use OCP\Accounts\PropertyDoesNotExistException;
use OCP\IUser;

class Account implements IAccount {

	/** @var IAccountProperty[] */
	private $properties = [];

	/** @var IUser */
	private $user;

	public function __construct(IUser $user) {
		$this->user = $user;
	}

	public function setProperty(string $property, string $value, string $scope, string $verified): IAccount {
		$this->properties[$property] = new AccountProperty($property, $value, $scope, $verified);
		return $this;
	}

	public function getProperty(string $property): IAccountProperty {
		if (!array_key_exists($property, $this->properties)) {
			throw new PropertyDoesNotExistException($property);
		}
		return $this->properties[$property];
	}

	public function getProperties(): array {
		return $this->properties;
	}

	public function getFilteredProperties(string $scope = null, string $verified = null): array {
		return \array_filter($this->properties, function (IAccountProperty $obj) use ($scope, $verified) {
			if ($scope !== null && $scope !== $obj->getScope()) {
				return false;
			}
			if ($verified !== null && $verified !== $obj->getVerified()) {
				return false;
			}
			return true;
		});
	}

	public function jsonSerialize() {
		return $this->properties;
	}

	public function getUser(): IUser {
		return $this->user;
	}
}
