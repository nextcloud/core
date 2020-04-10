<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCP\Collaboration\Collaborators;

/**
 * Interface ISearchResult
 *
 * @package OCP\Collaboration\Collaborators
 * @since 13.0.0
 */
interface ISearchResult {
	/**
	 * @param SearchResultType $type
	 * @param array $matches
	 * @param array|null $exactMatches
	 * @since 13.0.0
	 */
	public function addResultSet(SearchResultType $type, array $matches, array $exactMatches = null);

	/**
	 * @param SearchResultType $type
	 * @param string $collaboratorId
	 * @return bool
	 * @since 13.0.0
	 */
	public function hasResult(SearchResultType $type, $collaboratorId);

	/**
	 * @param SearchResultType $type
	 * @since 13.0.0
	 */
	public function unsetResult(SearchResultType $type);

	/**
	 * @param SearchResultType $type
	 * @since 13.0.0
	 */
	public function markExactIdMatch(SearchResultType $type);

	/**
	 * @param SearchResultType $type
	 * @return bool
	 * @since 13.0.0
	 */
	public function hasExactIdMatch(SearchResultType $type);

	/**
	 * @return array
	 * @since 13.0.0
	 */
	public function asArray();
}
