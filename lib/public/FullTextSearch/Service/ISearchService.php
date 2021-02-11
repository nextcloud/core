<?php

declare(strict_types=1);

/**
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
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

namespace OCP\FullTextSearch\Service;

use OCP\FullTextSearch\Model\ISearchRequest;
use OCP\FullTextSearch\Model\ISearchResult;

/**
 * Interface ISearchService
 *
 * @since 15.0.0
 *
 */
interface ISearchService {


	/**
	 * generate a search request, based on an array:
	 *
	 * $request =
	 *   [
	 *        'providers' =>    (string/array) 'all'
	 *        'author' =>       (string) owner of the document.
	 *        'search' =>       (string) search string,
	 *        'size' =>         (int) number of items to be return
	 *        'page' =>         (int) page
	 *        'parts' =>        (array) parts of document to search within,
	 *        'options' =       (array) search options,
	 *        'tags'     =>     (array) tags,
	 *        'metatags' =>     (array) metatags,
	 *        'subtags'  =>     (array) subtags
	 *   ]
	 *
	 * 'providers' can be an array of providerIds
	 *
	 * @since 15.0.0
	 *
	 * @param array $request
	 *
	 * @return ISearchRequest
	 */
	public function generateSearchRequest(array $request): ISearchRequest;


	/**
	 * Search documents
	 *
	 * @since 15.0.0
	 *
	 * @param string $userId
	 * @param ISearchRequest $searchRequest
	 *
	 * @return ISearchResult[]
	 */
	public function search(string $userId, ISearchRequest $searchRequest): array;
}
