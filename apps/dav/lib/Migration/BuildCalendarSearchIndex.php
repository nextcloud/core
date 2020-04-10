<?php
/**
 * @copyright 2017 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class BuildCalendarSearchIndex implements IRepairStep {

	/** @var IDBConnection */
	private $db;

	/** @var IJobList */
	private $jobList;

	/** @var IConfig */
	private $config;

	/**
	 * @param IDBConnection $db
	 * @param IJobList $jobList
	 * @param IConfig $config
	 */
	public function __construct(IDBConnection $db,
								IJobList $jobList,
								IConfig $config) {
		$this->db = $db;
		$this->jobList = $jobList;
		$this->config = $config;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return 'Registering building of calendar search index as background job';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		// only run once
		if ($this->config->getAppValue('dav', 'buildCalendarSearchIndex') === 'yes') {
			$output->info('Repair step already executed');
			return;
		}

		$query = $this->db->getQueryBuilder();
		$query->select($query->createFunction('MAX(' . $query->getColumnName('id') . ')'))
			->from('calendarobjects');
		$maxId = (int)$query->execute()->fetchColumn();

		$output->info('Add background job');
		$this->jobList->add(BuildCalendarSearchIndexBackgroundJob::class, [
			'offset' => 0,
			'stopAt' => $maxId
		]);

		// if all were done, no need to redo the repair during next upgrade
		$this->config->setAppValue('dav', 'buildCalendarSearchIndex', 'yes');
	}
}
