<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCP\Search;

/**
 * The query objected passed into \OCP\Search\IProvider::search
 *
 * This mainly wraps the search term, but will ensure that Nextcloud can add new
 * optional properties to a search request without having break the interface of
 * \OCP\Search\IProvider::search.
 *
 * @see \OCP\Search\IProvider::search
 *
 * @since 20.0.0
 */
interface ISearchQuery {

	/**
	 * @since 20.0.0
	 */
	public const SORT_DATE_DESC = 1;

	/**
	 * Get the user-entered search term to find matches for
	 *
	 * @return string the search term
	 * @since 20.0.0
	 */
	public function getTerm(): string;

	/**
	 * Get the sort order of results as defined as SORT_* constants on this interface
	 *
	 * @return int
	 * @since 20.0.0
	 */
	public function getSortOrder(): int;

	/**
	 * Get the number of items to return for a paginated result
	 *
	 * @return int
	 * @see \OCP\Search\IProvider for details
	 * @since 20.0.0
	 */
	public function getLimit(): int;

	/**
	 * Get the app-specific cursor of the tail of the previous result entries
	 *
	 * @return int|string|null
	 * @see \OCP\Search\IProvider for details
	 * @since 20.0.0
	 */
	public function getCursor();

	/**
	 * @return string
	 * @since 20.0.0
	 */
	public function getRoute(): string;

	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function getRouteParameters(): array;
}
