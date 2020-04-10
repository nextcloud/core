<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCA\TwoFactorBackupCodes\BackgroundJob;

use OC\Authentication\TwoFactorAuth\Manager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\IUser;
use OCP\IUserManager;

class CheckBackupCodes extends QueuedJob {

	/** @var IUserManager */
	private $userManager;

	/** @var IJobList */
	private $jobList;

	/** @var Manager */
	private $registry;

	/** @var Manager */
	private $twofactorManager;

	public function __construct(ITimeFactory $timeFactory, IUserManager $userManager, IJobList $jobList, Manager $twofactorManager, IRegistry $registry) {
		parent::__construct($timeFactory);
		$this->userManager = $userManager;
		$this->jobList = $jobList;
		$this->twofactorManager = $twofactorManager;
		$this->registry = $registry;
	}

	protected function run($argument) {
		$this->userManager->callForSeenUsers(function (IUser $user) {
			$providers = $this->registry->getProviderStates($user);
			$isTwoFactorAuthenticated = $this->twofactorManager->isTwoFactorAuthenticated($user);

			if ($isTwoFactorAuthenticated && isset($providers['backup_codes']) && $providers['backup_codes'] === false) {
				$this->jobList->add(RememberBackupCodesJob::class, ['uid' => $user->getUID()]);
			}
		});
	}

}
