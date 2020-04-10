<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Comments;

use OCP\Comments\ICommentsManager;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IUserSession;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\Exception\NotFound;

/**
 * Class EntityTypeCollection
 *
 * This is collection on the type of things a user can leave comments on, for
 * example: 'files'.
 *
 * Its children are instances of EntityCollection (representing a specific
 * object, for example the file by id).
 *
 * @package OCA\DAV\Comments
 */
class EntityTypeCollection extends RootCollection {

	/** @var ILogger */
	protected $logger;

	/** @var IUserManager */
	protected $userManager;

	/** @var \Closure */
	protected $childExistsFunction;

	/**
	 * @param string $name
	 * @param ICommentsManager $commentsManager
	 * @param IUserManager $userManager
	 * @param IUserSession $userSession
	 * @param ILogger $logger
	 * @param \Closure $childExistsFunction
	 */
	public function __construct(
		$name,
		ICommentsManager $commentsManager,
		IUserManager $userManager,
		IUserSession $userSession,
		ILogger $logger,
		\Closure $childExistsFunction
	) {
		$name = trim($name);
		if(empty($name) || !is_string($name)) {
			throw new \InvalidArgumentException('"name" parameter must be non-empty string');
		}
		$this->name = $name;
		$this->commentsManager = $commentsManager;
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->childExistsFunction = $childExistsFunction;
	}

	/**
	 * Returns a specific child node, referenced by its name
	 *
	 * This method must throw Sabre\DAV\Exception\NotFound if the node does not
	 * exist.
	 *
	 * @param string $name
	 * @return \Sabre\DAV\INode
	 * @throws NotFound
	 */
	function getChild($name) {
		if(!$this->childExists($name)) {
			throw new NotFound('Entity does not exist or is not available');
		}
		return new EntityCollection(
			$name,
			$this->name,
			$this->commentsManager,
			$this->userManager,
			$this->userSession,
			$this->logger
		);
	}

	/**
	 * Returns an array with all the child nodes
	 *
	 * @return \Sabre\DAV\INode[]
	 * @throws MethodNotAllowed
	 */
	function getChildren() {
		throw new MethodNotAllowed('No permission to list folder contents');
	}

	/**
	 * Checks if a child-node with the specified name exists
	 *
	 * @param string $name
	 * @return bool
	 */
	function childExists($name) {
		return call_user_func($this->childExistsFunction, $name);
	}

}
