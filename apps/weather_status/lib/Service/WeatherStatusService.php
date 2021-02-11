<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Julien Veyssier
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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

namespace OCA\WeatherStatus\Service;

use OCP\IConfig;
use OCP\IL10N;
use OCP\App\IAppManager;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\PropertyDoesNotExistException;
use OCP\IUserManager;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IClient;
use OCP\ICacheFactory;
use OCP\ICache;
use OCP\ILogger;

use OCA\WeatherStatus\AppInfo\Application;

/**
 * Class WeatherStatusService
 *
 * @package OCA\WeatherStatus\Service
 */
class WeatherStatusService {
	public const MODE_BROWSER_LOCATION = 1;
	public const MODE_MANUAL_LOCATION = 2;

	/** @var IClientService */
	private $clientService;

	/** @var IClient */
	private $client;

	/** @var IConfig */
	private $config;

	/** @var IL10N */
	private $l10n;

	/** @var ILogger */
	private $logger;

	/** @var IAccountManager */
	private $accountManager;

	/** @var IUserManager */
	private $userManager;

	/** @var IAppManager */
	private $appManager;

	/** @var ICacheFactory */
	private $cacheFactory;

	/** @var ICache */
	private $cache;

	/** @var string */
	private $userId;

	/** @var string */
	private $version;

	/**
	 * WeatherStatusService constructor
	 *
	 * @param IClientService $clientService
	 * @param IConfig $config
	 * @param IL10N $l10n
	 * @param ILogger $logger
	 * @param IAccountManager $accountManager
	 * @param IUserManager $userManager
	 * @param IAppManager $appManager
	 * @param ICacheFactory $cacheFactory
	 * @param string $userId
	 */
	public function __construct(IClientService $clientService,
								IConfig $config,
								IL10N $l10n,
								ILogger $logger,
								IAccountManager $accountManager,
								IUserManager $userManager,
								IAppManager $appManager,
								ICacheFactory $cacheFactory,
								?string $userId) {
		$this->config = $config;
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->accountManager = $accountManager;
		$this->userManager = $userManager;
		$this->appManager = $appManager;
		$this->version = $appManager->getAppVersion(Application::APP_ID);
		$this->clientService = $clientService;
		$this->client = $clientService->newClient();
		if ($cacheFactory->isAvailable()) {
			$this->cache = $cacheFactory->createDistributed();
		}
	}

	/**
	 * Change the weather status mode. There are currently 2 modes:
	 * - ask the browser
	 * - use the user defined address
	 * @param int $mode New mode
	 * @return array success state
	 */
	public function setMode(int $mode): array {
		$this->config->setUserValue($this->userId, Application::APP_ID, 'mode', strval($mode));
		return ['success' => true];
	}

	/**
	 * Get favorites list
	 * @param array $favorites
	 * @return array success state
	 */
	public function getFavorites(): array {
		$favoritesJson = $this->config->getUserValue($this->userId, Application::APP_ID, 'favorites', '');
		return json_decode($favoritesJson, true) ?: [];
	}

	/**
	 * Set favorites list
	 * @param array $favorites
	 * @return array success state
	 */
	public function setFavorites(array $favorites): array {
		$this->config->setUserValue($this->userId, Application::APP_ID, 'favorites', json_encode($favorites));
		return ['success' => true];
	}

	/**
	 * Try to use the address set in user personal settings as weather location
	 *
	 * @return array with success state and address information
	 */
	public function usePersonalAddress(): array {
		$account = $this->accountManager->getAccount($this->userManager->get($this->userId));
		try {
			$address = $account->getProperty('address')->getValue();
		} catch (PropertyDoesNotExistException $e) {
			return ['success' => false];
		}
		if ($address === '') {
			return ['success' => false];
		}
		return $this->setAddress($address);
	}

	/**
	 * Set address and resolve it to get coordinates
	 * or directly set coordinates and get address with reverse geocoding
	 *
	 * @param string|null $address Any approximative or exact address
	 * @param float|null $lat Latitude in decimal degree format
	 * @param float|null $lon Longitude in decimal degree format
	 * @return array with success state and address information
	 */
	public function setLocation(?string $address, ?float $lat, ?float $lon): array {
		if (!is_null($lat) && !is_null($lon)) {
			// store coordinates
			$this->config->setUserValue($this->userId, Application::APP_ID, 'lat', strval($lat));
			$this->config->setUserValue($this->userId, Application::APP_ID, 'lon', strval($lon));
			// resolve and store formatted address
			$address = $this->resolveLocation($lat, $lon);
			$address = $address ? $address : $this->l10n->t('Unknown address');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'address', $address);
			// get and store altitude
			$altitude = $this->getAltitude($lat, $lon);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'altitude', strval($altitude));
			return [
				'address' => $address,
				'success' => true,
			];
		} elseif ($address) {
			return $this->setAddress($address);
		} else {
			return ['success' => false];
		}
	}

	/**
	 * Provide address information from coordinates
	 *
	 * @param float $lat Latitude in decimal degree format
	 * @param float $lon Longitude in decimal degree format
	 */
	private function resolveLocation(float $lat, float $lon): ?string {
		$params = [
			'lat' => number_format($lat, 2),
			'lon' => number_format($lon, 2),
			'addressdetails' => 1,
			'format' => 'json',
		];
		$url = 'https://nominatim.openstreetmap.org/reverse';
		$result = $this->requestJSON($url, $params);
		return $this->formatOsmAddress($result);
	}

	/**
	 * Get altitude from coordinates
	 *
	 * @param float $lat Latitude in decimal degree format
	 * @param float $lon Longitude in decimal degree format
	 * @return float altitude in meter
	 */
	private function getAltitude(float $lat, float $lon): float {
		$params = [
			'locations' => $lat . ',' . $lon,
		];
		$url = 'https://api.opentopodata.org/v1/srtm30m';
		$result = $this->requestJSON($url, $params);
		$altitude = 0;
		if (isset($result['results']) && is_array($result['results']) && count($result['results']) > 0
			&& is_array($result['results'][0]) && isset($result['results'][0]['elevation'])) {
			$altitude = floatval($result['results'][0]['elevation']);
		}
		return $altitude;
	}

	/**
	 * @return string Formatted address from JSON nominatim result
	 */
	private function formatOsmAddress(array $json): ?string {
		if (isset($json['address']) && isset($json['display_name'])) {
			$jsonAddr = $json['address'];
			$cityAddress = '';
			// priority : city, town, village, municipality
			if (isset($jsonAddr['city'])) {
				$cityAddress .= $jsonAddr['city'];
			} elseif (isset($jsonAddr['town'])) {
				$cityAddress .= $jsonAddr['town'];
			} elseif (isset($jsonAddr['village'])) {
				$cityAddress .= $jsonAddr['village'];
			} elseif (isset($jsonAddr['municipality'])) {
				$cityAddress .= $jsonAddr['municipality'];
			} else {
				return $json['display_name'];
			}
			// post code
			if (isset($jsonAddr['postcode'])) {
				$cityAddress .= ', ' . $jsonAddr['postcode'];
			}
			// country
			if (isset($jsonAddr['country'])) {
				$cityAddress .= ', ' . $jsonAddr['country'];
				return $cityAddress;
			} else {
				return $json['display_name'];
			}
		} elseif (isset($json['display_name'])) {
			return $json['display_name'];
		}
		return null;
	}

	/**
	 * Set address and resolve it to get coordinates
	 *
	 * @param string $address Any approximative or exact address
	 * @return array with success state and address information (coordinates and formatted address)
	 */
	public function setAddress(string $address): array {
		$addressInfo = $this->searchForAddress($address);
		if (isset($addressInfo['display_name']) && isset($addressInfo['lat']) && isset($addressInfo['lon'])) {
			$formattedAddress = $this->formatOsmAddress($addressInfo);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'address', $formattedAddress);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'lat', strval($addressInfo['lat']));
			$this->config->setUserValue($this->userId, Application::APP_ID, 'lon', strval($addressInfo['lon']));
			$this->config->setUserValue($this->userId, Application::APP_ID, 'mode', strval(self::MODE_MANUAL_LOCATION));
			// get and store altitude
			$altitude = $this->getAltitude(floatval($addressInfo['lat']), floatval($addressInfo['lon']));
			$this->config->setUserValue($this->userId, Application::APP_ID, 'altitude', strval($altitude));
			return [
				'lat' => $addressInfo['lat'],
				'lon' => $addressInfo['lon'],
				'address' => $formattedAddress,
				'success' => true,
			];
		} else {
			return ['success' => false];
		}
	}

	/**
	 * Ask nominatim information about an unformatted address
	 *
	 * @param string Unformatted address
	 * @return array Full Nominatim result for the given address
	 */
	private function searchForAddress(string $address): array {
		$params = [
			'format' => 'json',
			'addressdetails' => '1',
			'extratags' => '1',
			'namedetails' => '1',
			'limit' => '1',
		];
		$url = 'https://nominatim.openstreetmap.org/search/' . $address;
		$results = $this->requestJSON($url, $params);
		if (count($results) > 0) {
			return $results[0];
		}
		return ['error' => $this->l10n->t('No result.')];
	}

	/**
	 * Get stored user location
	 *
	 * @return array which contains coordinates, formatted address and current weather status mode
	 */
	public function getLocation(): array {
		$lat = $this->config->getUserValue($this->userId, Application::APP_ID, 'lat', '');
		$lon = $this->config->getUserValue($this->userId, Application::APP_ID, 'lon', '');
		$address = $this->config->getUserValue($this->userId, Application::APP_ID, 'address', '');
		$mode = $this->config->getUserValue($this->userId, Application::APP_ID, 'mode', self::MODE_MANUAL_LOCATION);
		return [
			'lat' => $lat,
			'lon' => $lon,
			'address' => $address,
			'mode' => intval($mode),
		];
	}

	/**
	 * Get forecast for current location
	 *
	 * @return array which contains success state and filtered forecast data
	 */
	public function getForecast(): array {
		$lat = $this->config->getUserValue($this->userId, Application::APP_ID, 'lat', '');
		$lon = $this->config->getUserValue($this->userId, Application::APP_ID, 'lon', '');
		$alt = $this->config->getUserValue($this->userId, Application::APP_ID, 'altitude', '');
		if (!is_numeric($alt)) {
			$alt = 0;
		}
		if (is_numeric($lat) && is_numeric($lon)) {
			return $this->forecastRequest(floatval($lat), floatval($lon), floatval($alt));
		} else {
			return ['success' => false];
		}
	}

	/**
	 * Actually make the request to the forecast service
	 *
	 * @param float $lat Latitude of requested forecast, in decimal degree format
	 * @param float $lon Longitude of requested forecast, in decimal degree format
	 * @param float $altitude Altitude of requested forecast, in meter
	 * @param int $nbValues Number of forecast values (hours)
	 * @return array Filtered forecast data
	 */
	private function forecastRequest(float $lat, float $lon, float $altitude, int $nbValues = 10): array {
		$params = [
			'lat' => number_format($lat, 2),
			'lon' => number_format($lon, 2),
			'altitude' => $altitude,
		];
		$url = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';
		$weather = $this->requestJSON($url, $params);
		if (isset($weather['properties']) && isset($weather['properties']['timeseries']) && is_array($weather['properties']['timeseries'])) {
			return array_slice($weather['properties']['timeseries'], 0, $nbValues);
		}
		return ['error' => $this->l10n->t('Malformed JSON data.')];
	}

	/**
	 * Make a HTTP GET request and parse JSON result.
	 * Request results are cached until the 'Expires' response header says so
	 *
	 * @param string $url Base URL to query
	 * @param array $params GET parameters
	 * @return array which contains the error message or the parsed JSON result
	 */
	private function requestJSON(string $url, array $params = []): array {
		if (isset($this->cache)) {
			$cacheKey = $url . '|' . implode(',', $params) . '|' . implode(',', array_keys($params));
			if ($this->cache->hasKey($cacheKey)) {
				return $this->cache->get($cacheKey);
			}
		}
		try {
			$options = [
				'headers' => [
					'User-Agent' => 'NextcloudWeatherStatus/' . $this->version . ' nextcloud.com'
				],
			];

			$reqUrl = $url;
			if (count($params) > 0) {
				$paramsContent = http_build_query($params);
				$reqUrl = $url . '?' . $paramsContent;
			}

			$response = $this->client->get($reqUrl, $options);
			$body = $response->getBody();
			$headers = $response->getHeaders();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Error')];
			} else {
				$json = json_decode($body, true);
				if (isset($this->cache)) {
					// default cache duration is one hour
					$cacheDuration = 60 * 60;
					if (isset($headers['Expires']) && count($headers['Expires']) > 0) {
						// if the Expires response header is set, use it to define cache duration
						$expireTs = (new \Datetime($headers['Expires'][0]))->getTimestamp();
						$nowTs = (new \Datetime())->getTimestamp();
						$duration = $expireTs - $nowTs;
						if ($duration > $cacheDuration) {
							$cacheDuration = $duration;
						}
					}
					$this->cache->set($cacheKey, $json, $cacheDuration);
				}
				return $json;
			}
		} catch (\Exception $e) {
			$this->logger->warning($url . 'API error : ' . $e, ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
