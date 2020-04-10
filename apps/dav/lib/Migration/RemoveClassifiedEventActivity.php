<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Migration;

use OCA\DAV\CalDAV\CalDavBackend;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RemoveClassifiedEventActivity implements IRepairStep {

	/** @var IDBConnection */
	private $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @inheritdoc
	 */
	public function getName() {
		return 'Remove activity entries of private events';
	}

	/**
	 * @inheritdoc
	 */
	public function run(IOutput $output) {
		if (!$this->connection->tableExists('activity')) {
			return;
		}

		$deletedEvents = $this->removePrivateEventActivity();
		$deletedEvents += $this->removeConfidentialUncensoredEventActivity();

		$output->info("Removed $deletedEvents activity entries");
	}

	protected function removePrivateEventActivity(): int {
		$deletedEvents = 0;

		$delete = $this->connection->getQueryBuilder();
		$delete->delete('activity')
			->where($delete->expr()->neq('affecteduser', $delete->createParameter('owner')))
			->andWhere($delete->expr()->eq('object_type', $delete->createParameter('type')))
			->andWhere($delete->expr()->eq('object_id', $delete->createParameter('calendar_id')))
			->andWhere($delete->expr()->like('subjectparams', $delete->createParameter('event_uid')));

		$query = $this->connection->getQueryBuilder();
		$query->select('c.principaluri', 'o.calendarid', 'o.uid')
			->from('calendarobjects', 'o')
			->leftJoin('o', 'calendars', 'c', $query->expr()->eq('c.id', 'o.calendarid'))
			->where($query->expr()->eq('o.classification', $query->createNamedParameter(CalDavBackend::CLASSIFICATION_PRIVATE)));
		$result = $query->execute();

		while ($row = $result->fetch()) {
			if ($row['principaluri'] === null) {
				continue;
			}

			$delete->setParameter('owner', $this->getPrincipal($row['principaluri']))
				->setParameter('type', 'calendar')
				->setParameter('calendar_id', $row['calendarid'])
				->setParameter('event_uid', '%' . $this->connection->escapeLikeParameter('{"id":"' . $row['uid'] . '"') . '%');
			$deletedEvents += $delete->execute();
		}
		$result->closeCursor();

		return $deletedEvents;
	}

	protected function removeConfidentialUncensoredEventActivity(): int {
		$deletedEvents = 0;

		$delete = $this->connection->getQueryBuilder();
		$delete->delete('activity')
			->where($delete->expr()->neq('affecteduser', $delete->createParameter('owner')))
			->andWhere($delete->expr()->eq('object_type', $delete->createParameter('type')))
			->andWhere($delete->expr()->eq('object_id', $delete->createParameter('calendar_id')))
			->andWhere($delete->expr()->like('subjectparams', $delete->createParameter('event_uid')))
			->andWhere($delete->expr()->notLike('subjectparams', $delete->createParameter('filtered_name')));

		$query = $this->connection->getQueryBuilder();
		$query->select('c.principaluri', 'o.calendarid', 'o.uid')
			->from('calendarobjects', 'o')
			->leftJoin('o', 'calendars', 'c', $query->expr()->eq('c.id', 'o.calendarid'))
			->where($query->expr()->eq('o.classification', $query->createNamedParameter(CalDavBackend::CLASSIFICATION_CONFIDENTIAL)));
		$result = $query->execute();

		while ($row = $result->fetch()) {
			if ($row['principaluri'] === null) {
				continue;
			}

			$delete->setParameter('owner', $this->getPrincipal($row['principaluri']))
				->setParameter('type', 'calendar')
				->setParameter('calendar_id', $row['calendarid'])
				->setParameter('event_uid', '%' . $this->connection->escapeLikeParameter('{"id":"' . $row['uid'] . '"') . '%')
				->setParameter('filtered_name', '%' . $this->connection->escapeLikeParameter('{"id":"' . $row['uid'] . '","name":"Busy"') . '%');
			$deletedEvents += $delete->execute();
		}
		$result->closeCursor();

		return $deletedEvents;
	}

	protected function getPrincipal(string $principalUri): string {
		$uri = explode('/', $principalUri);
		return array_pop($uri);
	}
}
