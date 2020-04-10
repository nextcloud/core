<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

namespace OC\Files\ObjectStore;

use OCP\IUser;

/**
 * Class Mapper
 *
 * @package OC\Files\ObjectStore
 *
 * Map a user to a bucket.
 */
class Mapper {
	/** @var IUser */
	private $user;

	/**
	 * Mapper constructor.
	 *
	 * @param IUser $user
	 */
	public function __construct(IUser $user) {
		$this->user = $user;
	}

	/**
	 * @param int $numBuckets
	 * @return string
	 */
	public function getBucket($numBuckets = 64) {
		$hash = md5($this->user->getUID());
		$num = hexdec(substr($hash, 0, 4));
		return (string)($num % $numBuckets);
	}
}
