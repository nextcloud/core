<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_Sharing;

use OC\BackgroundJob\TimedJob;

/**
 * Delete all shares that are expired
 */
class ExpireSharesJob extends TimedJob {

	/**
	 * sets the correct interval for this timed job
	 */
	public function __construct() {
		// Run once a day
		$this->setInterval(24 * 60 * 60);
	}

	/**
	 * Makes the background job do its work
	 *
	 * @param array $argument unused argument
	 */
	public function run($argument) {
		$connection = \OC::$server->getDatabaseConnection();

		//Current time
		$now = new \DateTime();
		$now = $now->format('Y-m-d H:i:s');

		/*
		 * Expire file link shares only (for now)
		 */
		$qb = $connection->getQueryBuilder();
		$qb->select('id', 'file_source', 'uid_owner', 'item_type')
			->from('share')
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('share_type', $qb->expr()->literal(\OCP\Share::SHARE_TYPE_LINK)),
					$qb->expr()->lte('expiration', $qb->expr()->literal($now)),
					$qb->expr()->orX(
						$qb->expr()->eq('item_type', $qb->expr()->literal('file')),
						$qb->expr()->eq('item_type', $qb->expr()->literal('folder'))
					)
				)
			);

		$shares = $qb->execute();
		while($share = $shares->fetch()) {
			\OC\Share\Share::unshare($share['item_type'], $share['file_source'], \OCP\Share::SHARE_TYPE_LINK, null, $share['uid_owner']);
		}
		$shares->closeCursor();
	}

}
