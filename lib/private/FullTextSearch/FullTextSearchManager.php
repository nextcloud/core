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

namespace OC\FullTextSearch;

use OCP\FullTextSearch\Exceptions\FullTextSearchAppNotAvailableException;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\FullTextSearch\Model\IIndex;
use OCP\FullTextSearch\Model\ISearchResult;
use OCP\FullTextSearch\Service\IIndexService;
use OCP\FullTextSearch\Service\IProviderService;
use OCP\FullTextSearch\Service\ISearchService;

/**
 * Class FullTextSearchManager
 *
 * @package OC\FullTextSearch
 */
class FullTextSearchManager implements IFullTextSearchManager {


	/** @var IProviderService */
	private $providerService;

	/** @var IIndexService */
	private $indexService;

	/** @var ISearchService */
	private $searchService;


	/**
	 * @since 15.0.0
	 *
	 * @param IProviderService $providerService
	 */
	public function registerProviderService(IProviderService $providerService) {
		$this->providerService = $providerService;
	}

	/**
	 * @since 15.0.0
	 *
	 * @param IIndexService $indexService
	 */
	public function registerIndexService(IIndexService $indexService) {
		$this->indexService = $indexService;
	}

	/**
	 * @since 15.0.0
	 *
	 * @param ISearchService $searchService
	 */
	public function registerSearchService(ISearchService $searchService) {
		$this->searchService = $searchService;
	}

	/**
	 * @since 16.0.0
	 *
	 * @return bool
	 */
	public function isAvailable(): bool {
		if ($this->indexService === null ||
			$this->providerService === null ||
			$this->searchService === null) {
			return false;
		}

		return true;
	}


	/**
	 * @return IProviderService
	 * @throws FullTextSearchAppNotAvailableException
	 */
	private function getProviderService(): IProviderService {
		if ($this->providerService === null) {
			throw new FullTextSearchAppNotAvailableException('No IProviderService registered');
		}

		return $this->providerService;
	}


	/**
	 * @return IIndexService
	 * @throws FullTextSearchAppNotAvailableException
	 */
	private function getIndexService(): IIndexService {
		if ($this->indexService === null) {
			throw new FullTextSearchAppNotAvailableException('No IIndexService registered');
		}

		return $this->indexService;
	}


	/**
	 * @return ISearchService
	 * @throws FullTextSearchAppNotAvailableException
	 */
	private function getSearchService(): ISearchService {
		if ($this->searchService === null) {
			throw new FullTextSearchAppNotAvailableException('No ISearchService registered');
		}

		return $this->searchService;
	}


	/**
	 * @throws FullTextSearchAppNotAvailableException
	 */
	public function addJavascriptAPI() {
		$this->getProviderService()->addJavascriptAPI();
	}


	/**
	 * @param string $providerId
	 *
	 * @return bool
	 * @throws FullTextSearchAppNotAvailableException
	 */
	public function isProviderIndexed(string $providerId): bool {
		return $this->getProviderService()->isProviderIndexed($providerId);
	}


	/**
	 * @param string $providerId
	 * @param string $documentId
	 * @return IIndex
	 * @throws FullTextSearchAppNotAvailableException
	 */
	public function getIndex(string $providerId, string $documentId): IIndex {
		return $this->getIndexService()->getIndex($providerId, $documentId);
	}

	/**
	 * @param string $providerId
	 * @param string $documentId
	 * @param string $userId
	 * @param int $status
	 *
	 * @see IIndex for available value for $status.
	 *
	 * @return IIndex
	 * @throws FullTextSearchAppNotAvailableException
	 */
	public function createIndex(string $providerId, string $documentId, string $userId, int $status = 0): IIndex {
		return $this->getIndexService()->createIndex($providerId, $documentId, $userId, $status);
	}


	/**
	 * @param string $providerId
	 * @param string $documentId
	 * @param int $status
	 * @param bool $reset
	 *
	 * @see IIndex for available value for $status.
	 *
	 * @throws FullTextSearchAppNotAvailableException
	 */
	public function updateIndexStatus(string $providerId, string $documentId, int $status, bool $reset = false) {
		$this->getIndexService()->updateIndexStatus($providerId, $documentId, $status, $reset);
	}

	/**
	 * @param string $providerId
	 * @param array $documentIds
	 * @param int $status
	 * @param bool $reset
	 *
	 * @see IIndex for available value for $status.
	 *
	 * @throws FullTextSearchAppNotAvailableException
	 */
	public function updateIndexesStatus(string $providerId, array $documentIds, int $status, bool $reset = false) {
		$this->getIndexService()->updateIndexesStatus($providerId, $documentIds, $status, $reset);
	}


	/**
	 * @param IIndex[] $indexes
	 *
	 * @throws FullTextSearchAppNotAvailableException
	 */
	public function updateIndexes(array $indexes) {
		$this->getIndexService()->updateIndexes($indexes);
	}


	/**
	 * @param array $request
	 * @param string $userId
	 *
	 * @return ISearchResult[]
	 * @throws FullTextSearchAppNotAvailableException
	 */
	public function search(array $request, string $userId = ''): array {
		$searchRequest = $this->getSearchService()->generateSearchRequest($request);

		return $this->getSearchService()->search($userId, $searchRequest);
	}


}
