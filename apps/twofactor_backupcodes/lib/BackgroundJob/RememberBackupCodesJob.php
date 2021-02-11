<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\IUserManager;
use OCP\Notification\IManager;

class RememberBackupCodesJob extends TimedJob {

	/** @var IRegistry */
	private $registry;

	/** @var IUserManager */
	private $userManager;

	/** @var IManager */
	private $notificationManager;

	/** @var IJobList */
	private $jobList;

	public function __construct(IRegistry $registry,
								IUserManager $userManager,
								ITimeFactory $timeFactory,
								IManager $notificationManager,
								IJobList $jobList) {
		parent::__construct($timeFactory);
		$this->registry = $registry;
		$this->userManager = $userManager;
		$this->time = $timeFactory;
		$this->notificationManager = $notificationManager;
		$this->jobList = $jobList;

		$this->setInterval(60 * 60 * 24 * 14);
	}

	protected function run($argument) {
		$uid = $argument['uid'];
		$user = $this->userManager->get($uid);

		if ($user === null) {
			// We can't run with an invalid user
			return;
		}

		$providers = $this->registry->getProviderStates($user);
		$state2fa = array_reduce($providers, function (bool $carry, bool $state) {
			return $carry || $state;
		}, false);

		/*
		 * If no provider is active or if the backup codes are already generate
		 * we can remove the job
		 */
		if ($state2fa === false || (isset($providers['backup_codes']) && $providers['backup_codes'] === true)) {
			// Backup codes already generated lets remove this job
			$this->jobList->remove(self::class, $argument);
			return;
		}

		$date = new \DateTime();
		$date->setTimestamp($this->time->getTime());

		$notification = $this->notificationManager->createNotification();
		$notification->setApp('twofactor_backupcodes')
			->setUser($user->getUID())
			->setDateTime($date)
			->setObject('create', 'codes')
			->setSubject('create_backupcodes');
		$this->notificationManager->notify($notification);
	}
}
