<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Repair;

use Doctrine\DBAL\Driver\Statement;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Notification\IManager;

class RemoveLinkShares implements IRepairStep {
	/** @var IDBConnection */
	private $connection;
	/** @var IConfig */
	private $config;
	/** @var string[] */
	private $userToNotify = [];
	/** @var IGroupManager */
	private $groupManager;
	/** @var IManager */
	private $notificationManager;
	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(IDBConnection $connection,
								IConfig $config,
								IGroupManager $groupManager,
								IManager $notificationManager,
								ITimeFactory $timeFactory) {
		$this->connection = $connection;
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->notificationManager = $notificationManager;
		$this->timeFactory = $timeFactory;
	}


	public function getName(): string {
		return 'Remove potentially over exposing share links';
	}

	private function shouldRun(): bool {
		$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0');

		if (version_compare($versionFromBeforeUpdate, '14.0.11', '<')) {
			return true;
		}
		if (version_compare($versionFromBeforeUpdate, '15.0.8', '<')) {
			return true;
		}
		if (version_compare($versionFromBeforeUpdate, '16.0.0', '<=')) {
			return true;
		}

		return false;
	}

	/**
	 * Delete the share
	 *
	 * @param int $id
	 */
	private function deleteShare(int $id): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$qb->execute();
	}

	/**
	 * Get the total of affected shares
	 *
	 * @return int
	 */
	private function getTotal(): int {
		$subSubQuery = $this->connection->getQueryBuilder();
		$subSubQuery->select('*')
			->from('share')
			->where($subSubQuery->expr()->isNotNull('parent'))
			->andWhere($subSubQuery->expr()->eq('share_type', $subSubQuery->expr()->literal(3, IQueryBuilder::PARAM_INT)));

		$subQuery = $this->connection->getQueryBuilder();
		$subQuery->select('s1.id')
			->from($subQuery->createFunction('(' . $subSubQuery->getSQL() . ')'), 's1')
			->join(
				's1', 'share', 's2',
				$subQuery->expr()->eq('s1.parent', 's2.id')
			)
			->where($subQuery->expr()->orX(
				$subQuery->expr()->eq('s2.share_type', $subQuery->expr()->literal(1, IQueryBuilder::PARAM_INT)),
				$subQuery->expr()->eq('s2.share_type', $subQuery->expr()->literal(2, IQueryBuilder::PARAM_INT))
			))
			->andWhere($subQuery->expr()->eq('s1.item_source', 's2.item_source'));

		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->count('*', 'total'))
			->from('share')
			->where($query->expr()->in('id', $query->createFunction('(' . $subQuery->getSQL() . ')')));

		$result = $query->execute();
		$data = $result->fetch();
		$result->closeCursor();

		return (int) $data['total'];
	}

	/**
	 * Get the cursor to fetch all the shares
	 *
	 * @return \Doctrine\DBAL\Driver\Statement
	 */
	private function getShares(): Statement {
		$subQuery = $this->connection->getQueryBuilder();
		$subQuery->select('*')
			->from('share')
			->where($subQuery->expr()->isNotNull('parent'))
			->andWhere($subQuery->expr()->eq('share_type', $subQuery->expr()->literal(3, IQueryBuilder::PARAM_INT)));

		$query = $this->connection->getQueryBuilder();
		$query->select('s1.id', 's1.uid_owner', 's1.uid_initiator')
			->from($query->createFunction('(' . $subQuery->getSQL() . ')'), 's1')
			->join(
				's1', 'share', 's2',
				$query->expr()->eq('s1.parent', 's2.id')
			)
			->where($query->expr()->orX(
				$query->expr()->eq('s2.share_type', $query->expr()->literal(1, IQueryBuilder::PARAM_INT)),
				$query->expr()->eq('s2.share_type', $query->expr()->literal(2, IQueryBuilder::PARAM_INT))
			))
			->andWhere($query->expr()->eq('s1.item_source', 's2.item_source'));
		return $query->execute();
	}

	/**
	 * Process a single share
	 *
	 * @param array $data
	 */
	private function processShare(array $data): void {
		$id = $data['id'];

		$this->addToNotify($data['uid_owner']);
		$this->addToNotify($data['uid_initiator']);

		$this->deleteShare((int)$id);
	}

	/**
	 * Update list of users to notify
	 *
	 * @param string $uid
	 */
	private function addToNotify(string $uid): void {
		if (!isset($this->userToNotify[$uid])) {
			$this->userToNotify[$uid] = true;
		}
	}

	/**
	 * Send all notifications
	 */
	private function sendNotification(): void {
		$time = $this->timeFactory->getDateTime();

		$notification = $this->notificationManager->createNotification();
		$notification->setApp('core')
			->setDateTime($time)
			->setObject('repair', 'exposing_links')
			->setSubject('repair_exposing_links');

		$users = array_keys($this->userToNotify);
		foreach ($users as $user) {
			$notification->setUser((string) $user);
			$this->notificationManager->notify($notification);
		}
	}

	private function repair(IOutput $output, int $total): void {
		$output->startProgress($total);

		$shareCursor = $this->getShares();
		while($data = $shareCursor->fetch()) {
			$this->processShare($data);
			$output->advance();
		}
		$output->finishProgress();
		$shareCursor->closeCursor();

		// Notifiy all admins
		$adminGroup = $this->groupManager->get('admin');
		$adminUsers = $adminGroup->getUsers();
		foreach ($adminUsers as $user) {
			$this->addToNotify($user->getUID());
		}

		$output->info('Sending notifications to admins and affected users');
		$this->sendNotification();
	}

	public function run(IOutput $output): void {
		if ($this->shouldRun() === false || ($total = $this->getTotal()) === 0) {
			$output->info('No need to remove link shares.');
			return;
		}

		$output->info('Removing potentially over exposing link shares');
		$this->repair($output, $total);
		$output->info('Removed potentially over exposing link shares');
	}
}
