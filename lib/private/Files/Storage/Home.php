<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Files\Storage;

use OC\Files\Cache\HomePropagator;

/**
 * Specialized version of Local storage for home directory usage
 */
class Home extends Local implements \OCP\Files\IHomeStorage {
	/**
	 * @var string
	 */
	protected $id;

	/**
	 * @var \OC\User\User $user
	 */
	protected $user;

	/**
	 * Construct a Home storage instance
	 *
	 * @param array $arguments array with "user" containing the
	 * storage owner
	 */
	public function __construct($arguments) {
		$this->user = $arguments['user'];
		$datadir = $this->user->getHome();
		$this->id = 'home::' . $this->user->getUID();

		parent::__construct(['datadir' => $datadir]);
	}

	public function getId() {
		return $this->id;
	}

	/**
	 * @return \OC\Files\Cache\HomeCache
	 */
	public function getCache($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($this->cache)) {
			$this->cache = new \OC\Files\Cache\HomeCache($storage);
		}
		return $this->cache;
	}

	/**
	 * get a propagator instance for the cache
	 *
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the watcher
	 * @return \OC\Files\Cache\Propagator
	 */
	public function getPropagator($storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($this->propagator)) {
			$this->propagator = new HomePropagator($storage, \OC::$server->getDatabaseConnection());
		}
		return $this->propagator;
	}


	/**
	 * Returns the owner of this home storage
	 *
	 * @return \OC\User\User owner of this home storage
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * get the owner of a path
	 *
	 * @param string $path The path to get the owner
	 * @return string uid or false
	 */
	public function getOwner($path) {
		return $this->user->getUID();
	}
}
