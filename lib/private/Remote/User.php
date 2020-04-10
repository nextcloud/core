<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Remote;

use OCP\Remote\IUser;

class User implements IUser {
	const EXPECTED_KEYS = [
		'id',
		'email',
		'displayname',
		'phone',
		'address',
		'website',
		'groups',
		'language',
		'quota'
	];

	/** @var array */
	private $data;

	public function __construct(array $data) {
		$this->data = $data;
	}


	/**
	 * @return string
	 */
	public function getUserId() {
		return $this->data['id'];
	}

	/**
	 * @return string
	 */
	public function getEmail() {
		return $this->data['email'];
	}

	/**
	 * @return string
	 */
	public function getDisplayName() {
		return $this->data['displayname'];
	}

	/**
	 * @return string
	 */
	public function getPhone() {
		return $this->data['phone'];
	}

	/**
	 * @return string
	 */
	public function getAddress() {
		return $this->data['address'];
	}

	/**
	 * @return string
	 */
	public function getWebsite() {
		return $this->data['website'];
	}

	/**
	 * @return string
	 */
	public function getTwitter() {
		return isset($this->data['twitter']) ? $this->data['twitter'] : '';
	}

	/**
	 * @return string[]
	 */
	public function getGroups() {
		return $this->data['groups'];
	}

	/**
	 * @return string
	 */
	public function getLanguage() {
		return $this->data['language'];
	}

	/**
	 * @return int
	 */
	public function getUsedSpace() {
		return $this->data['quota']['used'];
	}

	/**
	 * @return int
	 */
	public function getFreeSpace() {
		return $this->data['quota']['free'];
	}

	/**
	 * @return int
	 */
	public function getTotalSpace() {
		return $this->data['quota']['total'];
	}

	/**
	 * @return int
	 */
	public function getQuota() {
		return $this->data['quota']['quota'];
	}
}
