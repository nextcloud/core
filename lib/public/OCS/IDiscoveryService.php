<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP\OCS;

/**
 * Interface IDiscoveryService
 *
 * Allows you to discover OCS end-points on a remote server
 *
 * @since 12.0.0
 */
interface IDiscoveryService {

	/**
	 * Discover OCS end-points
	 *
	 * If no valid discovery data is found the defaults are returned
	 *
	 * @since 12.0.0
	 *
	 * @param string $remote
	 * @param string $service the service you want to discover
	 * @param bool $skipCache We won't check if the data is in the cache. This is useful if a background job is updating the status - Added in 14.0.0
	 * @return array
	 */
	public function discover(string $remote, string $service, bool $skipCache = false): array;
}
