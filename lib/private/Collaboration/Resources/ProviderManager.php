<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Daniel Kesselberg <mail@danielkesselberg.de>
 *
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

namespace OC\Collaboration\Resources;

use OCP\AppFramework\QueryException;
use OCP\Collaboration\Resources\IProvider;
use OCP\Collaboration\Resources\IProviderManager;
use OCP\ILogger;
use OCP\IServerContainer;

class ProviderManager implements IProviderManager {

	/** @var string[] */
	protected $providers = [];

	/** @var IProvider[] */
	protected $providerInstances = [];

	/** @var IServerContainer */
	protected $serverContainer;

	/** @var ILogger */
	protected $logger;

	public function __construct(IServerContainer $serverContainer, ILogger $logger) {
		$this->serverContainer = $serverContainer;
		$this->logger = $logger;
	}

	public function getResourceProviders(): array {
		if ($this->providers !== []) {
			foreach ($this->providers as $provider) {
				try {
					$this->providerInstances[] = $this->serverContainer->query($provider);
				} catch (QueryException $e) {
					$this->logger->logException($e, [
						'message' => "Could not query resource provider $provider: " . $e->getMessage()
					]);
				}
			}
			$this->providers = [];
		}

		return $this->providerInstances;
	}

	public function registerResourceProvider(string $provider): void {
		$this->providers[] = $provider;
	}
}
