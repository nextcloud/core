<?php
/**
 * @copyright Copyright (c) 2020 Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Repair\NC21;

use OC\Core\BackgroundJobs\CheckForUserCertificates;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class AddCheckForUserCertificatesJob implements IRepairStep {

	/** @var IJobList */
	protected $jobList;
	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config, IJobList $jobList) {
		$this->jobList = $jobList;
		$this->config = $config;
	}

	public function getName() {
		return 'Queue a one-time job to check for user uploaded certificates';
	}

	private function shouldRun() {
		$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0.0');

		// was added to 21.0.0.2
		return version_compare($versionFromBeforeUpdate, '21.0.0.2', '<');
	}

	public function run(IOutput $output) {
		if ($this->shouldRun()) {
			$this->config->setAppValue('files_external', 'user_certificate_scan', 'not-run-yet');
			$this->jobList->add(CheckForUserCertificates::class);
		}
	}
}
