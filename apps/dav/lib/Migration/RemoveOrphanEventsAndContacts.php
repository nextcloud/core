<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RemoveOrphanEventsAndContacts implements IRepairStep {

	/** @var IDBConnection */
	private $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @inheritdoc
	 */
	public function getName(): string {
		return 'Clean up orphan event and contact data';
	}

	/**
	 * @inheritdoc
	 */
	public function run(IOutput $output) {
		$orphanItems = $this->removeOrphanChildren('calendarobjects', 'calendars',  'calendarid');
		$output->info(sprintf('%d events without a calendar have been cleaned up', $orphanItems));
		$orphanItems = $this->removeOrphanChildren('calendarobjects_props', 'calendarobjects',  'objectid');
		$output->info(sprintf('%d properties without an events have been cleaned up', $orphanItems));
		$orphanItems = $this->removeOrphanChildren('calendarchanges', 'calendars',  'calendarid');
		$output->info(sprintf('%d changes without a calendar have been cleaned up', $orphanItems));

		$orphanItems = $this->removeOrphanChildren('calendarobjects', 'calendarsubscriptions',  'calendarid');
		$output->info(sprintf('%d cached events without a calendar subscription have been cleaned up', $orphanItems));
		$orphanItems = $this->removeOrphanChildren('calendarchanges', 'calendarsubscriptions',  'calendarid');
		$output->info(sprintf('%d changes without a calendar subscription have been cleaned up', $orphanItems));

		$orphanItems = $this->removeOrphanChildren('cards', 'addressbooks',  'addressbookid');
		$output->info(sprintf('%d contacts without an addressbook have been cleaned up', $orphanItems));
		$orphanItems = $this->removeOrphanChildren('cards_properties', 'cards',  'cardid');
		$output->info(sprintf('%d properties without a contact have been cleaned up', $orphanItems));
		$orphanItems = $this->removeOrphanChildren('addressbookchanges', 'addressbooks',  'addressbookid');
		$output->info(sprintf('%d changes without an addressbook have been cleaned up', $orphanItems));
	}

	protected function removeOrphanChildren($childTable, $parentTable, $parentId): int {
		$qb = $this->connection->getQueryBuilder();

		$qb->select('c.id')
			->from($childTable, 'c')
			->leftJoin('c', $parentTable, 'p', $qb->expr()->eq('c.' . $parentId, 'p.id'))
			->where($qb->expr()->isNull('p.id'));

		if (\in_array($parentTable, ['calendars', 'calendarsubscriptions'], true)) {
			$calendarType = $parentTable === 'calendarsubscriptions' ? CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION : CalDavBackend::CALENDAR_TYPE_CALENDAR;
			$qb->andWhere($qb->expr()->eq('c.calendartype', $qb->createNamedParameter($calendarType, IQueryBuilder::PARAM_INT), IQueryBuilder::PARAM_INT));
		}

		$result = $qb->execute();

		$orphanItems = [];
		while ($row = $result->fetch()) {
			$orphanItems[] = (int) $row['id'];
		}
		$result->closeCursor();

		if (!empty($orphanItems)) {
			$qb->delete($childTable)
				->where($qb->expr()->in('id', $qb->createParameter('ids')));

			$orphanItemsBatch = array_chunk($orphanItems, 200);
			foreach ($orphanItemsBatch as $items) {
				$qb->setParameter('ids', $items, IQueryBuilder::PARAM_INT_ARRAY);
				$qb->execute();
			}
		}

		return count($orphanItems);
	}
}
