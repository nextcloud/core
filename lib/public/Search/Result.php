<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andrew Brown <andrew@casabrown.com>
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

namespace OCP\Search;

/**
 * The generic result of a search
 * @since 7.0.0
 */
class Result {

	/**
	 * A unique identifier for the result, usually given as the item ID in its
	 * corresponding application.
	 * @var string
	 * @since 7.0.0
	 */
	public $id;

	/**
	 * The name of the item returned; this will be displayed in the search
	 * results.
	 * @var string
	 * @since 7.0.0
	 */
	public $name;

	/**
	 * URL to the application item.
	 * @var string
	 * @since 7.0.0
	 */
	public $link;

	/**
	 * The type of search result returned; for consistency, name this the same
	 * as the class name (e.g. \OC\Search\File -> 'file') in lowercase.
	 * @var string
	 * @since 7.0.0
	 */
	public $type = 'generic';

	/**
	 * Create a new search result
	 * @param string $id unique identifier from application: '[app_name]/[item_identifier_in_app]'
	 * @param string $name displayed text of result
	 * @param string $link URL to the result within its app
	 * @since 7.0.0
	 */
	public function __construct($id = null, $name = null, $link = null) {
		$this->id = $id;
		$this->name = $name;
		$this->link = $link;
	}
}
