<?php
/**
 * @copyright 2019 Georg Ehrke <oc.list@georgehrke.com>
 *
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

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\IUser;
use OCP\IUserManager;

class RegisterRegenerateBirthdayCalendars extends QueuedJob {

	/** @var IUserManager */
	private $userManager;

	/** @var IJobList */
	private $jobList;

	/**
	 * RegisterRegenerateBirthdayCalendars constructor.
	 *
	 * @param ITimeFactory $time
	 * @param IUserManager $userManager
	 * @param IJobList $jobList
	 */
	public function __construct(ITimeFactory $time,
								IUserManager $userManager,
								IJobList $jobList) {
		parent::__construct($time);
		$this->userManager = $userManager;
		$this->jobList = $jobList;
	}

	/**
	 * @inheritDoc
	 */
	public function run($argument) {
		$this->userManager->callForSeenUsers(function (IUser $user) {
			$this->jobList->add(GenerateBirthdayCalendarBackgroundJob::class, [
				'userId' => $user->getUID(),
				'purgeBeforeGenerating' => true
			]);
		});
	}

}
