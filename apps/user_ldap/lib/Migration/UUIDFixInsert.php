<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\User_LDAP\Migration;

use OCA\User_LDAP\Mapping\GroupMapping;
use OCA\User_LDAP\Mapping\UserMapping;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class UUIDFixInsert implements IRepairStep {

	/** @var IConfig */
	protected $config;

	/** @var UserMapping */
	protected $userMapper;

	/** @var GroupMapping */
	protected $groupMapper;

	/** @var IJobList */
	protected $jobList;

	public function __construct(IConfig $config, UserMapping $userMapper, GroupMapping $groupMapper, IJobList $jobList) {
		$this->config = $config;
		$this->userMapper = $userMapper;
		$this->groupMapper = $groupMapper;
		$this->jobList = $jobList;
	}

	/**
	 * Returns the step's name
	 *
	 * @return string
	 * @since 9.1.0
	 */
	public function getName() {
		return 'Insert UUIDFix background job for user and group in batches';
	}

	/**
	 * Run repair step.
	 * Must throw exception on error.
	 *
	 * @param IOutput $output
	 * @throws \Exception in case of failure
	 * @since 9.1.0
	 */
	public function run(IOutput $output) {
		$installedVersion = $this->config->getAppValue('user_ldap', 'installed_version', '1.2.1');
		if(version_compare($installedVersion, '1.2.1') !== -1) {
			return;
		}

		foreach ([$this->userMapper, $this->groupMapper] as $mapper) {
			$offset = 0;
			$batchSize = 50;
			$jobClass = $mapper instanceof UserMapping ? UUIDFixUser::class : UUIDFixGroup::class;
			do {
				$retry = false;
				$records = $mapper->getList($offset, $batchSize);
				if(count($records) === 0){
					continue;
				}
				try {
					$this->jobList->add($jobClass, ['records' => $records]);
					$offset += $batchSize;
				} catch (\InvalidArgumentException $e) {
					if(strpos($e->getMessage(), 'Background job arguments can\'t exceed 4000') !== false) {
						$batchSize = (int)floor(count($records) * 0.8);
						$retry = true;
					}
				}
			} while (count($records) === $batchSize || $retry);
		}

	}
}
