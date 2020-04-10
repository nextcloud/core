<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Max Kovalenko <mxss1998@yandex.ru>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Files\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OC\Lock\DBLockingProvider;

/**
 * Clean up all file locks that are expired for the DB file locking provider
 */
class CleanupFileLocks extends TimedJob {

	/**
	 * Default interval in minutes
	 *
	 * @var int $defaultIntervalMin
	 **/
	protected $defaultIntervalMin = 5;

	/**
	 * sets the correct interval for this timed job
	 */
	public function __construct() {
		$this->interval = $this->defaultIntervalMin * 60;
	}

	/**
	 * Makes the background job do its work
	 *
	 * @param array $argument unused argument
	 * @throws \Exception
	 */
	public function run($argument) {
		$lockingProvider = \OC::$server->getLockingProvider();
		if($lockingProvider instanceof DBLockingProvider) {
			$lockingProvider->cleanExpiredLocks();
		}
	}
}
