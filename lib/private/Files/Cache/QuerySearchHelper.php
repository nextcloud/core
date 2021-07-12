<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tobias Kaminsky <tobias@kaminsky.me>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OC\Files\Cache;

use OC\Files\Search\SearchBinaryOperator;
use OC\SystemConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchQuery;
use OCP\IDBConnection;
use OCP\ILogger;

class QuerySearchHelper {

	/** @var IMimeTypeLoader */
	private $mimetypeLoader;
	/** @var IDBConnection */
	private $connection;
	/** @var SystemConfig */
	private $systemConfig;
	/** @var ILogger */
	private $logger;
	/** @var SearchBuilder */
	private $searchBuilder;

	public function __construct(
		IMimeTypeLoader $mimetypeLoader,
		IDBConnection $connection,
		SystemConfig $systemConfig,
		ILogger $logger,
		SearchBuilder $searchBuilder
	) {
		$this->mimetypeLoader = $mimetypeLoader;
		$this->connection = $connection;
		$this->systemConfig = $systemConfig;
		$this->logger = $logger;
		$this->searchBuilder = $searchBuilder;
	}

	protected function getQueryBuilder() {
		return new CacheQueryBuilder(
			$this->connection,
			$this->systemConfig,
			$this->logger
		);
	}

	/**
	 * Perform a file system search in multiple caches
	 *
	 * the results will be grouped by the same array keys as the $caches argument to allow
	 * post-processing based on which cache the result came from
	 *
	 * @template T of array-key
	 * @param ISearchQuery $searchQuery
	 * @param array<T, ICache> $caches
	 * @return array<T, ICacheEntry[]>
	 */
	public function searchInCaches(ISearchQuery $searchQuery, array $caches): array {
		// search in multiple caches at once by creating one query in the following format
		// SELECT ... FROM oc_filecache WHERE
		//     <filter expressions from the search query>
		// AND (
		//     <filter expression for storage1> OR
		//     <filter expression for storage2> OR
		//     ...
		// );
		//
		// This gives us all the files matching the search query from all caches
		//
		// while the resulting rows don't have a way to tell what storage they came from (multiple storages/caches can share storage_id)
		// we can just ask every cache if the row belongs to them and give them the cache to do any post processing on the result.

		$builder = $this->getQueryBuilder();

		$query = $builder->selectFileCache('file');

		if ($this->searchBuilder->shouldJoinTags($searchQuery->getSearchOperation())) {
			$user = $searchQuery->getUser();
			if ($user === null) {
				throw new \InvalidArgumentException("Searching by tag requires the user to be set in the query");
			}
			$query
				->leftJoin('file', 'vcategory_to_object', 'tagmap', $builder->expr()->eq('file.fileid', 'tagmap.objid'))
				->leftJoin('tagmap', 'vcategory', 'tag', $builder->expr()->andX(
					$builder->expr()->eq('tagmap.type', 'tag.type'),
					$builder->expr()->eq('tagmap.categoryid', 'tag.id'),
					$builder->expr()->eq('tag.type', $builder->createNamedParameter('files')),
					$builder->expr()->eq('tag.uid', $builder->createNamedParameter($user->getUID()))
				))
				->leftJoin('file', 'systemtag_object_mapping', 'systemtagmap', $builder->expr()->andX(
					$builder->expr()->eq('file.fileid', $builder->expr()->castColumn('systemtagmap.objectid', IQueryBuilder::PARAM_INT)),
					$builder->expr()->eq('systemtagmap.objecttype', $builder->createNamedParameter('files'))
				))
				->leftJoin('systemtagmap', 'systemtag', 'systemtag', $builder->expr()->andX(
					$builder->expr()->eq('systemtag.id', 'systemtagmap.systemtagid'),
					$builder->expr()->eq('systemtag.visibility', $builder->createNamedParameter(true))
				));
		}

		$searchExpr = $this->searchBuilder->searchOperatorToDBExpr($builder, $searchQuery->getSearchOperation());
		if ($searchExpr) {
			$query->andWhere($searchExpr);
		}

		$storageFilters = array_values(array_map(function (ICache $cache) {
			return $cache->getQueryFilterForStorage();
		}, $caches));
		$query->andWhere($this->searchBuilder->searchOperatorToDBExpr($builder, new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_OR, $storageFilters)));

		$this->searchBuilder->addSearchOrdersToQuery($query, $searchQuery->getOrder());

		if ($searchQuery->getLimit()) {
			$query->setMaxResults($searchQuery->getLimit());
		}
		if ($searchQuery->getOffset()) {
			$query->setFirstResult($searchQuery->getOffset());
		}

		$result = $query->execute();
		$files = $result->fetchAll();

		$rawEntries = array_map(function (array $data) {
			return Cache::cacheEntryFromData($data, $this->mimetypeLoader);
		}, $files);

		$result->closeCursor();

		// loop trough all caches for each result to see if the result matches that storage
		// results are grouped by the same array keys as the caches argument to allow the caller to distringuish the source of the results
		$results = array_fill_keys(array_keys($caches), []);
		foreach ($rawEntries as $rawEntry) {
			foreach ($caches as $cacheKey => $cache) {
				$entry = $cache->getCacheEntryFromSearchResult($rawEntry);
				if ($entry) {
					$results[$cacheKey][] = $entry;
				}
			}
		}
		return $results;
	}
}
