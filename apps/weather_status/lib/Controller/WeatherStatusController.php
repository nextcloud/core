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
namespace OCA\WeatherStatus\Controller;

use OCA\WeatherStatus\Service\WeatherStatusService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\ILogger;
use OCP\IRequest;

class WeatherStatusController extends OCSController {

	/** @var string */
	private $userId;

	/** @var ILogger */
	private $logger;

	/** @var WeatherStatusService */
	private $service;

	public function __construct(string $appName,
								IRequest $request,
								ILogger $logger,
								WeatherStatusService $service,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->logger = $logger;
		$this->service = $service;
	}

	/**
	 * @NoAdminRequired
	 *
	 * Try to use the address set in user personal settings as weather location
	 *
	 * @return DataResponse with success state and address information
	 */
	public function usePersonalAddress(): DataResponse {
		return new DataResponse($this->service->usePersonalAddress());
	}

	/**
	 * @NoAdminRequired
	 *
	 * Change the weather status mode. There are currently 2 modes:
	 * - ask the browser
	 * - use the user defined address
	 *
	 * @param int $mode New mode
	 * @return DataResponse success state
	 */
	public function setMode(int $mode): DataResponse {
		return new DataResponse($this->service->setMode($mode));
	}

	/**
	 * @NoAdminRequired
	 *
	 * Set address and resolve it to get coordinates
	 * or directly set coordinates and get address with reverse geocoding
	 *
	 * @param string|null $address Any approximative or exact address
	 * @param float|null $lat Latitude in decimal degree format
	 * @param float|null $lon Longitude in decimal degree format
	 * @return DataResponse with success state and address information
	 */
	public function setLocation(?string $address, ?float $lat, ?float $lon): DataResponse {
		$currentWeather = $this->service->setLocation($address, $lat, $lon);
		return new DataResponse($currentWeather);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Get stored user location
	 *
	 * @return DataResponse which contains coordinates, formatted address and current weather status mode
	 */
	public function getLocation(): DataResponse {
		$location = $this->service->getLocation();
		return new DataResponse($location);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Get forecast for current location
	 *
	 * @return DataResponse which contains success state and filtered forecast data
	 */
	public function getForecast(): DataResponse {
		$forecast = $this->service->getForecast();
		if (isset($forecast['success']) && $forecast['success'] === false) {
			return new DataResponse($forecast, Http::STATUS_NOT_FOUND);
		} else {
			return new DataResponse($forecast);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * Get favorites list
	 *
	 * @return DataResponse which contains the favorite list
	 */
	public function getFavorites(): DataResponse {
		return new DataResponse($this->service->getFavorites());
	}

	/**
	 * @NoAdminRequired
	 *
	 * Set favorites list
	 *
	 * @param array $favorites
	 * @return DataResponse success state
	 */
	public function setFavorites(array $favorites): DataResponse {
		return new DataResponse($this->service->setFavorites($favorites));
	}
}
