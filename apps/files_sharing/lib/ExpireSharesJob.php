<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IDBConnection;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;

/**
 * Delete all shares that are expired
 */
class ExpireSharesJob extends TimedJob {

	/** @var IManager */
	private $shareManager;

	/** @var IDBConnection */
	private $db;

	public function __construct(ITimeFactory $time, IManager $shareManager, IDBConnection $db) {
		$this->shareManager = $shareManager;
		$this->db = $db;

		parent::__construct($time);

		// Run once a day
		$this->setInterval(24 * 60 * 60);
	}


	/**
	 * Makes the background job do its work
	 *
	 * @param array $argument unused argument
	 */
	public function run($argument) {
		//Current time
		$now = new \DateTime();
		$now = $now->format('Y-m-d H:i:s');

		/*
		 * Expire file link shares only (for now)
		 */
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'share_type')
			->from('share')
			->where(
				$qb->expr()->andX(
					$qb->expr()->orX(
						$qb->expr()->eq('share_type', $qb->expr()->literal(IShare::TYPE_LINK)),
						$qb->expr()->eq('share_type', $qb->expr()->literal(IShare::TYPE_EMAIL))
					),
					$qb->expr()->lte('expiration', $qb->expr()->literal($now)),
					$qb->expr()->orX(
						$qb->expr()->eq('item_type', $qb->expr()->literal('file')),
						$qb->expr()->eq('item_type', $qb->expr()->literal('folder'))
					)
				)
			);

		$shares = $qb->execute();
		while ($share = $shares->fetch()) {
			if ((int)$share['share_type'] === IShare::TYPE_LINK) {
				$id = 'ocinternal';
			} elseif ((int)$share['share_type'] === IShare::TYPE_EMAIL) {
				$id = 'ocMailShare';
			}

			$id .= ':' . $share['id'];

			try {
				$share = $this->shareManager->getShareById($id);
				$this->shareManager->deleteShare($share);
			} catch (ShareNotFound $e) {
				// Normally the share gets automatically expired on fetching it
			}
		}
		$shares->closeCursor();
	}
}
