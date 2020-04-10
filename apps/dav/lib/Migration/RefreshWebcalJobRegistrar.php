<?php

declare(strict_types=1);

/**
 * @copyright 2018 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

use OCA\DAV\BackgroundJob\RefreshWebcalJob;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RefreshWebcalJobRegistrar implements IRepairStep {

	/** @var IDBConnection */
	private $connection;

	/** @var IJobList */
	private $jobList;

	/**
	 * FixBirthdayCalendarComponent constructor.
	 *
	 * @param IDBConnection $connection
	 * @param IJobList $jobList
	 */
	public function __construct(IDBConnection $connection, IJobList $jobList) {
		$this->connection = $connection;
		$this->jobList = $jobList;
	}

	/**
	 * @inheritdoc
	 */
	public function getName() {
		return 'Registering background jobs to update cache for webcal calendars';
	}

	/**
	 * @inheritdoc
	 */
	public function run(IOutput $output) {
		$query = $this->connection->getQueryBuilder();
		$query->select(['principaluri', 'uri'])
			->from('calendarsubscriptions');
		$stmt = $query->execute();

		$count = 0;
		while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			$args = [
				'principaluri' => $row['principaluri'],
				'uri' => $row['uri'],
			];

			if (!$this->jobList->has(RefreshWebcalJob::class, $args)) {
				$this->jobList->add(RefreshWebcalJob::class, $args);
				$count++;
			}
		}

		$output->info("Added $count background jobs to update webcal calendars");
	}
}
