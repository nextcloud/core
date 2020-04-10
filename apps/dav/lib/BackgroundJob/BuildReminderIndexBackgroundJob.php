<?php

declare(strict_types=1);

/**
 * @copyright 2019 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\BackgroundJob;

use OCA\DAV\CalDAV\Reminder\ReminderService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\IDBConnection;
use OCP\ILogger;

/**
 * Class BuildReminderIndexBackgroundJob
 *
 * @package OCA\DAV\BackgroundJob
 */
class BuildReminderIndexBackgroundJob extends QueuedJob {

	/** @var IDBConnection */
	private $db;

	/** @var ReminderService */
	private $reminderService;

	/** @var ILogger */
	private $logger;

	/** @var IJobList */
	private $jobList;

	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * BuildReminderIndexBackgroundJob constructor.
	 *
	 * @param IDBConnection $db
	 * @param ReminderService $reminderService
	 * @param ILogger $logger
	 * @param IJobList $jobList
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(IDBConnection $db,
								ReminderService $reminderService,
								ILogger $logger,
								IJobList $jobList,
								ITimeFactory $timeFactory) {
		parent::__construct($timeFactory);
		$this->db = $db;
		$this->reminderService = $reminderService;
		$this->logger = $logger;
		$this->jobList = $jobList;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @param $arguments
	 */
	public function run($arguments) {
		$offset = (int) $arguments['offset'];
		$stopAt = (int) $arguments['stopAt'];

		$this->logger->info('Building calendar reminder index (' . $offset .'/' . $stopAt . ')');

		$offset = $this->buildIndex($offset, $stopAt);

		if ($offset >= $stopAt) {
			$this->logger->info('Building calendar reminder index done');
		} else {
			$this->jobList->add(self::class, [
				'offset' => $offset,
				'stopAt' => $stopAt
			]);
			$this->logger->info('Scheduled a new BuildReminderIndexBackgroundJob with offset ' . $offset);
		}
	}

	/**
	 * @param int $offset
	 * @param int $stopAt
	 * @return int
	 */
	private function buildIndex(int $offset, int $stopAt):int {
		$startTime = $this->timeFactory->getTime();

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('calendarobjects')
			->where($query->expr()->lte('id', $query->createNamedParameter($stopAt)))
			->andWhere($query->expr()->gt('id', $query->createNamedParameter($offset)))
			->orderBy('id', 'ASC');

		$stmt = $query->execute();
		while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			$offset = $row['id'];
			if (is_resource($row['calendardata'])) {
				$row['calendardata'] = stream_get_contents($row['calendardata']);
			}
			$row['component'] = $row['componenttype'];

			try {
				$this->reminderService->onTouchCalendarObject('\OCA\DAV\CalDAV\CalDavBackend::createCalendarObject', $row);
			} catch(\Exception $ex) {
				$this->logger->logException($ex);
			}

			if (($this->timeFactory->getTime() - $startTime) > 15) {
				return $offset;
			}
		}

		return $stopAt;
	}
}
