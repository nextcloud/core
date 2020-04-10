<?php
/**
 * @author Robin Appelman <icewind@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\Lock;

use OC\Memcache\ArrayCache;

class MemcacheLockingProviderTest extends LockingProvider {

	/**
	 * @var \OCP\IMemcache
	 */
	private $memcache;

	/**
	 * @return \OCP\Lock\ILockingProvider
	 */
	protected function getInstance() {
		$this->memcache = new ArrayCache();
		return new \OC\Lock\MemcacheLockingProvider($this->memcache);
	}

	protected function tearDown(): void {
		$this->memcache->clear();
		parent::tearDown();
	}
}
