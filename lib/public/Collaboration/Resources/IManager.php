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

namespace OCP\Collaboration\Resources;

use OCP\IUser;

/**
 * @since 16.0.0
 */
interface IManager extends IProvider {

	/**
	 * @param int $id
	 * @return ICollection
	 * @throws CollectionException when the collection could not be found
	 * @since 16.0.0
	 */
	public function getCollection(int $id): ICollection;

	/**
	 * @param int $id
	 * @param IUser|null $user
	 * @return ICollection
	 * @throws CollectionException when the collection could not be found
	 * @since 16.0.0
	 */
	public function getCollectionForUser(int $id, ?IUser $user): ICollection;

	/**
	 * @param string $name
	 * @return ICollection
	 * @since 16.0.0
	 */
	public function newCollection(string $name): ICollection;

	/**
	 * Can a user/guest access the collection
	 *
	 * @param ICollection $collection
	 * @param IUser|null $user
	 * @return bool
	 * @since 16.0.0
	 */
	public function canAccessCollection(ICollection $collection, ?IUser $user): bool;

	/**
	 * @param IUser|null $user
	 * @since 16.0.0
	 */
	public function invalidateAccessCacheForUser(?IUser $user): void;

	/**
	 * @param IResource $resource
	 * @since 16.0.0
	 */
	public function invalidateAccessCacheForResource(IResource $resource): void;

	/**
	 * @param IResource $resource
	 * @param IUser|null $user
	 * @since 16.0.0
	 */
	public function invalidateAccessCacheForResourceByUser(IResource $resource, ?IUser $user): void;

	/**
	 * @param IProvider $provider
	 * @since 16.0.0
	 */
	public function invalidateAccessCacheForProvider(IProvider $provider): void;

	/**
	 * @param IProvider $provider
	 * @param IUser|null $user
	 * @since 16.0.0
	 */
	public function invalidateAccessCacheForProviderByUser(IProvider $provider, ?IUser $user): void;

	/**
	 * @param string $type
	 * @param string $id
	 * @return IResource
	 * @since 16.0.0
	 */
	public function createResource(string $type, string $id): IResource;

	/**
	 * @param string $type
	 * @param string $id
	 * @param IUser|null $user
	 * @return IResource
	 * @throws ResourceException
	 * @since 16.0.0
	 */
	public function getResourceForUser(string $type, string $id, ?IUser $user): IResource;

	/**
	 * @param string $provider
	 * @since 16.0.0
	 * @deprecated 18.0.0 Use IProviderManager::registerResourceProvider instead
	 */
	public function registerResourceProvider(string $provider): void;
}
