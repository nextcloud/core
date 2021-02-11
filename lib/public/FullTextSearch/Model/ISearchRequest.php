<?php

declare(strict_types=1);

/**
 * @copyright 2018
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
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

namespace OCP\FullTextSearch\Model;

/**
 * Interface ISearchRequest
 *
 * When a search request is initiated, from a request from the front-end or using
 * the IFullTextSearchManager::search() method, FullTextSearch will create a
 * SearchRequest object, based on this interface.
 *
 * The object will be passed to the targeted Content Provider so it can convert
 * search options using available method.
 *
 * The object is then encapsulated in a SearchResult and send to the
 * Search Platform.
 *
 * @since 15.0.0
 *
 *
 */
interface ISearchRequest {


	/**
	 * Get the maximum number of results to be returns by the Search Platform.
	 *
	 * @since 15.0.0
	 *
	 * @return int
	 */
	public function getSize(): int;


	/**
	 * Get the current page.
	 * Used by pagination.
	 *
	 * @since 15.0.0
	 *
	 * @return int
	 */
	public function getPage(): int;


	/**
	 * Get the author of the request.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getAuthor(): string;

	/**
	 * Get the searched string.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getSearch(): string;

	/**
	 * Set the searched string.
	 *
	 * @param string $search
	 *
	 * @since 17.0.0
	 *
	 * @return ISearchRequest
	 */
	public function setSearch(string $search): ISearchRequest;

	/**
	 * Extends the searched string.
	 *
	 * @since 17.0.0
	 *
	 * @param string $search
	 *
	 * @return ISearchRequest
	 */
	public function addSearch(string $search): ISearchRequest;


	/**
	 * Get the value of an option (as string).
	 *
	 * @since 15.0.0
	 *
	 * @param string $option
	 * @param string $default
	 *
	 * @return string
	 */
	public function getOption(string $option, string $default = ''): string;

	/**
	 * Get the value of an option (as array).
	 *
	 * @since 15.0.0
	 *
	 * @param string $option
	 * @param array $default
	 *
	 * @return array
	 */
	public function getOptionArray(string $option, array $default = []): array;


	/**
	 * Limit the search to a part of the document.
	 *
	 * @since 15.0.0
	 *
	 * @param string $part
	 *
	 * @return ISearchRequest
	 */
	public function addPart(string $part): ISearchRequest;

	/**
	 * Limit the search to an array of parts of the document.
	 *
	 * @since 15.0.0
	 *
	 * @param array $parts
	 *
	 * @return ISearchRequest
	 */
	public function setParts(array $parts): ISearchRequest;

	/**
	 * Get the parts the search is limited to.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getParts(): array;


	/**
	 * Limit the search to a specific meta tag.
	 *
	 * @since 15.0.0
	 *
	 * @param string $tag
	 *
	 * @return ISearchRequest
	 */
	public function addMetaTag(string $tag): ISearchRequest;

	/**
	 * Get the meta tags the search is limited to.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getMetaTags(): array;

	/**
	 * Limit the search to an array of meta tags.
	 *
	 * @since 15.0.0
	 *
	 * @param array $tags
	 *
	 * @return ISearchRequest
	 */
	public function setMetaTags(array $tags): IsearchRequest;


	/**
	 * Limit the search to a specific sub tag.
	 *
	 * @since 15.0.0
	 *
	 * @param string $source
	 * @param string $tag
	 *
	 * @return ISearchRequest
	 */
	public function addSubTag(string $source, string $tag): ISearchRequest;

	/**
	 * Get the sub tags the search is limited to.
	 *
	 * @since 15.0.0
	 *
	 * @param bool $formatted
	 *
	 * @return array
	 */
	public function getSubTags(bool $formatted): array;

	/**
	 * Limit the search to an array of sub tags.
	 *
	 * @since 15.0.0
	 *
	 * @param array $tags
	 *
	 * @return ISearchRequest
	 */
	public function setSubTags(array $tags): ISearchRequest;


	/**
	 * Limit the search to a specific field of the mapping, using a full string.
	 *
	 * @since 15.0.0
	 *
	 * @param string $field
	 *
	 * @return ISearchRequest
	 */
	public function addLimitField(string $field): ISearchRequest;

	/**
	 * Get the fields the search is limited to.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getLimitFields(): array;


	/**
	 * Limit the search to a specific field of the mapping, using a wildcard on
	 * the search string.
	 *
	 * @since 15.0.0
	 *
	 * @param string $field
	 *
	 * @return ISearchRequest
	 */
	public function addWildcardField(string $field): ISearchRequest;

	/**
	 * Get the limit to field of the mapping.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getWildcardFields(): array;


	/**
	 * Filter the results, based on a group of field, using regex
	 *
	 * @since 15.0.0
	 *
	 * @param array $filters
	 *
	 * @return ISearchRequest
	 */
	public function addRegexFilters(array $filters): ISearchRequest;

	/**
	 * Get the regex filters the search is limit to.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getRegexFilters(): array;


	/**
	 * Filter the results, based on a group of field, using wildcard
	 *
	 * @since 15.0.0
	 *
	 * @param array $filter
	 *
	 * @return ISearchRequest
	 */
	public function addWildcardFilter(array $filter): ISearchRequest;

	/**
	 * Get the wildcard filters the search is limit to.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getWildcardFilters(): array;


	/**
	 * Add an extra field to the search.
	 *
	 * @since 15.0.0
	 *
	 * @param string $field
	 *
	 * @return ISearchRequest
	 */
	public function addField(string $field): ISearchRequest;

	/**
	 * Get the list of extra field to search into.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getFields(): array;



	/**
	 * Add a MUST search on an extra field
	 *
	 * @param ISearchRequestSimpleQuery $query
	 *
	 * @return ISearchRequest
	 * @since 17.0.0
	 */
	public function addSimpleQuery(ISearchRequestSimpleQuery $query): ISearchRequest;


	/**
	 * Get the list of queries on extra field.
	 *
	 * @return ISearchRequestSimpleQuery[]
	 * @since 17.0.0
	 */
	public function getSimpleQueries(): array;
}
