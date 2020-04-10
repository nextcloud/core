<?php

declare(strict_types=1);

/**
 * @copyright 2018 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

use DateInterval;
use OC\BackgroundJob\Job;
use OCA\DAV\CalDAV\WebcalCaching\RefreshWebcalService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\ILogger;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\InvalidDataException;

class RefreshWebcalJob extends Job {

	/**
	 * @var RefreshWebcalService
	 */
	private $refreshWebcalService;

	/**
	 * @var IConfig
	 */
	private $config;

	/** @var ILogger */
	private $logger;

	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * RefreshWebcalJob constructor.
	 *
	 * @param RefreshWebcalService $refreshWebcalService
	 * @param IConfig $config
	 * @param ILogger $logger
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(RefreshWebcalService $refreshWebcalService, IConfig $config, ILogger $logger, ITimeFactory $timeFactory) {
		$this->refreshWebcalService = $refreshWebcalService;
		$this->config = $config;
		$this->logger = $logger;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * this function is called at most every hour
	 *
	 * @inheritdoc
	 */
	public function execute($jobList, ILogger $logger = null) {
		$subscription = $this->refreshWebcalService->getSubscription($this->argument['principaluri'], $this->argument['uri']);
		if (!$subscription) {
			return;
		}

		$this->fixSubscriptionRowTyping($subscription);

		// if no refresh rate was configured, just refresh once a week
		$defaultRefreshRate = $this->config->getAppValue('dav', 'calendarSubscriptionRefreshRate', 'P1W');
		$refreshRate = $subscription[RefreshWebcalService::REFRESH_RATE] ?? $defaultRefreshRate;

		$subscriptionId = $subscription['id'];

		try {
			/** @var DateInterval $dateInterval */
			$dateInterval = DateTimeParser::parseDuration($refreshRate);
		} catch(InvalidDataException $ex) {
			$this->logger->logException($ex);
			$this->logger->warning("Subscription $subscriptionId could not be refreshed, refreshrate in database is invalid");
			return;
		}

		$interval = $this->getIntervalFromDateInterval($dateInterval);
		if (($this->timeFactory->getTime() - $this->lastRun) <= $interval) {
			return;
		}

		parent::execute($jobList, $logger);
	}

	/**
	 * @param array $argument
	 */
	protected function run($argument) {
		$this->refreshWebcalService->refreshSubscription($argument['principaluri'], $argument['uri']);
	}

	/**
	 * get total number of seconds from DateInterval object
	 *
	 * @param DateInterval $interval
	 * @return int
	 */
	private function getIntervalFromDateInterval(DateInterval $interval):int {
		return $interval->s
			+ ($interval->i * 60)
			+ ($interval->h * 60 * 60)
			+ ($interval->d * 60 * 60 * 24)
			+ ($interval->m * 60 * 60 * 24 * 30)
			+ ($interval->y * 60 * 60 * 24 * 365);
	}

	/**
	 * Fixes types of rows
	 *
	 * @param array $row
	 */
	private function fixSubscriptionRowTyping(array &$row):void {
		$forceInt = [
			'id',
			'lastmodified',
			RefreshWebcalService::STRIP_ALARMS,
			RefreshWebcalService::STRIP_ATTACHMENTS,
			RefreshWebcalService::STRIP_TODOS,
		];

		foreach($forceInt as $column) {
			if (isset($row[$column])) {
				$row[$column] = (int) $row[$column];
			}
		}
	}
}
