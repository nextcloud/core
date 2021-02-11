<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Bjoern Schiessle <bjoern@schiessle.org>
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\LookupServerConnector\BackgroundJobs;

use OC\Security\IdentityProof\Signer;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;

class RetryJob extends Job {
	/** @var IClientService */
	private $clientService;
	/** @var string */
	private $lookupServer;
	/** @var IConfig */
	private $config;
	/** @var IUserManager */
	private $userManager;
	/** @var IAccountManager */
	private $accountManager;
	/** @var Signer */
	private $signer;
	/** @var int */
	protected $retries = 0;
	/** @var bool */
	protected $retainJob = false;

	/**
	 * @param ITimeFactory $time
	 * @param IClientService $clientService
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param IAccountManager $accountManager
	 * @param Signer $signer
	 */
	public function __construct(ITimeFactory $time,
								IClientService $clientService,
								IConfig $config,
								IUserManager $userManager,
								IAccountManager $accountManager,
								Signer $signer) {
		parent::__construct($time);
		$this->clientService = $clientService;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->accountManager = $accountManager;
		$this->signer = $signer;

		$this->lookupServer = $config->getSystemValue('lookup_server', 'https://lookup.nextcloud.com');
		if (!empty($this->lookupServer)) {
			$this->lookupServer = rtrim($this->lookupServer, '/');
			$this->lookupServer .= '/users';
		}
	}

	/**
	 * run the job, then remove it from the jobList
	 *
	 * @param IJobList $jobList
	 * @param ILogger|null $logger
	 */
	public function execute(IJobList $jobList, ILogger $logger = null): void {
		if (!isset($this->argument['userId'])) {
			// Old background job without user id, just drop it.
			$jobList->remove($this, $this->argument);
			return;
		}

		$this->retries = (int) $this->config->getUserValue($this->argument['userId'], 'lookup_server_connector', 'update_retries', 0);

		if ($this->shouldRemoveBackgroundJob()) {
			$jobList->remove($this, $this->argument);
			return;
		}

		if ($this->shouldRun()) {
			parent::execute($jobList, $logger);
			if (!$this->retainJob) {
				$jobList->remove($this, $this->argument);
			}
		}
	}

	/**
	 * Check if we should kill the background job:
	 *
	 * - internet connection is disabled
	 * - no valid lookup server URL given
	 * - lookup server was disabled by the admin
	 * - max retries are reached (set to 5)
	 *
	 * @return bool
	 */
	protected function shouldRemoveBackgroundJob(): bool {
		return $this->config->getSystemValueBool('has_internet_connection', true) === false ||
			$this->config->getSystemValueString('lookup_server', 'https://lookup.nextcloud.com') === '' ||
			$this->config->getAppValue('files_sharing', 'lookupServerUploadEnabled', 'yes') !== 'yes' ||
			$this->retries >= 5;
	}

	protected function shouldRun(): bool {
		$delay = 100 * 6 ** $this->retries;
		return ($this->time->getTime() - $this->lastRun) > $delay;
	}

	protected function run($argument): void {
		$user = $this->userManager->get($this->argument['userId']);
		if (!$user instanceof IUser) {
			// User does not exist anymore
			return;
		}

		$data = $this->getUserAccountData($user);
		$signedData = $this->signer->sign('lookupserver', $data, $user);
		$client = $this->clientService->newClient();

		try {
			if (count($data) === 1) {
				// No public data, just the federation Id
				$client->delete($this->lookupServer,
					[
						'body' => json_encode($signedData),
						'timeout' => 10,
						'connect_timeout' => 3,
					]
				);
			} else {
				$client->post($this->lookupServer,
					[
						'body' => json_encode($signedData),
						'timeout' => 10,
						'connect_timeout' => 3,
					]
				);
			}

			// Reset retry counter
			$this->config->deleteUserValue(
				$user->getUID(),
				'lookup_server_connector',
				'update_retries'
			);
		} catch (\Exception $e) {
			// An error occurred, retry later
			$this->retainJob = true;
			$this->config->setUserValue(
				$user->getUID(),
				'lookup_server_connector',
				'update_retries',
				$this->retries + 1
			);
		}
	}

	protected function getUserAccountData(IUser $user): array {
		$account = $this->accountManager->getAccount($user);

		$publicData = [];
		foreach ($account->getProperties() as $property) {
			if ($property->getScope() === IAccountManager::VISIBILITY_PUBLIC) {
				$publicData[$property->getName()] = $property->getValue();
			}
		}

		$data = ['federationId' => $user->getCloudId()];
		if (!empty($publicData)) {
			$data['name'] = $publicData[IAccountManager::PROPERTY_DISPLAYNAME]['value'] ?? '';
			$data['email'] = $publicData[IAccountManager::PROPERTY_EMAIL]['value'] ?? '';
			$data['address'] = $publicData[IAccountManager::PROPERTY_ADDRESS]['value'] ?? '';
			$data['website'] = $publicData[IAccountManager::PROPERTY_WEBSITE]['value'] ?? '';
			$data['twitter'] = $publicData[IAccountManager::PROPERTY_TWITTER]['value'] ?? '';
			$data['phone'] = $publicData[IAccountManager::PROPERTY_PHONE]['value'] ?? '';
			$data['twitter_signature'] = $publicData[IAccountManager::PROPERTY_TWITTER]['signature'] ?? '';
			$data['website_signature'] = $publicData[IAccountManager::PROPERTY_WEBSITE]['signature'] ?? '';
			$data['verificationStatus'] = [
				IAccountManager::PROPERTY_WEBSITE => $publicData[IAccountManager::PROPERTY_WEBSITE]['verified'] ?? '',
				IAccountManager::PROPERTY_TWITTER => $publicData[IAccountManager::PROPERTY_TWITTER]['verified'] ?? '',
			];
		}

		return $data;
	}
}
