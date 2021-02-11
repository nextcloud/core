<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Collaboration\Collaborators;

use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\Federation\ICloudIdManager;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUserSession;
use OCP\Share\IShare;

class LookupPlugin implements ISearchPlugin {

	/** @var IConfig */
	private $config;
	/** @var IClientService */
	private $clientService;
	/** @var string remote part of the current user's cloud id */
	private $currentUserRemote;
	/** @var ICloudIdManager */
	private $cloudIdManager;
	/** @var ILogger */
	private $logger;

	public function __construct(IConfig $config,
								IClientService $clientService,
								IUserSession $userSession,
								ICloudIdManager $cloudIdManager,
								ILogger $logger) {
		$this->config = $config;
		$this->clientService = $clientService;
		$this->cloudIdManager = $cloudIdManager;
		$currentUserCloudId = $userSession->getUser()->getCloudId();
		$this->currentUserRemote = $cloudIdManager->resolveCloudId($currentUserCloudId)->getRemote();
		$this->logger = $logger;
	}

	public function search($search, $limit, $offset, ISearchResult $searchResult) {
		$isGlobalScaleEnabled = $this->config->getSystemValue('gs.enabled', false);
		$isLookupServerEnabled = $this->config->getAppValue('files_sharing', 'lookupServerEnabled', 'yes') === 'yes';
		$hasInternetConnection = (bool)$this->config->getSystemValue('has_internet_connection', true);

		// if case of Global Scale we always search the lookup server
		if (!$isGlobalScaleEnabled && (!$isLookupServerEnabled || !$hasInternetConnection)) {
			return false;
		}

		$lookupServerUrl = $this->config->getSystemValue('lookup_server', 'https://lookup.nextcloud.com');
		if (empty($lookupServerUrl)) {
			return false;
		}
		$lookupServerUrl = rtrim($lookupServerUrl, '/');
		$result = [];

		try {
			$client = $this->clientService->newClient();
			$response = $client->get(
				$lookupServerUrl . '/users?search=' . urlencode($search),
				[
					'timeout' => 10,
					'connect_timeout' => 3,
				]
			);

			$body = json_decode($response->getBody(), true);

			foreach ($body as $lookup) {
				try {
					$remote = $this->cloudIdManager->resolveCloudId($lookup['federationId'])->getRemote();
				} catch (\Exception $e) {
					$this->logger->error('Can not parse federated cloud ID "' .  $lookup['federationId'] . '"');
					$this->logger->error($e->getMessage());
					continue;
				}
				if ($this->currentUserRemote === $remote) {
					continue;
				}
				$name = isset($lookup['name']['value']) ? $lookup['name']['value'] : '';
				$label = empty($name) ? $lookup['federationId'] : $name . ' (' . $lookup['federationId'] . ')';
				$result[] = [
					'label' => $label,
					'value' => [
						'shareType' => IShare::TYPE_REMOTE,
						'shareWith' => $lookup['federationId'],
					],
					'extra' => $lookup,
				];
			}
		} catch (\Exception $e) {
		}

		$type = new SearchResultType('lookup');
		$searchResult->addResultSet($type, $result, []);

		return false;
	}
}
