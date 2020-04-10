<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\Repair;

use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Share;

class OldGroupMembershipShares implements IRepairStep {

	/** @var \OCP\IDBConnection */
	protected $connection;

	/** @var \OCP\IGroupManager */
	protected $groupManager;

	/**
	 * @var array [gid => [uid => (bool)]]
	 */
	protected $memberships;

	/**
	 * @param IDBConnection $connection
	 * @param IGroupManager $groupManager
	 */
	public function __construct(IDBConnection $connection, IGroupManager $groupManager) {
		$this->connection = $connection;
		$this->groupManager = $groupManager;
	}

	/**
	 * Returns the step's name
	 *
	 * @return string
	 */
	public function getName() {
		return 'Remove shares of old group memberships';
	}

	/**
	 * Run repair step.
	 * Must throw exception on error.
	 *
	 * @throws \Exception in case of failure
	 * @suppress SqlInjectionChecker
	 */
	public function run(IOutput $output) {
		$deletedEntries = 0;

		$query = $this->connection->getQueryBuilder();
		$query->select('s1.id')->selectAlias('s1.share_with', 'user')->selectAlias('s2.share_with', 'group')
			->from('share', 's1')
			->where($query->expr()->isNotNull('s1.parent'))
				// \OC\Share\Constant::$shareTypeGroupUserUnique === 2
				->andWhere($query->expr()->eq('s1.share_type', $query->expr()->literal(2)))
				->andWhere($query->expr()->isNotNull('s2.id'))
				->andWhere($query->expr()->eq('s2.share_type', $query->expr()->literal(Share::SHARE_TYPE_GROUP)))
			->leftJoin('s1', 'share', 's2', $query->expr()->eq('s1.parent', 's2.id'));

		$deleteQuery = $this->connection->getQueryBuilder();
		$deleteQuery->delete('share')
			->where($query->expr()->eq('id', $deleteQuery->createParameter('share')));

		$result = $query->execute();
		while ($row = $result->fetch()) {
			if (!$this->isMember($row['group'], $row['user'])) {
				$deletedEntries += $deleteQuery->setParameter('share', (int) $row['id'])
					->execute();
			}
		}
		$result->closeCursor();

		if ($deletedEntries) {
			$output->info('Removed ' . $deletedEntries . ' shares where user is not a member of the group anymore');
		}
	}

	/**
	 * @param string $gid
	 * @param string $uid
	 * @return bool
	 */
	protected function isMember($gid, $uid) {
		if (isset($this->memberships[$gid][$uid])) {
			return $this->memberships[$gid][$uid];
		}

		$isMember = $this->groupManager->isInGroup($uid, $gid);
		if (!isset($this->memberships[$gid])) {
			$this->memberships[$gid] = [];
		}
		$this->memberships[$gid][$uid] = $isMember;

		return $isMember;
	}
}
