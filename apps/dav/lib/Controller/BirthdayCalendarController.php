<?php
/**
 * @copyright 2017, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\Controller;

use OCA\DAV\BackgroundJob\GenerateBirthdayCalendarBackgroundJob;
use OCA\DAV\CalDAV\CalDavBackend;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;

class BirthdayCalendarController extends Controller {

	/**
	 * @var IDBConnection
	 */
	protected $db;

	/**
	 * @var IConfig
	 */
	protected $config;

	/**
	 * @var IUserManager
	 */
	protected $userManager;

	/**
	 * @var CalDavBackend
	 */
	protected $caldavBackend;

	/**
	 * @var IJobList
	 */
	protected $jobList;

	/**
	 * BirthdayCalendar constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IDBConnection $db
	 * @param IConfig $config
	 * @param IJobList $jobList
	 * @param IUserManager $userManager
	 * @param CalDavBackend $calDavBackend
	 */
	public function __construct($appName, IRequest $request,
								IDBConnection $db, IConfig $config,
								IJobList $jobList,
								IUserManager $userManager,
								CalDavBackend $calDavBackend) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->jobList = $jobList;
		$this->caldavBackend = $calDavBackend;
	}

	/**
	 * @return Response
	 */
	public function enable() {
		$this->config->setAppValue($this->appName, 'generateBirthdayCalendar', 'yes');

		// add background job for each user
		$this->userManager->callForSeenUsers(function (IUser $user) {
			$this->jobList->add(GenerateBirthdayCalendarBackgroundJob::class, [
				'userId' => $user->getUID(),
			]);
		});

		return new JSONResponse([]);
	}

	/**
	 * @return Response
	 */
	public function disable() {
		$this->config->setAppValue($this->appName, 'generateBirthdayCalendar', 'no');

		$this->jobList->remove(GenerateBirthdayCalendarBackgroundJob::class);
		$this->caldavBackend->deleteAllBirthdayCalendars();

		return new JSONResponse([]);
	}
}
