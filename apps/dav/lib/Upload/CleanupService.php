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

namespace OCA\DAV\Upload;

use OCA\DAV\BackgroundJob\UploadCleanup;
use OCP\BackgroundJob\IJobList;
use OCP\IUserSession;

class CleanupService {
	/** @var IUserSession */
	private $userSession;
	/** @var IJobList */
	private $jobList;

	public function __construct(IUserSession $userSession, IJobList $jobList) {
		$this->userSession = $userSession;
		$this->jobList = $jobList;
	}

	public function addJob(string $folder) {
		$this->jobList->add(UploadCleanup::class, ['uid' => $this->userSession->getUser()->getUID(), 'folder' => $folder]);
	}

	public function removeJob(string $folder) {
		$this->jobList->remove(UploadCleanup::class, ['uid' => $this->userSession->getUser()->getUID(), 'folder' => $folder]);
	}
}
