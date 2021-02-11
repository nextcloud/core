<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bernhard Reiter <ockham@raz.or.at>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author derkostka <sebastian.kostka@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * Class for easily tagging objects by their id
 *
 * A tag can be e.g. 'Family', 'Work', 'Chore', 'Special Occation' or
 * anything else that is either parsed from a vobject or that the user chooses
 * to add.
 * Tag names are not case-sensitive, but will be saved with the case they
 * are entered in. If a user already has a tag 'family' for a type, and
 * tries to add a tag named 'Family' it will be silently ignored.
 */

namespace OC;

use OC\Tagging\Tag;
use OC\Tagging\TagMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\ILogger;
use OCP\ITags;

class Tags implements ITags {

	/**
	 * Tags
	 *
	 * @var array
	 */
	private $tags = [];

	/**
	 * Used for storing objectid/categoryname pairs while rescanning.
	 *
	 * @var array
	 */
	private static $relations = [];

	/**
	 * Type
	 *
	 * @var string
	 */
	private $type;

	/**
	 * User
	 *
	 * @var string
	 */
	private $user;

	/**
	 * Are we including tags for shared items?
	 *
	 * @var bool
	 */
	private $includeShared = false;

	/**
	 * The current user, plus any owners of the items shared with the current
	 * user, if $this->includeShared === true.
	 *
	 * @var array
	 */
	private $owners = [];

	/**
	 * The Mapper we're using to communicate our Tag objects to the database.
	 *
	 * @var TagMapper
	 */
	private $mapper;

	/**
	 * The sharing backend for objects of $this->type. Required if
	 * $this->includeShared === true to determine ownership of items.
	 *
	 * @var \OCP\Share_Backend
	 */
	private $backend;

	public const TAG_TABLE = '*PREFIX*vcategory';
	public const RELATION_TABLE = '*PREFIX*vcategory_to_object';

	/**
	 * Constructor.
	 *
	 * @param TagMapper $mapper Instance of the TagMapper abstraction layer.
	 * @param string $user The user whose data the object will operate on.
	 * @param string $type The type of items for which tags will be loaded.
	 * @param array $defaultTags Tags that should be created at construction.
	 *
	 * since 20.0.0 $includeShared isn't used anymore
	 */
	public function __construct(TagMapper $mapper, $user, $type, $defaultTags = []) {
		$this->mapper = $mapper;
		$this->user = $user;
		$this->type = $type;
		$this->owners = [$this->user];
		$this->tags = $this->mapper->loadTags($this->owners, $this->type);

		if (count($defaultTags) > 0 && count($this->tags) === 0) {
			$this->addMultiple($defaultTags, true);
		}
	}

	/**
	 * Check if any tags are saved for this type and user.
	 *
	 * @return boolean
	 */
	public function isEmpty() {
		return count($this->tags) === 0;
	}

	/**
	 * Returns an array mapping a given tag's properties to its values:
	 * ['id' => 0, 'name' = 'Tag', 'owner' = 'User', 'type' => 'tagtype']
	 *
	 * @param string $id The ID of the tag that is going to be mapped
	 * @return array|false
	 */
	public function getTag($id) {
		$key = $this->getTagById($id);
		if ($key !== false) {
			return $this->tagMap($this->tags[$key]);
		}
		return false;
	}

	/**
	 * Get the tags for a specific user.
	 *
	 * This returns an array with maps containing each tag's properties:
	 * [
	 * 	['id' => 0, 'name' = 'First tag', 'owner' = 'User', 'type' => 'tagtype'],
	 * 	['id' => 1, 'name' = 'Shared tag', 'owner' = 'Other user', 'type' => 'tagtype'],
	 * ]
	 *
	 * @return array
	 */
	public function getTags() {
		if (!count($this->tags)) {
			return [];
		}

		usort($this->tags, function ($a, $b) {
			return strnatcasecmp($a->getName(), $b->getName());
		});
		$tagMap = [];

		foreach ($this->tags as $tag) {
			if ($tag->getName() !== ITags::TAG_FAVORITE) {
				$tagMap[] = $this->tagMap($tag);
			}
		}
		return $tagMap;
	}

	/**
	 * Return only the tags owned by the given user, omitting any tags shared
	 * by other users.
	 *
	 * @param string $user The user whose tags are to be checked.
	 * @return array An array of Tag objects.
	 */
	public function getTagsForUser($user) {
		return array_filter($this->tags,
			function ($tag) use ($user) {
				return $tag->getOwner() === $user;
			}
		);
	}

	/**
	 * Get the list of tags for the given ids.
	 *
	 * @param array $objIds array of object ids
	 * @return array|boolean of tags id as key to array of tag names
	 * or false if an error occurred
	 */
	public function getTagsForObjects(array $objIds) {
		$entries = [];

		try {
			$conn = \OC::$server->getDatabaseConnection();
			$chunks = array_chunk($objIds, 900, false);
			foreach ($chunks as $chunk) {
				$result = $conn->executeQuery(
					'SELECT `category`, `categoryid`, `objid` ' .
					'FROM `' . self::RELATION_TABLE . '` r, `' . self::TAG_TABLE . '` ' .
					'WHERE `categoryid` = `id` AND `uid` = ? AND r.`type` = ? AND `objid` IN (?)',
					[$this->user, $this->type, $chunk],
					[null, null, IQueryBuilder::PARAM_INT_ARRAY]
				);
				while ($row = $result->fetch()) {
					$objId = (int)$row['objid'];
					if (!isset($entries[$objId])) {
						$entries[$objId] = [];
					}
					$entries[$objId][] = $row['category'];
				}
			}
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => __METHOD__,
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
			return false;
		}

		return $entries;
	}

	/**
	 * Get the a list if items tagged with $tag.
	 *
	 * Throws an exception if the tag could not be found.
	 *
	 * @param string $tag Tag id or name.
	 * @return array|false An array of object ids or false on error.
	 * @throws \Exception
	 */
	public function getIdsForTag($tag) {
		$result = null;
		$tagId = false;
		if (is_numeric($tag)) {
			$tagId = $tag;
		} elseif (is_string($tag)) {
			$tag = trim($tag);
			if ($tag === '') {
				\OCP\Util::writeLog('core', __METHOD__.', Cannot use empty tag names', ILogger::DEBUG);
				return false;
			}
			$tagId = $this->getTagId($tag);
		}

		if ($tagId === false) {
			$l10n = \OC::$server->getL10N('core');
			throw new \Exception(
				$l10n->t('Could not find category "%s"', [$tag])
			);
		}

		$ids = [];
		$sql = 'SELECT `objid` FROM `' . self::RELATION_TABLE
			. '` WHERE `categoryid` = ?';

		try {
			$stmt = \OC_DB::prepare($sql);
			$result = $stmt->execute([$tagId]);
			if ($result === null) {
				$stmt->closeCursor();
				\OCP\Util::writeLog('core', __METHOD__. 'DB error: ' . \OC::$server->getDatabaseConnection()->getError(), ILogger::ERROR);
				return false;
			}
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => __METHOD__,
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
			return false;
		}

		if (!is_null($result)) {
			while ($row = $result->fetchRow()) {
				$ids[] = (int)$row['objid'];
			}
			$result->closeCursor();
		}

		return $ids;
	}

	/**
	 * Checks whether a tag is saved for the given user,
	 * disregarding the ones shared with him or her.
	 *
	 * @param string $name The tag name to check for.
	 * @param string $user The user whose tags are to be checked.
	 * @return bool
	 */
	public function userHasTag($name, $user) {
		$key = $this->array_searchi($name, $this->getTagsForUser($user));
		return ($key !== false) ? $this->tags[$key]->getId() : false;
	}

	/**
	 * Checks whether a tag is saved for or shared with the current user.
	 *
	 * @param string $name The tag name to check for.
	 * @return bool
	 */
	public function hasTag($name) {
		return $this->getTagId($name) !== false;
	}

	/**
	 * Add a new tag.
	 *
	 * @param string $name A string with a name of the tag
	 * @return false|int the id of the added tag or false on error.
	 */
	public function add($name) {
		$name = trim($name);

		if ($name === '') {
			\OCP\Util::writeLog('core', __METHOD__.', Cannot add an empty tag', ILogger::DEBUG);
			return false;
		}
		if ($this->userHasTag($name, $this->user)) {
			\OCP\Util::writeLog('core', __METHOD__.', name: ' . $name. ' exists already', ILogger::DEBUG);
			return false;
		}
		try {
			$tag = new Tag($this->user, $this->type, $name);
			$tag = $this->mapper->insert($tag);
			$this->tags[] = $tag;
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => __METHOD__,
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
			return false;
		}
		\OCP\Util::writeLog('core', __METHOD__.', id: ' . $tag->getId(), ILogger::DEBUG);
		return $tag->getId();
	}

	/**
	 * Rename tag.
	 *
	 * @param string|integer $from The name or ID of the existing tag
	 * @param string $to The new name of the tag.
	 * @return bool
	 */
	public function rename($from, $to) {
		$from = trim($from);
		$to = trim($to);

		if ($to === '' || $from === '') {
			\OCP\Util::writeLog('core', __METHOD__.', Cannot use empty tag names', ILogger::DEBUG);
			return false;
		}

		if (is_numeric($from)) {
			$key = $this->getTagById($from);
		} else {
			$key = $this->getTagByName($from);
		}
		if ($key === false) {
			\OCP\Util::writeLog('core', __METHOD__.', tag: ' . $from. ' does not exist', ILogger::DEBUG);
			return false;
		}
		$tag = $this->tags[$key];

		if ($this->userHasTag($to, $tag->getOwner())) {
			\OCP\Util::writeLog('core', __METHOD__.', A tag named ' . $to. ' already exists for user ' . $tag->getOwner() . '.', ILogger::DEBUG);
			return false;
		}

		try {
			$tag->setName($to);
			$this->tags[$key] = $this->mapper->update($tag);
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => __METHOD__,
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
			return false;
		}
		return true;
	}

	/**
	 * Add a list of new tags.
	 *
	 * @param string[] $names A string with a name or an array of strings containing
	 * the name(s) of the tag(s) to add.
	 * @param bool $sync When true, save the tags
	 * @param int|null $id int Optional object id to add to this|these tag(s)
	 * @return bool Returns false on error.
	 */
	public function addMultiple($names, $sync = false, $id = null) {
		if (!is_array($names)) {
			$names = [$names];
		}
		$names = array_map('trim', $names);
		array_filter($names);

		$newones = [];
		foreach ($names as $name) {
			if (!$this->hasTag($name) && $name !== '') {
				$newones[] = new Tag($this->user, $this->type, $name);
			}
			if (!is_null($id)) {
				// Insert $objectid, $categoryid  pairs if not exist.
				self::$relations[] = ['objid' => $id, 'tag' => $name];
			}
		}
		$this->tags = array_merge($this->tags, $newones);
		if ($sync === true) {
			$this->save();
		}

		return true;
	}

	/**
	 * Save the list of tags and their object relations
	 */
	protected function save() {
		if (is_array($this->tags)) {
			foreach ($this->tags as $tag) {
				try {
					if (!$this->mapper->tagExists($tag)) {
						$this->mapper->insert($tag);
					}
				} catch (\Exception $e) {
					\OC::$server->getLogger()->logException($e, [
						'message' => __METHOD__,
						'level' => ILogger::ERROR,
						'app' => 'core',
					]);
				}
			}

			// reload tags to get the proper ids.
			$this->tags = $this->mapper->loadTags($this->owners, $this->type);
			\OCP\Util::writeLog('core', __METHOD__.', tags: ' . print_r($this->tags, true),
				ILogger::DEBUG);
			// Loop through temporarily cached objectid/tagname pairs
			// and save relations.
			$tags = $this->tags;
			// For some reason this is needed or array_search(i) will return 0..?
			ksort($tags);
			$dbConnection = \OC::$server->getDatabaseConnection();
			foreach (self::$relations as $relation) {
				$tagId = $this->getTagId($relation['tag']);
				\OCP\Util::writeLog('core', __METHOD__ . 'catid, ' . $relation['tag'] . ' ' . $tagId, ILogger::DEBUG);
				if ($tagId) {
					try {
						$dbConnection->insertIfNotExist(self::RELATION_TABLE,
							[
								'objid' => $relation['objid'],
								'categoryid' => $tagId,
								'type' => $this->type,
							]);
					} catch (\Exception $e) {
						\OC::$server->getLogger()->logException($e, [
							'message' => __METHOD__,
							'level' => ILogger::ERROR,
							'app' => 'core',
						]);
					}
				}
			}
			self::$relations = []; // reset
		} else {
			\OCP\Util::writeLog('core', __METHOD__.', $this->tags is not an array! '
				. print_r($this->tags, true), ILogger::ERROR);
		}
	}

	/**
	 * Delete tags and tag/object relations for a user.
	 *
	 * For hooking up on post_deleteUser
	 *
	 * @param array $arguments
	 */
	public static function post_deleteUser($arguments) {
		// Find all objectid/tagId pairs.
		$result = null;
		try {
			$stmt = \OC_DB::prepare('SELECT `id` FROM `' . self::TAG_TABLE . '` '
				. 'WHERE `uid` = ?');
			$result = $stmt->execute([$arguments['uid']]);
			if ($result === null) {
				\OCP\Util::writeLog('core', __METHOD__. 'DB error: ' . \OC::$server->getDatabaseConnection()->getError(), ILogger::ERROR);
			}
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => __METHOD__,
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
		}

		if (!is_null($result)) {
			try {
				$stmt = \OC_DB::prepare('DELETE FROM `' . self::RELATION_TABLE . '` '
					. 'WHERE `categoryid` = ?');
				while ($row = $result->fetchRow()) {
					try {
						$stmt->execute([$row['id']]);
					} catch (\Exception $e) {
						\OC::$server->getLogger()->logException($e, [
							'message' => __METHOD__,
							'level' => ILogger::ERROR,
							'app' => 'core',
						]);
					}
				}
				$result->closeCursor();
			} catch (\Exception $e) {
				\OC::$server->getLogger()->logException($e, [
					'message' => __METHOD__,
					'level' => ILogger::ERROR,
					'app' => 'core',
				]);
			}
		}
		try {
			$stmt = \OC_DB::prepare('DELETE FROM `' . self::TAG_TABLE . '` '
				. 'WHERE `uid` = ?');
			$result = $stmt->execute([$arguments['uid']]);
			if ($result === null) {
				\OCP\Util::writeLog('core', __METHOD__. ', DB error: ' . \OC::$server->getDatabaseConnection()->getError(), ILogger::ERROR);
			}
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => __METHOD__,
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
		}
	}

	/**
	 * Delete tag/object relations from the db
	 *
	 * @param array $ids The ids of the objects
	 * @return boolean Returns false on error.
	 */
	public function purgeObjects(array $ids) {
		if (count($ids) === 0) {
			// job done ;)
			return true;
		}
		$updates = $ids;
		try {
			$query = 'DELETE FROM `' . self::RELATION_TABLE . '` ';
			$query .= 'WHERE `objid` IN (' . str_repeat('?,', count($ids) - 1) . '?) ';
			$query .= 'AND `type`= ?';
			$updates[] = $this->type;
			$stmt = \OC_DB::prepare($query);
			$result = $stmt->execute($updates);
			if ($result === null) {
				\OCP\Util::writeLog('core', __METHOD__. 'DB error: ' . \OC::$server->getDatabaseConnection()->getError(), ILogger::ERROR);
				return false;
			}
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => __METHOD__,
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
			return false;
		}
		return true;
	}

	/**
	 * Get favorites for an object type
	 *
	 * @return array|false An array of object ids.
	 */
	public function getFavorites() {
		if (!$this->userHasTag(ITags::TAG_FAVORITE, $this->user)) {
			return [];
		}

		try {
			return $this->getIdsForTag(ITags::TAG_FAVORITE);
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => __METHOD__,
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
			return [];
		}
	}

	/**
	 * Add an object to favorites
	 *
	 * @param int $objid The id of the object
	 * @return boolean
	 */
	public function addToFavorites($objid) {
		if (!$this->userHasTag(ITags::TAG_FAVORITE, $this->user)) {
			$this->add(ITags::TAG_FAVORITE);
		}
		return $this->tagAs($objid, ITags::TAG_FAVORITE);
	}

	/**
	 * Remove an object from favorites
	 *
	 * @param int $objid The id of the object
	 * @return boolean
	 */
	public function removeFromFavorites($objid) {
		return $this->unTag($objid, ITags::TAG_FAVORITE);
	}

	/**
	 * Creates a tag/object relation.
	 *
	 * @param int $objid The id of the object
	 * @param string $tag The id or name of the tag
	 * @return boolean Returns false on error.
	 */
	public function tagAs($objid, $tag) {
		if (is_string($tag) && !is_numeric($tag)) {
			$tag = trim($tag);
			if ($tag === '') {
				\OCP\Util::writeLog('core', __METHOD__.', Cannot add an empty tag', ILogger::DEBUG);
				return false;
			}
			if (!$this->hasTag($tag)) {
				$this->add($tag);
			}
			$tagId = $this->getTagId($tag);
		} else {
			$tagId = $tag;
		}
		try {
			\OC::$server->getDatabaseConnection()->insertIfNotExist(self::RELATION_TABLE,
				[
					'objid' => $objid,
					'categoryid' => $tagId,
					'type' => $this->type,
				]);
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => __METHOD__,
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
			return false;
		}
		return true;
	}

	/**
	 * Delete single tag/object relation from the db
	 *
	 * @param int $objid The id of the object
	 * @param string $tag The id or name of the tag
	 * @return boolean
	 */
	public function unTag($objid, $tag) {
		if (is_string($tag) && !is_numeric($tag)) {
			$tag = trim($tag);
			if ($tag === '') {
				\OCP\Util::writeLog('core', __METHOD__.', Tag name is empty', ILogger::DEBUG);
				return false;
			}
			$tagId = $this->getTagId($tag);
		} else {
			$tagId = $tag;
		}

		try {
			$sql = 'DELETE FROM `' . self::RELATION_TABLE . '` '
					. 'WHERE `objid` = ? AND `categoryid` = ? AND `type` = ?';
			$stmt = \OC_DB::prepare($sql);
			$stmt->execute([$objid, $tagId, $this->type]);
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'message' => __METHOD__,
				'level' => ILogger::ERROR,
				'app' => 'core',
			]);
			return false;
		}
		return true;
	}

	/**
	 * Delete tags from the database.
	 *
	 * @param string[]|integer[] $names An array of tags (names or IDs) to delete
	 * @return bool Returns false on error
	 */
	public function delete($names) {
		if (!is_array($names)) {
			$names = [$names];
		}

		$names = array_map('trim', $names);
		array_filter($names);

		\OCP\Util::writeLog('core', __METHOD__ . ', before: '
			. print_r($this->tags, true), ILogger::DEBUG);
		foreach ($names as $name) {
			$id = null;

			if (is_numeric($name)) {
				$key = $this->getTagById($name);
			} else {
				$key = $this->getTagByName($name);
			}
			if ($key !== false) {
				$tag = $this->tags[$key];
				$id = $tag->getId();
				unset($this->tags[$key]);
				$this->mapper->delete($tag);
			} else {
				\OCP\Util::writeLog('core', __METHOD__ . 'Cannot delete tag ' . $name
					. ': not found.', ILogger::ERROR);
			}
			if (!is_null($id) && $id !== false) {
				try {
					$sql = 'DELETE FROM `' . self::RELATION_TABLE . '` '
							. 'WHERE `categoryid` = ?';
					$stmt = \OC_DB::prepare($sql);
					$result = $stmt->execute([$id]);
					if ($result === null) {
						\OCP\Util::writeLog('core',
							__METHOD__. 'DB error: ' . \OC::$server->getDatabaseConnection()->getError(),
							ILogger::ERROR);
						return false;
					}
				} catch (\Exception $e) {
					\OC::$server->getLogger()->logException($e, [
						'message' => __METHOD__,
						'level' => ILogger::ERROR,
						'app' => 'core',
					]);
					return false;
				}
			}
		}
		return true;
	}

	// case-insensitive array_search
	protected function array_searchi($needle, $haystack, $mem = 'getName') {
		if (!is_array($haystack)) {
			return false;
		}
		return array_search(strtolower($needle), array_map(
			function ($tag) use ($mem) {
				return strtolower(call_user_func([$tag, $mem]));
			}, $haystack)
		);
	}

	/**
	 * Get a tag's ID.
	 *
	 * @param string $name The tag name to look for.
	 * @return string|bool The tag's id or false if no matching tag is found.
	 */
	private function getTagId($name) {
		$key = $this->array_searchi($name, $this->tags);
		if ($key !== false) {
			return $this->tags[$key]->getId();
		}
		return false;
	}

	/**
	 * Get a tag by its name.
	 *
	 * @param string $name The tag name.
	 * @return integer|bool The tag object's offset within the $this->tags
	 *                      array or false if it doesn't exist.
	 */
	private function getTagByName($name) {
		return $this->array_searchi($name, $this->tags, 'getName');
	}

	/**
	 * Get a tag by its ID.
	 *
	 * @param string $id The tag ID to look for.
	 * @return integer|bool The tag object's offset within the $this->tags
	 *                      array or false if it doesn't exist.
	 */
	private function getTagById($id) {
		return $this->array_searchi($id, $this->tags, 'getId');
	}

	/**
	 * Returns an array mapping a given tag's properties to its values:
	 * ['id' => 0, 'name' = 'Tag', 'owner' = 'User', 'type' => 'tagtype']
	 *
	 * @param Tag $tag The tag that is going to be mapped
	 * @return array
	 */
	private function tagMap(Tag $tag) {
		return [
			'id' => $tag->getId(),
			'name' => $tag->getName(),
			'owner' => $tag->getOwner(),
			'type' => $tag->getType()
		];
	}
}
