<?php

declare(strict_types=1);

/**
 * @copyright 2018
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

namespace OC\FullTextSearch\Model;

use JsonSerializable;
use OCP\FullTextSearch\Model\IDocumentAccess;

/**
 * Class IDocumentAccess
 *
 * This object is used as a data transfer object when
 *
 * - indexing a document,
 * - generating a search request.
 *
 * During the index, it is used to define which users, groups, circles, ...
 * have access to the IndexDocument
 *
 * During the search, it is internally use to define to which group, circles, ...
 * a user that perform the search belongs to.
 *
 * @see IIndexDocument::setAccess
 *
 * @since 16.0.0
 *
 * @package OC\FullTextSearch\Model
 */
final class DocumentAccess implements IDocumentAccess, JsonSerializable {


	/** @var string */
	private $ownerId;

	/** @var string */
	private $viewerId = '';

	/** @var array */
	private $users = [];

	/** @var array */
	private $groups = [];

	/** @var array */
	private $circles = [];

	/** @var array */
	private $links = [];


	/**
	 * Owner of the document can be set at the init of the object.
	 *
	 * @since 16.0.0
	 *
	 * IDocumentAccess constructor.
	 *
	 * @param string $ownerId
	 */
	public function __construct(string $ownerId = '') {
		$this->setOwnerId($ownerId);
	}


	/**
	 * Set the Owner of the document.
	 *
	 * @since 16.0.0
	 *
	 * @param string $ownerId
	 *
	 * @return IDocumentAccess
	 */
	public function setOwnerId(string $ownerId): IDocumentAccess {
		$this->ownerId = $ownerId;

		return $this;
	}

	/**
	 * Get the Owner of the document.
	 *
	 * @since 16.0.0
	 *
	 * @return string
	 */
	public function getOwnerId(): string {
		return $this->ownerId;
	}


	/**
	 * Set the viewer of the document.
	 *
	 * @since 16.0.0
	 *
	 * @param string $viewerId
	 *
	 * @return IDocumentAccess
	 */
	public function setViewerId(string $viewerId): IDocumentAccess {
		$this->viewerId = $viewerId;

		return $this;
	}

	/**
	 * Get the viewer of the document.
	 *
	 * @since 16.0.0
	 *
	 * @return string
	 */
	public function getViewerId(): string {
		return $this->viewerId;
	}


	/**
	 * Set the list of users that have read access to the document.
	 *
	 * @since 16.0.0
	 *
	 * @param array $users
	 *
	 * @return IDocumentAccess
	 */
	public function setUsers(array $users): IDocumentAccess {
		$this->users = $users;

		return $this;
	}

	/**
	 * Add an entry to the list of users that have read access to the document.
	 *
	 * @since 16.0.0
	 *
	 * @param string $user
	 *
	 * @return IDocumentAccess
	 */
	public function addUser(string $user): IDocumentAccess {
		$this->users[] = $user;

		return $this;
	}

	/**
	 * Add multiple entries to the list of users that have read access to the
	 * document.
	 *
	 * @since 16.0.0
	 *
	 * @param array $users
	 *
	 * @return IDocumentAccess
	 */
	public function addUsers($users): IDocumentAccess {
		$this->users = array_merge($this->users, $users);

		return $this;
	}

	/**
	 * Get the complete list of users that have read access to the document.
	 *
	 * @since 16.0.0
	 *
	 * @return array
	 */
	public function getUsers(): array {
		return $this->users;
	}


	/**
	 * Set the list of groups that have read access to the document.
	 *
	 * @since 16.0.0
	 *
	 * @param array $groups
	 *
	 * @return IDocumentAccess
	 */
	public function setGroups(array $groups): IDocumentAccess {
		$this->groups = $groups;

		return $this;
	}

	/**
	 * Add an entry to the list of groups that have read access to the document.
	 *
	 * @since 16.0.0
	 *
	 * @param string $group
	 *
	 * @return IDocumentAccess
	 */
	public function addGroup(string $group): IDocumentAccess {
		$this->groups[] = $group;

		return $this;
	}

	/**
	 * Add multiple entries to the list of groups that have read access to the
	 * document.
	 *
	 * @since 16.0.0
	 *
	 * @param array $groups
	 *
	 * @return IDocumentAccess
	 */
	public function addGroups(array $groups) {
		$this->groups = array_merge($this->groups, $groups);

		return $this;
	}

	/**
	 * Get the complete list of groups that have read access to the document.
	 *
	 * @since 16.0.0
	 *
	 * @return array
	 */
	public function getGroups(): array {
		return $this->groups;
	}


	/**
	 * Set the list of circles that have read access to the document.
	 *
	 * @since 16.0.0
	 *
	 * @param array $circles
	 *
	 * @return IDocumentAccess
	 */
	public function setCircles(array $circles): IDocumentAccess {
		$this->circles = $circles;

		return $this;
	}

	/**
	 * Add an entry to the list of circles that have read access to the document.
	 *
	 * @since 16.0.0
	 *
	 * @param string $circle
	 *
	 * @return IDocumentAccess
	 */
	public function addCircle(string $circle): IDocumentAccess {
		$this->circles[] = $circle;

		return $this;
	}

	/**
	 * Add multiple entries to the list of groups that have read access to the
	 * document.
	 *
	 * @since 16.0.0
	 *
	 * @param array $circles
	 *
	 * @return IDocumentAccess
	 */
	public function addCircles(array $circles): IDocumentAccess {
		$this->circles = array_merge($this->circles, $circles);

		return $this;
	}

	/**
	 * Get the complete list of circles that have read access to the document.
	 *
	 * @since 16.0.0
	 *
	 * @return array
	 */
	public function getCircles(): array {
		return $this->circles;
	}


	/**
	 * Set the list of links that have read access to the document.
	 *
	 * @since 16.0.0
	 *
	 * @param array $links
	 *
	 * @return IDocumentAccess
	 */
	public function setLinks(array $links): IDocumentAccess {
		$this->links = $links;

		return $this;
	}

	/**
	 * Get the list of links that have read access to the document.
	 *
	 * @since 16.0.0
	 *
	 * @return array
	 */
	public function getLinks(): array {
		return $this->links;
	}


	/**
	 * @since 16.0.0
	 *
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'ownerId' => $this->getOwnerId(),
			'viewerId' => $this->getViewerId(),
			'users' => $this->getUsers(),
			'groups' => $this->getGroups(),
			'circles' => $this->getCircles(),
			'links' => $this->getLinks()
		];
	}
}
