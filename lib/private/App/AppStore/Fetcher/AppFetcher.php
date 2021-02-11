<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OC\App\AppStore\Fetcher;

use OC\App\AppStore\Version\VersionParser;
use OC\App\CompareVersion;
use OC\Files\AppData\Factory;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;

class AppFetcher extends Fetcher {

	/** @var CompareVersion */
	private $compareVersion;

	/** @var bool */
	private $ignoreMaxVersion;

	/**
	 * @param Factory $appDataFactory
	 * @param IClientService $clientService
	 * @param ITimeFactory $timeFactory
	 * @param IConfig $config
	 * @param CompareVersion $compareVersion
	 * @param ILogger $logger
	 */
	public function __construct(Factory $appDataFactory,
								IClientService $clientService,
								ITimeFactory $timeFactory,
								IConfig $config,
								CompareVersion $compareVersion,
								ILogger $logger) {
		parent::__construct(
			$appDataFactory,
			$clientService,
			$timeFactory,
			$config,
			$logger
		);

		$this->fileName = 'apps.json';
		$this->endpointName = 'apps.json';
		$this->compareVersion = $compareVersion;
		$this->ignoreMaxVersion = true;
	}

	/**
	 * Only returns the latest compatible app release in the releases array
	 *
	 * @param string $ETag
	 * @param string $content
	 * @param bool [$allowUnstable] Allow unstable releases
	 *
	 * @return array
	 */
	protected function fetch($ETag, $content, $allowUnstable = false) {
		/** @var mixed[] $response */
		$response = parent::fetch($ETag, $content);

		$allowPreReleases = $allowUnstable || $this->getChannel() === 'beta' || $this->getChannel() === 'daily';
		$allowNightly = $allowUnstable || $this->getChannel() === 'daily';

		foreach ($response['data'] as $dataKey => $app) {
			$releases = [];

			// Filter all compatible releases
			foreach ($app['releases'] as $release) {
				// Exclude all nightly and pre-releases if required
				if (($allowNightly || $release['isNightly'] === false)
					&& ($allowPreReleases || strpos($release['version'], '-') === false)) {
					// Exclude all versions not compatible with the current version
					try {
						$versionParser = new VersionParser();
						$serverVersion = $versionParser->getVersion($release['rawPlatformVersionSpec']);
						$ncVersion = $this->getVersion();
						$minServerVersion = $serverVersion->getMinimumVersion();
						$maxServerVersion = $serverVersion->getMaximumVersion();
						$minFulfilled = $this->compareVersion->isCompatible($ncVersion, $minServerVersion, '>=');
						$maxFulfilled = $maxServerVersion !== '' &&
							$this->compareVersion->isCompatible($ncVersion, $maxServerVersion, '<=');
						$isPhpCompatible = true;
						if (($release['rawPhpVersionSpec'] ?? '*') !== '*') {
							$phpVersion = $versionParser->getVersion($release['rawPhpVersionSpec']);
							$minPhpVersion = $phpVersion->getMinimumVersion();
							$maxPhpVersion = $phpVersion->getMaximumVersion();
							$minPhpFulfilled = $minPhpVersion === '' || $this->compareVersion->isCompatible(
									PHP_VERSION,
									$minPhpVersion,
									'>='
								);
							$maxPhpFulfilled = $maxPhpVersion === '' || $this->compareVersion->isCompatible(
									PHP_VERSION,
									$maxPhpVersion,
									'<='
								);

							$isPhpCompatible = $minPhpFulfilled && $maxPhpFulfilled;
						}
						if ($minFulfilled && ($this->ignoreMaxVersion || $maxFulfilled) && $isPhpCompatible) {
							$releases[] = $release;
						}
					} catch (\InvalidArgumentException $e) {
						$this->logger->logException($e, ['app' => 'appstoreFetcher', 'level' => ILogger::WARN]);
					}
				}
			}

			if (empty($releases)) {
				// Remove apps that don't have a matching release
				$response['data'][$dataKey] = [];
				continue;
			}

			// Get the highest version
			$versions = [];
			foreach ($releases as $release) {
				$versions[] = $release['version'];
			}
			usort($versions, 'version_compare');
			$versions = array_reverse($versions);
			if (isset($versions[0])) {
				$highestVersion = $versions[0];
				foreach ($releases as $release) {
					if ((string)$release['version'] === (string)$highestVersion) {
						$response['data'][$dataKey]['releases'] = [$release];
						break;
					}
				}
			}
		}

		$response['data'] = array_values(array_filter($response['data']));
		return $response;
	}

	/**
	 * @param string $version
	 * @param string $fileName
	 * @param bool $ignoreMaxVersion
	 */
	public function setVersion(string $version, string $fileName = 'apps.json', bool $ignoreMaxVersion = true) {
		parent::setVersion($version);
		$this->fileName = $fileName;
		$this->ignoreMaxVersion = $ignoreMaxVersion;
	}
}
