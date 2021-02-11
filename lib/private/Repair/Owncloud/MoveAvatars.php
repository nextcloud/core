<?php
/**
 * @copyright 2016 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
namespace OC\Repair\Owncloud;

use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class MoveAvatars implements IRepairStep {

	/** @var IJobList */
	private $jobList;

	/** @var IConfig */
	private $config;

	/**
	 * MoveAvatars constructor.
	 *
	 * @param IJobList $jobList
	 * @param IConfig $config
	 */
	public function __construct(IJobList $jobList,
								IConfig $config) {
		$this->jobList = $jobList;
		$this->config = $config;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return 'Add move avatar background job';
	}

	public function run(IOutput $output) {
		// only run once
		if ($this->config->getAppValue('core', 'moveavatarsdone') === 'yes') {
			$output->info('Repair step already executed');
			return;
		}
		if ($this->config->getSystemValue('enable_avatars', true) === false) {
			$output->info('Avatars are disabled');
		} else {
			$output->info('Add background job');
			$this->jobList->add(MoveAvatarsBackgroundJob::class);
			// if all were done, no need to redo the repair during next upgrade
			$this->config->setAppValue('core', 'moveavatarsdone', 'yes');
		}
	}
}
