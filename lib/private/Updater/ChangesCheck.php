<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
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

namespace OC\Updater;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\ILogger;

class ChangesCheck {
	/** @var IClientService */
	protected $clientService;
	/** @var ChangesMapper */
	private $mapper;
	/** @var ILogger */
	private $logger;

	const RESPONSE_NO_CONTENT = 0;
	const RESPONSE_USE_CACHE = 1;
	const RESPONSE_HAS_CONTENT = 2;

	public function __construct(IClientService $clientService, ChangesMapper $mapper, ILogger $logger) {
		$this->clientService = $clientService;
		$this->mapper = $mapper;
		$this->logger = $logger;
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getChangesForVersion(string $version): array {
		$version = $this->normalizeVersion($version);
		$changesInfo = $this->mapper->getChanges($version);
		$changesData = json_decode($changesInfo->getData(), true);
		if (empty($changesData)) {
			throw new DoesNotExistException('Unable to decode changes info');
		}
		return $changesData;
	}

	/**
	 * @throws \Exception
	 */
	public function check(string $uri, string $version): array {
		try {
			$version = $this->normalizeVersion($version);
			$changesInfo = $this->mapper->getChanges($version);
			if($changesInfo->getLastCheck() + 1800 > time()) {
				return json_decode($changesInfo->getData(), true);
			}
		} catch (DoesNotExistException $e) {
			$changesInfo = new ChangesResult();
		}

		$response = $this->queryChangesServer($uri, $changesInfo);

		switch($this->evaluateResponse($response)) {
			case self::RESPONSE_NO_CONTENT:
				return [];
			case self::RESPONSE_USE_CACHE:
				return json_decode($changesInfo->getData(), true);
			case self::RESPONSE_HAS_CONTENT:
			default:
				$data = $this->extractData($response->getBody());
				$changesInfo->setData(json_encode($data));
				$changesInfo->setEtag($response->getHeader('Etag'));
				$this->cacheResult($changesInfo, $version);

				return $data;
		}
	}

	protected function evaluateResponse(IResponse $response): int {
		if($response->getStatusCode() === 304) {
			return self::RESPONSE_USE_CACHE;
		} else if($response->getStatusCode() === 404) {
			return self::RESPONSE_NO_CONTENT;
		} else if($response->getStatusCode() === 200) {
			return self::RESPONSE_HAS_CONTENT;
		}
		$this->logger->debug('Unexpected return code {code} from changelog server', [
			'app' => 'core',
			'code' => $response->getStatusCode(),
		]);
		return self::RESPONSE_NO_CONTENT;
	}

	protected function cacheResult(ChangesResult $entry, string $version) {
		if($entry->getVersion() === $version) {
			$this->mapper->update($entry);
		} else {
			$entry->setVersion($version);
			$this->mapper->insert($entry);
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function queryChangesServer(string $uri, ChangesResult $entry): IResponse {
		$headers = [];
		if($entry->getEtag() !== '') {
			$headers['If-None-Match'] = [$entry->getEtag()];
		}

		$entry->setLastCheck(time());
		$client = $this->clientService->newClient();
		return $client->get($uri, [
			'headers' => $headers,
		]);
	}

	protected function extractData($body):array {
		$data = [];
		if ($body) {
			$loadEntities = libxml_disable_entity_loader(true);
			$xml = @simplexml_load_string($body);
			libxml_disable_entity_loader($loadEntities);
			if ($xml !== false) {
				$data['changelogURL'] = (string)$xml->changelog['href'];
				$data['whatsNew'] = [];
				foreach($xml->whatsNew as $infoSet) {
					$data['whatsNew'][(string)$infoSet['lang']] = [
						'regular' => (array)$infoSet->regular->item,
						'admin' => (array)$infoSet->admin->item,
					];
				}
			} else {
				libxml_clear_errors();
			}
		}
		return $data;
	}

	/**
	 * returns a x.y.z form of the provided version. Extra numbers will be
	 * omitted, missing ones added as zeros.
	 */
	public function normalizeVersion(string $version): string {
		$versionNumbers = array_slice(explode('.', $version), 0, 3);
		$versionNumbers[0] = $versionNumbers[0] ?: '0'; // deal with empty input
		while(count($versionNumbers) < 3) {
			// changelog server expects x.y.z, pad 0 if it is too short
			$versionNumbers[] = 0;
		}
		return implode('.', $versionNumbers);
	}
}
