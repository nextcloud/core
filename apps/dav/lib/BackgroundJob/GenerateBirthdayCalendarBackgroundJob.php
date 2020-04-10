<?php
/**
 * @copyright 2017 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

use OC\BackgroundJob\QueuedJob;
use OCA\DAV\CalDAV\BirthdayService;
use OCP\IConfig;

class GenerateBirthdayCalendarBackgroundJob extends QueuedJob {

	/** @var BirthdayService */
	private $birthdayService;

	/** @var IConfig */
	private $config;

	/**
	 * GenerateAllBirthdayCalendarsBackgroundJob constructor.
	 *
	 * @param BirthdayService $birthdayService
	 * @param IConfig $config
	 */
	public function __construct(BirthdayService $birthdayService,
								IConfig $config) {
		$this->birthdayService = $birthdayService;
		$this->config = $config;
	}

	/**
	 * @param array $arguments
	 */
	public function run($arguments) {
		$userId = $arguments['userId'];
		$purgeBeforeGenerating = $arguments['purgeBeforeGenerating'] ?? false;

		// make sure admin didn't change his mind
		$isGloballyEnabled = $this->config->getAppValue('dav', 'generateBirthdayCalendar', 'yes');
		if ($isGloballyEnabled !== 'yes') {
			return;
		}

		// did the user opt out?
		$isUserEnabled = $this->config->getUserValue($userId, 'dav', 'generateBirthdayCalendar', 'yes');
		if ($isUserEnabled !== 'yes') {
			return;
		}

		if ($purgeBeforeGenerating) {
			$this->birthdayService->resetForUser($userId);
		}

		$this->birthdayService->syncUser($userId);
	}
}
