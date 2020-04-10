<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Route;

use OCP\ILogger;

class CachingRouter extends Router {
	/**
	 * @var \OCP\ICache
	 */
	protected $cache;

	/**
	 * @param \OCP\ICache $cache
	 * @param ILogger $logger
	 */
	public function __construct($cache, ILogger $logger) {
		$this->cache = $cache;
		parent::__construct($logger);
	}

	/**
	 * Generate url based on $name and $parameters
	 *
	 * @param string $name Name of the route to use.
	 * @param array $parameters Parameters for the route
	 * @param bool $absolute
	 * @return string
	 */
	public function generate($name, $parameters = [], $absolute = false) {
		asort($parameters);
		$key = $this->context->getHost() . '#' . $this->context->getBaseUrl() . $name . sha1(json_encode($parameters)) . (int)$absolute;
		$cachedKey = $this->cache->get($key);
		if ($cachedKey) {
			return $cachedKey;
		} else {
			$url = parent::generate($name, $parameters, $absolute);
			if ($url) {
				$this->cache->set($key, $url, 3600);
			}
			return $url;
		}
	}
}
