<?php
/**
 * @copyright 2017 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

use OC\BackgroundJob\QueuedJob;
use OCA\DAV\CalDAV\CalDavBackend;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\ILogger;

class BuildCalendarSearchIndexBackgroundJob extends QueuedJob {

	/** @var IDBConnection */
	private $db;

	/** @var CalDavBackend */
	private $calDavBackend;

	/** @var ILogger */
	private $logger;

	/** @var IJobList */
	private $jobList;

	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * @param IDBConnection $db
	 * @param CalDavBackend $calDavBackend
	 * @param ILogger $logger
	 * @param IJobList $jobList
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(IDBConnection $db,
								CalDavBackend $calDavBackend,
								ILogger $logger,
								IJobList $jobList,
								ITimeFactory $timeFactory) {
		$this->db = $db;
		$this->calDavBackend = $calDavBackend;
		$this->logger = $logger;
		$this->jobList = $jobList;
		$this->timeFactory = $timeFactory;
	}

	public function run($arguments) {
		$offset = (int) $arguments['offset'];
		$stopAt = (int) $arguments['stopAt'];

		$this->logger->info('Building calendar index (' . $offset .'/' . $stopAt . ')');

		$startTime = $this->timeFactory->getTime();
		while (($this->timeFactory->getTime() - $startTime) < 15) {
			$offset = $this->buildIndex($offset, $stopAt);
			if ($offset >= $stopAt) {
				break;
			}
		}

		if ($offset >= $stopAt) {
			$this->logger->info('Building calendar index done');
		} else {
			$this->jobList->add(self::class, [
				'offset' => $offset,
				'stopAt' => $stopAt
			]);
			$this->logger->info('New building calendar index job scheduled with offset ' . $offset);
		}
	}

	/**
	 * @param int $offset
	 * @param int $stopAt
	 * @return int
	 */
	private function buildIndex(int $offset, int $stopAt): int {
		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'calendarid', 'uri', 'calendardata'])
			->from('calendarobjects')
			->where($query->expr()->lte('id', $query->createNamedParameter($stopAt)))
			->andWhere($query->expr()->gt('id', $query->createNamedParameter($offset)))
			->orderBy('id', 'ASC')
			->setMaxResults(500);

		$result = $query->execute();
		while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
			$offset = $row['id'];

			$calendarData = $row['calendardata'];
			if (is_resource($calendarData)) {
				$calendarData = stream_get_contents($calendarData);
			}

			$this->calDavBackend->updateProperties($row['calendarid'], $row['uri'], $calendarData);
		}

		return $offset;
	}
}
