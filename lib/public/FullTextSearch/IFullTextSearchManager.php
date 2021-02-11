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

namespace OCP\FullTextSearch;

use OCP\FullTextSearch\Model\IIndex;
use OCP\FullTextSearch\Model\ISearchResult;
use OCP\FullTextSearch\Service\IIndexService;
use OCP\FullTextSearch\Service\IProviderService;
use OCP\FullTextSearch\Service\ISearchService;

/**
 * Interface IFullTextSearchManager
 *
 * Should be used to manage FullTextSearch from the app that contains your
 * Content Provider/Search Platform.
 *
 * @since 15.0.0
 *
 */
interface IFullTextSearchManager {


	/**
	 * Register a IProviderService.
	 *
	 * @since 15.0.0
	 *
	 * @param IProviderService $providerService
	 */
	public function registerProviderService(IProviderService $providerService);

	/**
	 * Register a IIndexService.
	 *
	 * @since 15.0.0
	 *
	 * @param IIndexService $indexService
	 */
	public function registerIndexService(IIndexService $indexService);

	/**
	 * Register a ISearchService.
	 *
	 * @since 15.0.0
	 *
	 * @param ISearchService $searchService
	 */
	public function registerSearchService(ISearchService $searchService);

	/**
	 * returns true is Full Text Search is available (app is present and Service
	 * are registered)
	 *
	 * @since 16.0.0
	 *
	 * @return bool
	 */
	public function isAvailable(): bool;


	/**
	 * Add the Javascript API in the navigation page of an app.
	 * Needed to replace the default search.
	 *
	 * @since 15.0.0
	 */
	public function addJavascriptAPI();


	/**
	 * Check if the provider $providerId is already indexed.
	 *
	 * @since 15.0.0
	 *
	 * @param string $providerId
	 *
	 * @return bool
	 */
	public function isProviderIndexed(string $providerId): bool;


	/**
	 * Retrieve an Index from the database, based on the Id of the Provider
	 * and the Id of the Document
	 *
	 * @since 15.0.0
	 *
	 * @param string $providerId
	 * @param string $documentId
	 *
	 * @return IIndex
	 */
	public function getIndex(string $providerId, string $documentId): IIndex;


	/**
	 * Create a new Index.
	 *
	 * This method must be called when a new document is created.
	 *
	 * @since 15.0.0
	 *
	 * @param string $providerId
	 * @param string $documentId
	 * @param string $userId
	 * @param int $status
	 *
	 * @return IIndex
	 */
	public function createIndex(string $providerId, string $documentId, string $userId, int $status = 0): IIndex;


	/**
	 * Update the status of an Index. status is a bitflag, setting $reset to
	 * true will reset the status to the value defined in the parameter.
	 *
	 * @since 15.0.0
	 *
	 * @param string $providerId
	 * @param string $documentId
	 * @param int $status
	 * @param bool $reset
	 */
	public function updateIndexStatus(string $providerId, string $documentId, int $status, bool $reset = false);


	/**
	 * Update the status of an array of Index. status is a bit flag, setting $reset to
	 * true will reset the status to the value defined in the parameter.
	 *
	 * @since 15.0.0
	 *
	 * @param string $providerId
	 * @param array $documentIds
	 * @param int $status
	 * @param bool $reset
	 */
	public function updateIndexesStatus(string $providerId, array $documentIds, int $status, bool $reset = false);

	/**
	 * Update an array of Index.
	 *
	 * @since 15.0.0
	 *
	 * @param IIndex[] $indexes
	 */
	public function updateIndexes(array $indexes);

	/**
	 * Search using an array as request. If $userId is empty, will use the
	 * current session.
	 *
	 * @see ISearchService::generateSearchRequest
	 *
	 * @since 15.0.0
	 *
	 * @param array $request
	 * @param string $userId
	 * @return ISearchResult[]
	 */
	public function search(array $request, string $userId = ''): array;
}
