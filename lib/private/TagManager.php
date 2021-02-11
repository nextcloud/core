<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Reiter <ockham@raz.or.at>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Tanghus <thomas@tanghus.net>
 * @author Vincent Petry <vincent@nextcloud.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

/**
 * Factory class creating instances of \OCP\ITags
 *
 * A tag can be e.g. 'Family', 'Work', 'Chore', 'Special Occation' or
 * anything else that is either parsed from a vobject or that the user chooses
 * to add.
 * Tag names are not case-sensitive, but will be saved with the case they
 * are entered in. If a user already has a tag 'family' for a type, and
 * tries to add a tag named 'Family' it will be silently ignored.
 */

namespace OC;

use OC\Tagging\TagMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\ITagManager;
use OCP\ITags;
use OCP\IUserSession;

class TagManager implements ITagManager {

	/** @var TagMapper */
	private $mapper;

	/** @var IUserSession */
	private $userSession;

	/** @var IDBConnection */
	private $connection;

	public function __construct(TagMapper $mapper, IUserSession $userSession, IDBConnection $connection) {
		$this->mapper = $mapper;
		$this->userSession = $userSession;
		$this->connection = $connection;
	}

	/**
	 * Create a new \OCP\ITags instance and load tags from db.
	 *
	 * @see \OCP\ITags
	 * @param string $type The type identifier e.g. 'contact' or 'event'.
	 * @param array $defaultTags An array of default tags to be used if none are stored.
	 * @param boolean $includeShared Whether to include tags for items shared with this user by others.
	 * @param string $userId user for which to retrieve the tags, defaults to the currently
	 * logged in user
	 * @return \OCP\ITags
	 *
	 * since 20.0.0 $includeShared isn't used anymore
	 */
	public function load($type, $defaultTags = [], $includeShared = false, $userId = null) {
		if (is_null($userId)) {
			$user = $this->userSession->getUser();
			if ($user === null) {
				// nothing we can do without a user
				return null;
			}
			$userId = $this->userSession->getUser()->getUId();
		}
		return new Tags($this->mapper, $userId, $type, $defaultTags);
	}

	/**
	 * Get all users who favorited an object
	 *
	 * @param string $objectType
	 * @param int $objectId
	 * @return array
	 */
	public function getUsersFavoritingObject(string $objectType, int $objectId): array {
		$query = $this->connection->getQueryBuilder();
		$query->select('uid')
			->from('vcategory_to_object', 'o')
			->innerJoin('o', 'vcategory', 'c', $query->expr()->eq('o.categoryid', 'c.id'))
			->where($query->expr()->eq('objid', $query->createNamedParameter($objectId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('c.type', $query->createNamedParameter($objectType)))
			->andWhere($query->expr()->eq('c.category', $query->createNamedParameter(ITags::TAG_FAVORITE)));

		$result = $query->execute();
		$users = $result->fetchAll(\PDO::FETCH_COLUMN);
		$result->closeCursor();

		return $users;
	}
}
