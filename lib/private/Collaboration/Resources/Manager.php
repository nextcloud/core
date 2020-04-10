<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OC\Collaboration\Resources;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCP\Collaboration\Resources\CollectionException;
use OCP\Collaboration\Resources\ICollection;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\IProvider;
use OCP\Collaboration\Resources\IProviderManager;
use OCP\Collaboration\Resources\IResource;
use OCP\Collaboration\Resources\ResourceException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\IUser;

class Manager implements IManager {

	public const TABLE_COLLECTIONS = 'collres_collections';
	public const TABLE_RESOURCES = 'collres_resources';
	public const TABLE_ACCESS_CACHE = 'collres_accesscache';

	/** @var IDBConnection */
	protected $connection;
	/** @var IProviderManager */
	protected $providerManager;
	/** @var ILogger */
	protected $logger;

	/** @var string[] */
	protected $providers = [];


	public function __construct(IDBConnection $connection, IProviderManager $providerManager, ILogger $logger) {
		$this->connection = $connection;
		$this->providerManager = $providerManager;
		$this->logger = $logger;
	}

	/**
	 * @param int $id
	 * @return ICollection
	 * @throws CollectionException when the collection could not be found
	 * @since 16.0.0
	 */
	public function getCollection(int $id): ICollection {
		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from(self::TABLE_COLLECTIONS)
			->where($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if (!$row) {
			throw new CollectionException('Collection not found');
		}

		return new Collection($this, $this->connection, (int) $row['id'], (string) $row['name']);
	}

	/**
	 * @param int $id
	 * @param IUser|null $user
	 * @return ICollection
	 * @throws CollectionException when the collection could not be found
	 * @since 16.0.0
	 */
	public function getCollectionForUser(int $id, ?IUser $user): ICollection {
		$query = $this->connection->getQueryBuilder();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		$query->select('*')
			->from(self::TABLE_COLLECTIONS, 'c')
			->leftJoin(
				'c', self::TABLE_ACCESS_CACHE, 'a',
				$query->expr()->andX(
					$query->expr()->eq('c.id', 'a.collection_id'),
					$query->expr()->eq('a.user_id', $query->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				)
			)
			->where($query->expr()->eq('c.id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if (!$row) {
			throw new CollectionException('Collection not found');
		}

		$access = $row['access'] === null ? null : (bool) $row['access'];
		if ($user instanceof IUser) {
			return new Collection($this, $this->connection, (int) $row['id'], (string) $row['name'], $user, $access);
		}

		return new Collection($this, $this->connection, (int) $row['id'], (string) $row['name'], $user, $access);
	}

	/**
	 * @param IUser $user
	 * @param string $filter
	 * @param int $limit
	 * @param int $start
	 * @return ICollection[]
	 * @since 16.0.0
	 */
	public function searchCollections(IUser $user, string $filter, int $limit = 50, int $start = 0): array {
		$query = $this->connection->getQueryBuilder();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		$query->select('c.*', 'a.access')
			->from(self::TABLE_COLLECTIONS, 'c')
			->leftJoin(
				'c', self::TABLE_ACCESS_CACHE, 'a',
				$query->expr()->andX(
					$query->expr()->eq('c.id', 'a.collection_id'),
					$query->expr()->eq('a.user_id', $query->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				)
			)
			->where($query->expr()->eq('a.access', $query->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->orderBy('c.id')
			->setMaxResults($limit)
			->setFirstResult($start);

		if ($filter !== '') {
			$query->where($query->expr()->iLike('c.name', $query->createNamedParameter('%' . $this->connection->escapeLikeParameter($filter) . '%')));
		}

		$result = $query->execute();
		$collections = [];

		$foundResults = 0;
		while ($row = $result->fetch()) {
			$foundResults++;
			$access = $row['access'] === null ? null : (bool) $row['access'];
			$collection = new Collection($this, $this->connection, (int)$row['id'], (string)$row['name'], $user, $access);
			if ($collection->canAccess($user)) {
				$collections[] = $collection;
			}
		}
		$result->closeCursor();

		if (empty($collections) && $foundResults === $limit) {
			return $this->searchCollections($user, $filter, $limit, $start + $limit);
		}

		return $collections;
	}

	/**
	 * @param string $name
	 * @return ICollection
	 * @since 16.0.0
	 */
	public function newCollection(string $name): ICollection {
		$query = $this->connection->getQueryBuilder();
		$query->insert(self::TABLE_COLLECTIONS)
			->values([
				'name' => $query->createNamedParameter($name),
			]);
		$query->execute();

		return new Collection($this, $this->connection, $query->getLastInsertId(), $name);
	}

	/**
	 * @param string $type
	 * @param string $id
	 * @return IResource
	 * @since 16.0.0
	 */
	public function createResource(string $type, string $id): IResource {
		return new Resource($this, $this->connection, $type, $id);
	}

	/**
	 * @param string $type
	 * @param string $id
	 * @param IUser|null $user
	 * @return IResource
	 * @throws ResourceException
	 * @since 16.0.0
	 */
	public function getResourceForUser(string $type, string $id, ?IUser $user): IResource {
		$query = $this->connection->getQueryBuilder();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		$query->select('r.*', 'a.access')
			->from(self::TABLE_RESOURCES, 'r')
			->leftJoin(
				'r', self::TABLE_ACCESS_CACHE, 'a',
				$query->expr()->andX(
					$query->expr()->eq('r.resource_id', 'a.resource_id'),
					$query->expr()->eq('r.resource_type', 'a.resource_type'),
					$query->expr()->eq('a.user_id', $query->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				)
			)
			->where($query->expr()->eq('r.resource_type', $query->createNamedParameter($type, IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('r.resource_id', $query->createNamedParameter($id, IQueryBuilder::PARAM_STR)));
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if (!$row) {
			throw new ResourceException('Resource not found');
		}

		$access = $row['access'] === null ? null : (bool) $row['access'];
		if ($user instanceof IUser) {
			return new Resource($this, $this->connection, $type, $id, $user, $access);
		}

		return new Resource($this, $this->connection, $type, $id, null, $access);
	}

	/**
	 * @param ICollection $collection
	 * @param IUser|null $user
	 * @return IResource[]
	 * @since 16.0.0
	 */
	public function getResourcesByCollectionForUser(ICollection $collection, ?IUser $user): array {
		$query = $this->connection->getQueryBuilder();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		$query->select('r.*', 'a.access')
			->from(self::TABLE_RESOURCES, 'r')
			->leftJoin(
				'r', self::TABLE_ACCESS_CACHE, 'a',
				$query->expr()->andX(
					$query->expr()->eq('r.resource_id', 'a.resource_id'),
					$query->expr()->eq('r.resource_type', 'a.resource_type'),
					$query->expr()->eq('a.user_id', $query->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				)
			)
			->where($query->expr()->eq('r.collection_id', $query->createNamedParameter($collection->getId(), IQueryBuilder::PARAM_INT)));

		$resources = [];
		$result = $query->execute();
		while ($row = $result->fetch()) {
			$access = $row['access'] === null ? null : (bool) $row['access'];
			$resources[] = new Resource($this, $this->connection, $row['resource_type'], $row['resource_id'], $user, $access);
		}
		$result->closeCursor();

		return $resources;
	}

	/**
	 * Get the rich object data of a resource
	 *
	 * @param IResource $resource
	 * @return array
	 * @since 16.0.0
	 */
	public function getResourceRichObject(IResource $resource): array {
		foreach ($this->providerManager->getResourceProviders() as $provider) {
			if ($provider->getType() === $resource->getType()) {
				try {
					return $provider->getResourceRichObject($resource);
				} catch (ResourceException $e) {
				}
			}
		}

		return [];
	}

	/**
	 * Can a user/guest access the collection
	 *
	 * @param IResource $resource
	 * @param IUser|null $user
	 * @return bool
	 * @since 16.0.0
	 */
	public function canAccessResource(IResource $resource, ?IUser $user): bool {
		$access = $this->checkAccessCacheForUserByResource($resource, $user);
		if (\is_bool($access)) {
			return $access;
		}

		$access = false;
		foreach ($this->providerManager->getResourceProviders() as $provider) {
			if ($provider->getType() === $resource->getType()) {
				try {
					if ($provider->canAccessResource($resource, $user)) {
						$access = true;
						break;
					}
				} catch (ResourceException $e) {
				}
			}
		}

		$this->cacheAccessForResource($resource, $user, $access);
		return $access;
	}

	/**
	 * Can a user/guest access the collection
	 *
	 * @param ICollection $collection
	 * @param IUser|null $user
	 * @return bool
	 * @since 16.0.0
	 */
	public function canAccessCollection(ICollection $collection, ?IUser $user): bool {
		$access = $this->checkAccessCacheForUserByCollection($collection, $user);
		if (\is_bool($access)) {
			return $access;
		}

		$access = null;
		// Access is granted when a user can access all resources
		foreach ($collection->getResources() as $resource) {
			if (!$resource->canAccess($user)) {
				$access = false;
				break;
			}

			$access = true;
		}

		$this->cacheAccessForCollection($collection, $user, $access);
		return $access;
	}

	protected function checkAccessCacheForUserByResource(IResource $resource, ?IUser $user): ?bool {
		$query = $this->connection->getQueryBuilder();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		$query->select('access')
			->from(self::TABLE_ACCESS_CACHE)
			->where($query->expr()->eq('resource_id', $query->createNamedParameter($resource->getId(), IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('resource_type', $query->createNamedParameter($resource->getType(), IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);

		$hasAccess = null;
		$result = $query->execute();
		if ($row = $result->fetch()) {
			$hasAccess = (bool) $row['access'];
		}
		$result->closeCursor();

		return $hasAccess;
	}

	protected function checkAccessCacheForUserByCollection(ICollection $collection, ?IUser $user): ?bool {
		$query = $this->connection->getQueryBuilder();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		$query->select('access')
			->from(self::TABLE_ACCESS_CACHE)
			->where($query->expr()->eq('collection_id', $query->createNamedParameter($collection->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);

		$hasAccess = null;
		$result = $query->execute();
		if ($row = $result->fetch()) {
			$hasAccess = (bool) $row['access'];
		}
		$result->closeCursor();

		return $hasAccess;
	}

	public function cacheAccessForResource(IResource $resource, ?IUser $user, bool $access): void {
		$query = $this->connection->getQueryBuilder();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		$query->insert(self::TABLE_ACCESS_CACHE)
			->values([
				'user_id' => $query->createNamedParameter($userId),
				'resource_id' => $query->createNamedParameter($resource->getId()),
				'resource_type' => $query->createNamedParameter($resource->getType()),
				'access' => $query->createNamedParameter($access, IQueryBuilder::PARAM_BOOL),
			]);
		try {
			$query->execute();
		} catch (UniqueConstraintViolationException $e) {
		}
	}

	public function cacheAccessForCollection(ICollection $collection, ?IUser $user, bool $access): void {
		$query = $this->connection->getQueryBuilder();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		$query->insert(self::TABLE_ACCESS_CACHE)
			->values([
				'user_id' => $query->createNamedParameter($userId),
				'collection_id' => $query->createNamedParameter($collection->getId()),
				'access' => $query->createNamedParameter($access, IQueryBuilder::PARAM_BOOL),
			]);
		try {
			$query->execute();
		} catch (UniqueConstraintViolationException $e) {
		}
	}

	public function invalidateAccessCacheForUser(?IUser $user): void {
		$query = $this->connection->getQueryBuilder();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		$query->delete(self::TABLE_ACCESS_CACHE)
			->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)));
		$query->execute();
	}

	public function invalidateAccessCacheForResource(IResource $resource): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete(self::TABLE_ACCESS_CACHE)
			->where($query->expr()->eq('resource_id', $query->createNamedParameter($resource->getId())))
			->andWhere($query->expr()->eq('resource_type', $query->createNamedParameter($resource->getType(), IQueryBuilder::PARAM_STR)));
		$query->execute();

		foreach ($resource->getCollections() as $collection) {
			$this->invalidateAccessCacheForCollection($collection);
		}
	}

	public function invalidateAccessCacheForAllCollections(): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete(self::TABLE_ACCESS_CACHE)
			->where($query->expr()->neq('collection_id', $query->createNamedParameter(0)));
		$query->execute();
	}

	public function invalidateAccessCacheForCollection(ICollection $collection): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete(self::TABLE_ACCESS_CACHE)
			->where($query->expr()->eq('collection_id', $query->createNamedParameter($collection->getId())));
		$query->execute();
	}

	public function invalidateAccessCacheForProvider(IProvider $provider): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete(self::TABLE_ACCESS_CACHE)
			->where($query->expr()->eq('resource_type', $query->createNamedParameter($provider->getType(), IQueryBuilder::PARAM_STR)));
		$query->execute();
	}

	public function invalidateAccessCacheForResourceByUser(IResource $resource, ?IUser $user): void {
		$query = $this->connection->getQueryBuilder();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		$query->delete(self::TABLE_ACCESS_CACHE)
			->where($query->expr()->eq('resource_id', $query->createNamedParameter($resource->getId())))
			->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($userId)));
		$query->execute();

		foreach ($resource->getCollections() as $collection) {
			$this->invalidateAccessCacheForCollectionByUser($collection, $user);
		}
	}

	protected function invalidateAccessCacheForCollectionByUser(ICollection $collection, ?IUser $user): void {
		$query = $this->connection->getQueryBuilder();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		$query->delete(self::TABLE_ACCESS_CACHE)
			->where($query->expr()->eq('collection_id', $query->createNamedParameter($collection->getId())))
			->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($userId)));
		$query->execute();
	}

	public function invalidateAccessCacheForProviderByUser(IProvider $provider, ?IUser $user): void {
		$query = $this->connection->getQueryBuilder();
		$userId = $user instanceof IUser ? $user->getUID() : '';

		$query->delete(self::TABLE_ACCESS_CACHE)
			->where($query->expr()->eq('resource_type', $query->createNamedParameter($provider->getType(), IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('user_id', $query->createNamedParameter($userId)));
		$query->execute();
	}

	/**
	 * @param string $provider
	 */
	public function registerResourceProvider(string $provider): void {
		$this->logger->debug('\OC\Collaboration\Resources\Manager::registerResourceProvider is deprecated', ['provider' => $provider]);
		$this->providerManager->registerResourceProvider($provider);
	}

	/**
	 * Get the resource type of the provider
	 *
	 * @return string
	 * @since 16.0.0
	 */
	public function getType(): string {
		return '';
	}
}
