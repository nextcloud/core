<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\UpdateNotification\Controller;

use OC\App\AppStore\Fetcher\AppFetcher;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IRequest;

class APIController extends OCSController {

	/** @var IConfig */
	protected $config;

	/** @var IAppManager */
	protected $appManager;

	/** @var AppFetcher */
	protected $appFetcher;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IAppManager $appManager
	 * @param AppFetcher $appFetcher
	 */
	public function __construct($appName,
								IRequest $request,
								IConfig $config,
								IAppManager $appManager,
								AppFetcher $appFetcher) {
		parent::__construct($appName, $request);

		$this->config = $config;
		$this->appManager = $appManager;
		$this->appFetcher = $appFetcher;
	}

	/**
	 * @param string $newVersion
	 * @return DataResponse
	 */
	public function getAppList(string $newVersion): DataResponse {
		if (!$this->config->getSystemValue('appstoreenabled', true)) {
			return new DataResponse([
				'appstore_disabled' => true,
			], Http::STATUS_NOT_FOUND);
		}

		// Get list of installed custom apps
		$installedApps = $this->appManager->getInstalledApps();
		$installedApps = array_filter($installedApps, function ($app) {
			try {
				$this->appManager->getAppPath($app);
			} catch (AppPathNotFoundException $e) {
				return false;
			}
			return !$this->appManager->isShipped($app);
		});

		if (empty($installedApps)) {
			return new DataResponse([
				'missing' => [],
				'available' => [],
			]);
		}

		$this->appFetcher->setVersion($newVersion, 'future-apps.json', false);

		// Apps available on the app store for that version
		$availableApps = array_map(function (array $app) {
			return $app['id'];
		}, $this->appFetcher->get());

		if (empty($availableApps)) {
			return new DataResponse([
				'appstore_disabled' => false,
				'already_on_latest' => false,
			], Http::STATUS_NOT_FOUND);
		}

		$missing = array_diff($installedApps, $availableApps);
		$missing = array_map([$this, 'getAppDetails'], $missing);
		sort($missing);

		$available = array_intersect($installedApps, $availableApps);
		$available = array_map([$this, 'getAppDetails'], $available);
		sort($available);

		return new DataResponse([
			'missing' => $missing,
			'available' => $available,
		]);
	}

	/**
	 * Get translated app name
	 *
	 * @param string $appId
	 * @return string[]
	 */
	protected function getAppDetails($appId): array {
		$app = $this->appManager->getAppInfo($appId);
		return [
			'appId' => $appId,
			'appName' => $app['name'] ?? $appId,
		];
	}
}
