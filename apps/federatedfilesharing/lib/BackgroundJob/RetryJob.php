<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\FederatedFileSharing\BackgroundJob;

use OCA\FederatedFileSharing\Notifications;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;
use OCP\ILogger;

/**
 * Class RetryJob
 *
 * Background job to re-send update of federated re-shares to the remote server in
 * case the server was not available on the first try
 *
 * @package OCA\FederatedFileSharing\BackgroundJob
 */
class RetryJob extends Job {

	/** @var  bool */
	private $retainJob = true;

	/** @var Notifications */
	private $notifications;

	/** @var int max number of attempts to send the request */
	private $maxTry = 20;

	/** @var int how much time should be between two tries (10 minutes) */
	private $interval = 600;


	public function __construct(Notifications $notifications,
								ITimeFactory $timeFactory) {
		parent::__construct($timeFactory);
		$this->notifications = $notifications;
	}

	/**
	 * run the job, then remove it from the jobList
	 *
	 * @param IJobList $jobList
	 * @param ILogger|null $logger
	 */
	public function execute(IJobList $jobList, ILogger $logger = null) {
		if ($this->shouldRun($this->argument)) {
			parent::execute($jobList, $logger);
			$jobList->remove($this, $this->argument);
			if ($this->retainJob) {
				$this->reAddJob($jobList, $this->argument);
			}
		}
	}

	protected function run($argument) {
		$remote = $argument['remote'];
		$remoteId = $argument['remoteId'];
		$token = $argument['token'];
		$action = $argument['action'];
		$data = json_decode($argument['data'], true);
		$try = (int)$argument['try'] + 1;

		$result = $this->notifications->sendUpdateToRemote($remote, $remoteId, $token, $action, $data, $try);

		if ($result === true || $try > $this->maxTry) {
			$this->retainJob = false;
		}
	}

	/**
	 * re-add background job with new arguments
	 *
	 * @param IJobList $jobList
	 * @param array $argument
	 */
	protected function reAddJob(IJobList $jobList, array $argument) {
		$jobList->add(RetryJob::class,
			[
				'remote' => $argument['remote'],
				'remoteId' => $argument['remoteId'],
				'token' => $argument['token'],
				'data' => $argument['data'],
				'action' => $argument['action'],
				'try' => (int)$argument['try'] + 1,
				'lastRun' => $this->time->getTime()
			]
		);
	}

	/**
	 * test if it is time for the next run
	 *
	 * @param array $argument
	 * @return bool
	 */
	protected function shouldRun(array $argument) {
		$lastRun = (int)$argument['lastRun'];
		return (($this->time->getTime() - $lastRun) > $this->interval);
	}
}
