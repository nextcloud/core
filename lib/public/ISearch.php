<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andrew Brown <andrew@casabrown.com>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jakob Sack <mail@jakobsack.de>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP;

/**
 * Small Interface for Search
 * @since 7.0.0
 */
interface ISearch {

	/**
	 * Search all providers for $query
	 * @param string $query
	 * @param string[] $inApps optionally limit results to the given apps
	 * @param int $page pages start at page 1
	 * @param int $size
	 * @return array An array of OCP\Search\Result's
	 * @since 8.0.0
	 */
	public function searchPaged($query, array $inApps = [], $page = 1, $size = 30);

	/**
	 * Register a new search provider to search with
	 * @param string $class class name of a OCP\Search\Provider
	 * @param array $options optional
	 * @since 7.0.0
	 */
	public function registerProvider($class, array $options = []);

	/**
	 * Remove one existing search provider
	 * @param string $provider class name of a OCP\Search\Provider
	 * @since 7.0.0
	 */
	public function removeProvider($provider);

	/**
	 * Remove all registered search providers
	 * @since 7.0.0
	 */
	public function clearProviders();

}
