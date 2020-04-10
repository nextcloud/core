<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

use Doctrine\DBAL\Exception\ConstraintViolationException;
use OCP\Collaboration\Resources\ICollection;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\IResource;
use OCP\Collaboration\Resources\ResourceException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUser;

class Collection implements ICollection {

	/** @var IManager|Manager */
	protected $manager;

	/** @var IDBConnection */
	protected $connection;

	/** @var int */
	protected $id;

	/** @var string */
	protected $name;

	/** @var IUser|null */
	protected $userForAccess;

	/** @var bool|null */
	protected $access;

	/** @var IResource[] */
	protected $resources;

	public function __construct(
		IManager $manager,
		IDBConnection $connection,
		int $id,
		string $name,
		?IUser $userForAccess = null,
		?bool $access = null
	) {
		$this->manager = $manager;
		$this->connection = $connection;
		$this->id = $id;
		$this->name = $name;
		$this->userForAccess = $userForAccess;
		$this->access = $access;
		$this->resources = [];
	}

	/**
	 * @return int
	 * @since 16.0.0
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * @return string
	 * @since 16.0.0
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @param string $name
	 * @since 16.0.0
	 */
	public function setName(string $name): void {
		$query = $this->connection->getQueryBuilder();
		$query->update(Manager::TABLE_COLLECTIONS)
			->set('name', $query->createNamedParameter($name))
			->where($query->expr()->eq('id', $query->createNamedParameter($this->getId(), IQueryBuilder::PARAM_INT)));
		$query->execute();

		$this->name = $name;
	}

	/**
	 * @return IResource[]
	 * @since 16.0.0
	 */
	public function getResources(): array {
		if (empty($this->resources)) {
			$this->resources = $this->manager->getResourcesByCollectionForUser($this, $this->userForAccess);
		}

		return $this->resources;
	}

	/**
	 * Adds a resource to a collection
	 *
	 * @param IResource $resource
	 * @throws ResourceException when the resource is already part of the collection
	 * @since 16.0.0
	 */
	public function addResource(IResource $resource): void {
		array_map(function (IResource $r) use ($resource) {
			if ($this->isSameResource($r, $resource)) {
				throw new ResourceException('Already part of the collection');
			}
		}, $this->getResources());

		$this->resources[] = $resource;

		$query = $this->connection->getQueryBuilder();
		$query->insert(Manager::TABLE_RESOURCES)
			->values([
				'collection_id' => $query->createNamedParameter($this->id, IQueryBuilder::PARAM_INT),
				'resource_type' => $query->createNamedParameter($resource->getType()),
				'resource_id' => $query->createNamedParameter($resource->getId()),
			]);

		try {
			$query->execute();
		} catch (ConstraintViolationException $e) {
			throw new ResourceException('Already part of the collection');
		}

		$this->manager->invalidateAccessCacheForCollection($this);
	}

	/**
	 * Removes a resource from a collection
	 *
	 * @param IResource $resource
	 * @since 16.0.0
	 */
	public function removeResource(IResource $resource): void {
		$this->resources = array_filter($this->getResources(), function (IResource $r) use ($resource) {
			return !$this->isSameResource($r, $resource);
		});

		$query = $this->connection->getQueryBuilder();
		$query->delete(Manager::TABLE_RESOURCES)
			->where($query->expr()->eq('collection_id', $query->createNamedParameter($this->id, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('resource_type', $query->createNamedParameter($resource->getType())))
			->andWhere($query->expr()->eq('resource_id', $query->createNamedParameter($resource->getId())));
		$query->execute();

		if (empty($this->resources)) {
			$this->removeCollection();
		} else {
			$this->manager->invalidateAccessCacheForCollection($this);
		}

	}

	/**
	 * Can a user/guest access the collection
	 *
	 * @param IUser|null $user
	 * @return bool
	 * @since 16.0.0
	 */
	public function canAccess(?IUser $user): bool {
		if ($user instanceof IUser) {
			return $this->canUserAccess($user);
		}
		return $this->canGuestAccess();
	}

	protected function canUserAccess(IUser $user): bool {
		if (\is_bool($this->access) && $this->userForAccess instanceof IUser && $user->getUID() === $this->userForAccess->getUID()) {
			return $this->access;
		}

		$access = $this->manager->canAccessCollection($this, $user);
		if ($this->userForAccess instanceof IUser && $user->getUID() === $this->userForAccess->getUID()) {
			$this->access = $access;
		}
		return $access;
	}

	protected function canGuestAccess(): bool {
		if (\is_bool($this->access) && !$this->userForAccess instanceof IUser) {
			return $this->access;
		}

		$access = $this->manager->canAccessCollection($this, null);
		if (!$this->userForAccess instanceof IUser) {
			$this->access = $access;
		}
		return $access;
	}

	protected function isSameResource(IResource $resource1, IResource $resource2): bool {
		return $resource1->getType() === $resource2->getType() &&
			$resource1->getId() === $resource2->getId();
	}

	protected function removeCollection(): void {
		$query = $this->connection->getQueryBuilder();
		$query->delete(Manager::TABLE_COLLECTIONS)
			->where($query->expr()->eq('id', $query->createNamedParameter($this->id, IQueryBuilder::PARAM_INT)));
		$query->execute();

		$this->manager->invalidateAccessCacheForCollection($this);
		$this->id = 0;
	}
}
