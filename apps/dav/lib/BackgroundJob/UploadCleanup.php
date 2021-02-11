<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
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

use OC\User\NoUserException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;

class UploadCleanup extends TimedJob {

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IJobList */
	private $jobList;

	public function __construct(ITimeFactory $time, IRootFolder $rootFolder, IJobList $jobList) {
		parent::__construct($time);
		$this->rootFolder = $rootFolder;
		$this->jobList = $jobList;

		// Run once a day
		$this->setInterval(60 * 60 * 24);
	}

	protected function run($argument) {
		$uid = $argument['uid'];
		$folder = $argument['folder'];

		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
			$userRoot = $userFolder->getParent();
			/** @var Folder $uploads */
			$uploads = $userRoot->get('uploads');
			/** @var Folder $uploadFolder */
			$uploadFolder = $uploads->get($folder);
		} catch (NotFoundException | NoUserException $e) {
			$this->jobList->remove(self::class, $argument);
			return;
		}

		$files = $uploadFolder->getDirectoryListing();

		// Remove if all files have an mtime of more than a day
		$time = $this->time->getTime() - 60 * 60 * 24;

		// The folder has to be more than a day old
		$initial = $uploadFolder->getMTime() < $time;

		$expire = array_reduce($files, function (bool $carry, File $file) use ($time) {
			return $carry && $file->getMTime() < $time;
		}, $initial);

		if ($expire) {
			$uploadFolder->delete();
			$this->jobList->remove(self::class, $argument);
		}
	}
}
