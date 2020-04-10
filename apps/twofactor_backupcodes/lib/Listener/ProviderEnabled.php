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

namespace OCA\TwoFactorBackupCodes\Listener;

use OCA\TwoFactorBackupCodes\BackgroundJob\RememberBackupCodesJob;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\Authentication\TwoFactorAuth\RegistryEvent;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class ProviderEnabled implements IEventListener {

	/** @var IRegistry */
	private $registry;

	/** @var IJobList */
	private $jobList;

	public function __construct(IRegistry $registry,
								IJobList $jobList) {
		$this->registry = $registry;
		$this->jobList = $jobList;
	}

	public function handle(Event $event): void {
		if (!($event instanceof RegistryEvent)) {
			return;
		}

		$providers = $this->registry->getProviderStates($event->getUser());
		if (isset($providers['backup_codes']) && $providers['backup_codes'] === true) {
			// Backup codes already generated nothing to do here
			return;
		}

		$this->jobList->add(RememberBackupCodesJob::class, ['uid' => $event->getUser()->getUID()]);
	}

}
