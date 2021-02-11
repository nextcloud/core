<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Steffen Lindner <mail@steffen-lindner.de>
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

namespace OC\App\AppStore\Fetcher;

use GuzzleHttp\Exception\ConnectException;
use OC\Files\AppData\Factory;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;

abstract class Fetcher {
	public const INVALIDATE_AFTER_SECONDS = 3600;
	public const RETRY_AFTER_FAILURE_SECONDS = 300;

	/** @var IAppData */
	protected $appData;
	/** @var IClientService */
	protected $clientService;
	/** @var ITimeFactory */
	protected $timeFactory;
	/** @var IConfig */
	protected $config;
	/** @var Ilogger */
	protected $logger;
	/** @var string */
	protected $fileName;
	/** @var string */
	protected $endpointName;
	/** @var string */
	protected $version;
	/** @var string */
	protected $channel;

	/**
	 * @param Factory $appDataFactory
	 * @param IClientService $clientService
	 * @param ITimeFactory $timeFactory
	 * @param IConfig $config
	 * @param ILogger $logger
	 */
	public function __construct(Factory $appDataFactory,
								IClientService $clientService,
								ITimeFactory $timeFactory,
								IConfig $config,
								ILogger $logger) {
		$this->appData = $appDataFactory->get('appstore');
		$this->clientService = $clientService;
		$this->timeFactory = $timeFactory;
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * Fetches the response from the server
	 *
	 * @param string $ETag
	 * @param string $content
	 *
	 * @return array
	 */
	protected function fetch($ETag, $content) {
		$appstoreenabled = $this->config->getSystemValue('appstoreenabled', true);
		if ((int)$this->config->getAppValue('settings', 'appstore-fetcher-lastFailure', '0') > time() - self::RETRY_AFTER_FAILURE_SECONDS) {
			return [];
		}

		if (!$appstoreenabled) {
			return [];
		}

		$options = [
			'timeout' => 60,
		];

		if ($ETag !== '') {
			$options['headers'] = [
				'If-None-Match' => $ETag,
			];
		}

		$client = $this->clientService->newClient();
		try {
			$response = $client->get($this->getEndpoint(), $options);
		} catch (ConnectException $e) {
			$this->config->setAppValue('settings', 'appstore-fetcher-lastFailure', (string)time());
			throw $e;
		}

		$responseJson = [];
		if ($response->getStatusCode() === Http::STATUS_NOT_MODIFIED) {
			$responseJson['data'] = json_decode($content, true);
		} else {
			$responseJson['data'] = json_decode($response->getBody(), true);
			$ETag = $response->getHeader('ETag');
		}
		$this->config->deleteAppValue('settings', 'appstore-fetcher-lastFailure');

		$responseJson['timestamp'] = $this->timeFactory->getTime();
		$responseJson['ncversion'] = $this->getVersion();
		if ($ETag !== '') {
			$responseJson['ETag'] = $ETag;
		}

		return $responseJson;
	}

	/**
	 * Returns the array with the categories on the appstore server
	 *
	 * @param bool [$allowUnstable] Allow unstable releases
	 * @return array
	 */
	public function get($allowUnstable = false) {
		$appstoreenabled = $this->config->getSystemValue('appstoreenabled', true);
		$internetavailable = $this->config->getSystemValue('has_internet_connection', true);

		if (!$appstoreenabled || !$internetavailable) {
			return [];
		}

		$rootFolder = $this->appData->getFolder('/');

		$ETag = '';
		$content = '';

		try {
			// File does already exists
			$file = $rootFolder->getFile($this->fileName);
			$jsonBlob = json_decode($file->getContent(), true);

			// Always get latests apps info if $allowUnstable
			if (!$allowUnstable && is_array($jsonBlob)) {

				// No caching when the version has been updated
				if (isset($jsonBlob['ncversion']) && $jsonBlob['ncversion'] === $this->getVersion()) {

					// If the timestamp is older than 3600 seconds request the files new
					if ((int)$jsonBlob['timestamp'] > ($this->timeFactory->getTime() - self::INVALIDATE_AFTER_SECONDS)) {
						return $jsonBlob['data'];
					}

					if (isset($jsonBlob['ETag'])) {
						$ETag = $jsonBlob['ETag'];
						$content = json_encode($jsonBlob['data']);
					}
				}
			}
		} catch (NotFoundException $e) {
			// File does not already exists
			$file = $rootFolder->newFile($this->fileName);
		}

		// Refresh the file content
		try {
			$responseJson = $this->fetch($ETag, $content, $allowUnstable);

			if (empty($responseJson)) {
				return [];
			}

			// Don't store the apps request file
			if ($allowUnstable) {
				return $responseJson['data'];
			}

			$file->putContent(json_encode($responseJson));
			return json_decode($file->getContent(), true)['data'];
		} catch (ConnectException $e) {
			$this->logger->warning('Could not connect to appstore: ' . $e->getMessage(), ['app' => 'appstoreFetcher']);
			return [];
		} catch (\Exception $e) {
			$this->logger->logException($e, ['app' => 'appstoreFetcher', 'level' => ILogger::WARN]);
			return [];
		}
	}

	/**
	 * Get the currently Nextcloud version
	 * @return string
	 */
	protected function getVersion() {
		if ($this->version === null) {
			$this->version = $this->config->getSystemValue('version', '0.0.0');
		}
		return $this->version;
	}

	/**
	 * Set the current Nextcloud version
	 * @param string $version
	 */
	public function setVersion(string $version) {
		$this->version = $version;
	}

	/**
	 * Get the currently Nextcloud update channel
	 * @return string
	 */
	protected function getChannel() {
		if ($this->channel === null) {
			$this->channel = \OC_Util::getChannel();
		}
		return $this->channel;
	}

	/**
	 * Set the current Nextcloud update channel
	 * @param string $channel
	 */
	public function setChannel(string $channel) {
		$this->channel = $channel;
	}

	protected function getEndpoint(): string {
		return $this->config->getSystemValue('appstoreurl', 'https://apps.nextcloud.com/api/v1') . '/' . $this->endpointName;
	}
}
