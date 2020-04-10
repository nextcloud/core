<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP\Search;

/**
 * Provides a template for search functionality throughout ownCloud;
 * @since 8.0.0
 */
abstract class PagedProvider extends Provider {

	/**
	 * show all results
	 * @since 8.0.0
	 */
	const SIZE_ALL = 0;

	/**
	 * Constructor
	 * @param array $options
	 * @since 8.0.0
	 */
	public function __construct($options) {
		parent::__construct($options);
	}

	/**
	 * Search for $query
	 * @param string $query
	 * @return array An array of OCP\Search\Result's
	 * @since 8.0.0
	 */
	public function search($query) {
		// old apps might assume they get all results, so we use SIZE_ALL
		return $this->searchPaged($query, 1, self::SIZE_ALL);
	}

	/**
	 * Search for $query
	 * @param string $query
	 * @param int $page pages start at page 1
	 * @param int $size 0 = SIZE_ALL
	 * @return array An array of OCP\Search\Result's
	 * @since 8.0.0
	 */
	abstract public function searchPaged($query, $page, $size);
}
