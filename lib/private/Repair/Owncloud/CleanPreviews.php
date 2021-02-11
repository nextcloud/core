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
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class CleanPreviews implements IRepairStep {

	/** @var IJobList */
	private $jobList;

	/** @var IUserManager */
	private $userManager;

	/** @var IConfig */
	private $config;

	/**
	 * MoveAvatars constructor.
	 *
	 * @param IJobList $jobList
	 * @param IUserManager $userManager
	 * @param IConfig $config
	 */
	public function __construct(IJobList $jobList,
								IUserManager $userManager,
								IConfig $config) {
		$this->jobList = $jobList;
		$this->userManager = $userManager;
		$this->config = $config;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return 'Add preview cleanup background jobs';
	}

	public function run(IOutput $output) {
		if (!$this->config->getAppValue('core', 'previewsCleanedUp', false)) {
			$this->userManager->callForSeenUsers(function (IUser $user) {
				$this->jobList->add(CleanPreviewsBackgroundJob::class, ['uid' => $user->getUID()]);
			});
			$this->config->setAppValue('core', 'previewsCleanedUp', '1');
		}
	}
}
