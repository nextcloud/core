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

namespace OCA\DAV\Migration;

use OCA\DAV\BackgroundJob\UploadCleanup;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ChunkCleanup implements IRepairStep {

	/** @var IConfig */
	private $config;
	/** @var IUserManager */
	private $userManager;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IJobList */
	private $jobList;

	public function __construct(IConfig $config,
								IUserManager $userManager,
								IRootFolder $rootFolder,
								IJobList $jobList) {
		$this->config = $config;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->jobList = $jobList;
	}

	public function getName(): string {
		return 'Chunk cleanup scheduler';
	}

	public function run(IOutput $output) {
		// If we already ran this onec there is no need to run it again
		if ($this->config->getAppValue('dav', 'chunks_migrated', '0') === '1') {
			$output->info('Cleanup not required');
		}

		$output->startProgress();
		// Loop over all seen users
		$this->userManager->callForSeenUsers(function (IUser $user) use ($output) {
			try {
				$userFolder = $this->rootFolder->getUserFolder($user->getUID());
				$userRoot = $userFolder->getParent();
				/** @var Folder $uploadFolder */
				$uploadFolder = $userRoot->get('uploads');
			} catch (NotFoundException $e) {
				// No folder so skipping
				return;
			}

			// Insert a cleanup job for each folder we find
			$uploads = $uploadFolder->getDirectoryListing();
			foreach ($uploads as $upload) {
				$this->jobList->add(UploadCleanup::class, ['uid' => $user->getUID(), 'folder' => $upload->getName()]);
			}
			$output->advance();
		});
		$output->finishProgress();


		$this->config->setAppValue('dav', 'chunks_migrated', '1');
	}

}
