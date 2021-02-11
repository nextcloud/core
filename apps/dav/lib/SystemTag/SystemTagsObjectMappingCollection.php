<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\SystemTag;

use OCP\IUser;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\PreconditionFailed;
use Sabre\DAV\ICollection;

/**
 * Collection containing tags by object id
 */
class SystemTagsObjectMappingCollection implements ICollection {

	/**
	 * @var string
	 */
	private $objectId;

	/**
	 * @var string
	 */
	private $objectType;

	/**
	 * @var ISystemTagManager
	 */
	private $tagManager;

	/**
	 * @var ISystemTagObjectMapper
	 */
	private $tagMapper;

	/**
	 * User
	 *
	 * @var IUser
	 */
	private $user;


	/**
	 * Constructor
	 *
	 * @param string $objectId object id
	 * @param string $objectType object type
	 * @param IUser $user user
	 * @param ISystemTagManager $tagManager tag manager
	 * @param ISystemTagObjectMapper $tagMapper tag mapper
	 */
	public function __construct(
		$objectId,
		$objectType,
		IUser $user,
		ISystemTagManager $tagManager,
		ISystemTagObjectMapper $tagMapper
	) {
		$this->tagManager = $tagManager;
		$this->tagMapper = $tagMapper;
		$this->objectId = $objectId;
		$this->objectType = $objectType;
		$this->user = $user;
	}

	public function createFile($name, $data = null) {
		$tagId = $name;
		try {
			$tags = $this->tagManager->getTagsByIds([$tagId]);
			$tag = current($tags);
			if (!$this->tagManager->canUserSeeTag($tag, $this->user)) {
				throw new PreconditionFailed('Tag with id ' . $tagId . ' does not exist, cannot assign');
			}
			if (!$this->tagManager->canUserAssignTag($tag, $this->user)) {
				throw new Forbidden('No permission to assign tag ' . $tagId);
			}

			$this->tagMapper->assignTags($this->objectId, $this->objectType, $tagId);
		} catch (TagNotFoundException $e) {
			throw new PreconditionFailed('Tag with id ' . $tagId . ' does not exist, cannot assign');
		}
	}

	public function createDirectory($name) {
		throw new Forbidden('Permission denied to create collections');
	}

	public function getChild($tagId) {
		try {
			if ($this->tagMapper->haveTag([$this->objectId], $this->objectType, $tagId, true)) {
				$tag = $this->tagManager->getTagsByIds([$tagId]);
				$tag = current($tag);
				if ($this->tagManager->canUserSeeTag($tag, $this->user)) {
					return $this->makeNode($tag);
				}
			}
			throw new NotFound('Tag with id ' . $tagId . ' not present for object ' . $this->objectId);
		} catch (\InvalidArgumentException $e) {
			throw new BadRequest('Invalid tag id', 0, $e);
		} catch (TagNotFoundException $e) {
			throw new NotFound('Tag with id ' . $tagId . ' not found', 0, $e);
		}
	}

	public function getChildren() {
		$tagIds = current($this->tagMapper->getTagIdsForObjects([$this->objectId], $this->objectType));
		if (empty($tagIds)) {
			return [];
		}
		$tags = $this->tagManager->getTagsByIds($tagIds);

		// filter out non-visible tags
		$tags = array_filter($tags, function ($tag) {
			return $this->tagManager->canUserSeeTag($tag, $this->user);
		});

		return array_values(array_map(function ($tag) {
			return $this->makeNode($tag);
		}, $tags));
	}

	public function childExists($tagId) {
		try {
			$result = $this->tagMapper->haveTag([$this->objectId], $this->objectType, $tagId, true);

			if ($result) {
				$tags = $this->tagManager->getTagsByIds([$tagId]);
				$tag = current($tags);
				if (!$this->tagManager->canUserSeeTag($tag, $this->user)) {
					return false;
				}
			}

			return $result;
		} catch (\InvalidArgumentException $e) {
			throw new BadRequest('Invalid tag id', 0, $e);
		} catch (TagNotFoundException $e) {
			return false;
		}
	}

	public function delete() {
		throw new Forbidden('Permission denied to delete this collection');
	}

	public function getName() {
		return $this->objectId;
	}

	public function setName($name) {
		throw new Forbidden('Permission denied to rename this collection');
	}

	/**
	 * Returns the last modification time, as a unix timestamp
	 *
	 * @return int
	 */
	public function getLastModified() {
		return null;
	}

	/**
	 * Create a sabre node for the mapping of the
	 * given system tag to the collection's object
	 *
	 * @param ISystemTag $tag
	 *
	 * @return SystemTagMappingNode
	 */
	private function makeNode(ISystemTag $tag) {
		return new SystemTagMappingNode(
			$tag,
			$this->objectId,
			$this->objectType,
			$this->user,
			$this->tagManager,
			$this->tagMapper
		);
	}
}
