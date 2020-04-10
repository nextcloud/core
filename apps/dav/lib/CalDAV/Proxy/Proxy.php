<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\CalDAV\Proxy;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getOwnerId()
 * @method void setOwnerId(string $ownerId)
 * @method string getProxyId()
 * @method void setProxyId(string $proxyId)
 * @method int getPermissions()
 * @method void setPermissions(int $permissions)
 */
class Proxy extends Entity {

	/** @var string */
	protected $ownerId;
	/** @var string */
	protected $proxyId;
	/** @var int */
	protected $permissions;

	public function __construct() {
		$this->addType('ownerId', 'string');
		$this->addType('proxyId', 'string');
		$this->addType('permissions', 'int');
	}
}
