<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_Trashbin\BackgroundJob;

use OCA\Files_Trashbin\AppInfo\Application;
use OCA\Files_Trashbin\Expiration;
use OCA\Files_Trashbin\Helper;
use OCA\Files_Trashbin\Trashbin;
use OCP\IUser;
use OCP\IUserManager;

class ExpireTrash extends \OC\BackgroundJob\TimedJob {

	/**
	 * @var Expiration
	 */
	private $expiration;
	
	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @param IUserManager|null $userManager
	 * @param Expiration|null $expiration
	 */
	public function __construct(IUserManager $userManager = null,
								Expiration $expiration = null) {
		// Run once per 30 minutes
		$this->setInterval(60 * 30);

		if (is_null($expiration) || is_null($userManager)) {
			$this->fixDIForJobs();
		} else {
			$this->userManager = $userManager;
			$this->expiration = $expiration;
		}
	}

	protected function fixDIForJobs() {
		/** @var Application $application */
		$application = \OC::$server->query(Application::class);
		$this->userManager = \OC::$server->getUserManager();
		$this->expiration = $application->getContainer()->query('Expiration');
	}

	/**
	 * @param $argument
	 * @throws \Exception
	 */
	protected function run($argument) {
		$maxAge = $this->expiration->getMaxAgeAsTimestamp();
		if (!$maxAge) {
			return;
		}

		$this->userManager->callForSeenUsers(function (IUser $user) {
			$uid = $user->getUID();
			if (!$this->setupFS($uid)) {
				return;
			}
			$dirContent = Helper::getTrashFiles('/', $uid, 'mtime');
			Trashbin::deleteExpiredFiles($dirContent, $uid);
		});
		
		\OC_Util::tearDownFS();
	}

	/**
	 * Act on behalf on trash item owner
	 * @param string $user
	 * @return boolean
	 */
	protected function setupFS($user) {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($user);

		// Check if this user has a trashbin directory
		$view = new \OC\Files\View('/' . $user);
		if (!$view->is_dir('/files_trashbin/files')) {
			return false;
		}

		return true;
	}
}
