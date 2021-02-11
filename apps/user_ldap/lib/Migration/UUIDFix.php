<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\User_LDAP\Migration;

use OC\BackgroundJob\QueuedJob;
use OCA\User_LDAP\Mapping\AbstractMapping;
use OCA\User_LDAP\Proxy;
use OCA\User_LDAP\User_Proxy;

abstract class UUIDFix extends QueuedJob {
	/** @var  AbstractMapping */
	protected $mapper;

	/** @var  Proxy */
	protected $proxy;

	public function run($argument) {
		$isUser = $this->proxy instanceof User_Proxy;
		foreach ($argument['records'] as $record) {
			$access = $this->proxy->getLDAPAccess($record['name']);
			$uuid = $access->getUUID($record['dn'], $isUser);
			if ($uuid === false) {
				// record not found, no prob, continue with the next
				continue;
			}
			if ($uuid !== $record['uuid']) {
				$this->mapper->setUUIDbyDN($uuid, $record['dn']);
			}
		}
	}
}
