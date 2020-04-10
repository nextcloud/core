<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Bjoern Schiessle <bjoern@schiessle.org>
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\LookupServerConnector;

use OCA\LookupServerConnector\BackgroundJobs\RetryJob;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IUser;

/**
 * Class UpdateLookupServer
 *
 * @package OCA\LookupServerConnector
 */
class UpdateLookupServer {
	/** @var IConfig */
	private $config;
	/** @var IJobList */
	private $jobList;

	/**
	 * @param IJobList $jobList
	 * @param IConfig $config
	 */
	public function __construct(IJobList $jobList,
								IConfig $config) {
		$this->config = $config;
		$this->jobList = $jobList;
	}

	/**
	 * @param IUser $user
	 */
	public function userUpdated(IUser $user): void {
		if (!$this->shouldUpdateLookupServer()) {
			return;
		}

		// Reset retry counter
		$this->config->deleteUserValue(
			$user->getUID(),
			'lookup_server_connector',
			'update_retries'
		);
		$this->jobList->add(RetryJob::class, ['userId' => $user->getUID()]);
	}

	/**
	 * check if we should update the lookup server, we only do it if
	 *
	 * + we have an internet connection
	 * + the lookup server update was not disabled by the admin
	 * + we have a valid lookup server URL
	 *
	 * @return bool
	 */
	private function shouldUpdateLookupServer(): bool {
		return $this->config->getSystemValueBool('has_internet_connection', true) === true &&
			$this->config->getAppValue('files_sharing', 'lookupServerUploadEnabled', 'yes') === 'yes' &&
			$this->config->getSystemValueString('lookup_server', 'https://lookup.nextcloud.com') !== '';
	}

}
