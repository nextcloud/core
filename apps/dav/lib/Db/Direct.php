<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method string getToken()
 * @method void setToken(string $token)
 * @method int getExpiration()
 * @method void setExpiration(int $expiration)
 */
class Direct extends Entity {
	/** @var string */
	protected $userId;

	/** @var int */
	protected $fileId;

	/** @var string */
	protected $token;

	/** @var int */
	protected $expiration;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('fileId', 'int');
		$this->addType('token', 'string');
		$this->addType('expiration', 'int');
	}
}
