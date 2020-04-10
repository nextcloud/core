<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * Class ProxyMapper
 *
 * @package OCA\DAV\CalDAV\Proxy
 */
class ProxyMapper extends QBMapper {

	const PERMISSION_READ  = 1;
	const PERMISSION_WRITE = 2;

	/**
	 * ProxyMapper constructor.
	 *
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'dav_cal_proxy', Proxy::class);
	}

	/**
	 * @param string $proxyId The principal uri that can act as a proxy for the resulting calendars
	 *
	 * @return Proxy[]
	 */
	public function getProxiesFor(string $proxyId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('proxy_id', $qb->createNamedParameter($proxyId)));

		return $this->findEntities($qb);
	}

	/**
	 * @param string $ownerId The principal uri that has the resulting proxies for their calendars
	 *
	 * @return Proxy[]
	 */
	public function getProxiesOf(string $ownerId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('owner_id', $qb->createNamedParameter($ownerId)));

		return $this->findEntities($qb);
	}
}
