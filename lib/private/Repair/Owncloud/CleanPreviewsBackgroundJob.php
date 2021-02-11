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

use OC\BackgroundJob\QueuedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\ILogger;
use OCP\IUserManager;

class CleanPreviewsBackgroundJob extends QueuedJob {
	/** @var IRootFolder */
	private $rootFolder;

	/** @var ILogger */
	private $logger;

	/** @var IJobList */
	private $jobList;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var IUserManager */
	private $userManager;

	/**
	 * CleanPreviewsBackgroundJob constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param ILogger $logger
	 * @param IJobList $jobList
	 * @param ITimeFactory $timeFactory
	 * @param IUserManager $userManager
	 */
	public function __construct(IRootFolder $rootFolder,
								ILogger $logger,
								IJobList $jobList,
								ITimeFactory $timeFactory,
								IUserManager $userManager) {
		$this->rootFolder = $rootFolder;
		$this->logger = $logger;
		$this->jobList = $jobList;
		$this->timeFactory = $timeFactory;
		$this->userManager = $userManager;
	}

	public function run($arguments) {
		$uid = $arguments['uid'];
		if (!$this->userManager->userExists($uid)) {
			$this->logger->info('User no longer exists, skip user ' . $uid);
			return;
		}
		$this->logger->info('Started preview cleanup for ' . $uid);
		$empty = $this->cleanupPreviews($uid);

		if (!$empty) {
			$this->jobList->add(self::class, ['uid' => $uid]);
			$this->logger->info('New preview cleanup scheduled for ' . $uid);
		} else {
			$this->logger->info('Preview cleanup done for ' . $uid);
		}
	}

	/**
	 * @param $uid
	 * @return bool
	 */
	private function cleanupPreviews($uid) {
		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
		} catch (NotFoundException $e) {
			return true;
		}

		$userRoot = $userFolder->getParent();

		try {
			/** @var Folder $thumbnailFolder */
			$thumbnailFolder = $userRoot->get('thumbnails');
		} catch (NotFoundException $e) {
			return true;
		}

		$thumbnails = $thumbnailFolder->getDirectoryListing();

		$start = $this->timeFactory->getTime();
		foreach ($thumbnails as $thumbnail) {
			try {
				$thumbnail->delete();
			} catch (NotPermittedException $e) {
				// Ignore
			}

			if (($this->timeFactory->getTime() - $start) > 15) {
				return false;
			}
		}

		try {
			$thumbnailFolder->delete();
		} catch (NotPermittedException $e) {
			// Ignore
		}

		return true;
	}
}
