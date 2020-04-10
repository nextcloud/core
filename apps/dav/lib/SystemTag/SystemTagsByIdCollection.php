<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\TagNotFoundException;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

class SystemTagsByIdCollection implements ICollection {

	/**
	 * @var ISystemTagManager
	 */
	private $tagManager;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * @var IUserSession
	 */
	private $userSession;

	/**
	 * SystemTagsByIdCollection constructor.
	 *
	 * @param ISystemTagManager $tagManager
	 * @param IUserSession $userSession
	 * @param IGroupManager $groupManager
	 */
	public function __construct(
		ISystemTagManager $tagManager,
		IUserSession $userSession,
		IGroupManager $groupManager
	) {
		$this->tagManager = $tagManager;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
	}

	/**
	 * Returns whether the currently logged in user is an administrator
	 *
	 * @return bool true if the user is an admin
	 */
	private function isAdmin() {
		$user = $this->userSession->getUser();
		if ($user !== null) {
			return $this->groupManager->isAdmin($user->getUID());
		}
		return false;
	}

	/**
	 * @param string $name
	 * @param resource|string $data Initial payload
	 * @throws Forbidden
	 */
	function createFile($name, $data = null) {
		throw new Forbidden('Cannot create tags by id');
	}

	/**
	 * @param string $name
	 */
	function createDirectory($name) {
		throw new Forbidden('Permission denied to create collections');
	}

	/**
	 * @param string $name
	 */
	function getChild($name) {
		try {
			$tag = $this->tagManager->getTagsByIds([$name]);
			$tag = current($tag);
			if (!$this->tagManager->canUserSeeTag($tag, $this->userSession->getUser())) {
				throw new NotFound('Tag with id ' . $name . ' not found');
			}
			return $this->makeNode($tag);
		} catch (\InvalidArgumentException $e) {
			throw new BadRequest('Invalid tag id', 0, $e);
		} catch (TagNotFoundException $e) {
			throw new NotFound('Tag with id ' . $name . ' not found', 0, $e);
		}
	}

	function getChildren() {
		$visibilityFilter = true;
		if ($this->isAdmin()) {
			$visibilityFilter = null;
		}

		$tags = $this->tagManager->getAllTags($visibilityFilter);
		return array_map(function ($tag) {
			return $this->makeNode($tag);
		}, $tags);
	}

	/**
	 * @param string $name
	 */
	function childExists($name) {
		try {
			$tag = $this->tagManager->getTagsByIds([$name]);
			$tag = current($tag);
			if (!$this->tagManager->canUserSeeTag($tag, $this->userSession->getUser())) {
				return false;
			}
			return true;
		} catch (\InvalidArgumentException $e) {
			throw new BadRequest('Invalid tag id', 0, $e);
		} catch (TagNotFoundException $e) {
			return false;
		}
	}

	function delete() {
		throw new Forbidden('Permission denied to delete this collection');
	}

	function getName() {
		return 'systemtags';
	}

	function setName($name) {
		throw new Forbidden('Permission denied to rename this collection');
	}

	/**
	 * Returns the last modification time, as a unix timestamp
	 *
	 * @return int
	 */
	function getLastModified() {
		return null;
	}

	/**
	 * Create a sabre node for the given system tag
	 *
	 * @param ISystemTag $tag
	 *
	 * @return SystemTagNode
	 */
	private function makeNode(ISystemTag $tag) {
		return new SystemTagNode($tag, $this->userSession->getUser(), $this->isAdmin(), $this->tagManager);
	}
}
