<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
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

namespace OC\Security\RateLimiting\Backend;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;

/**
 * Class MemoryCache uses the configured distributed memory cache for storing
 * rate limiting data.
 *
 * @package OC\Security\RateLimiting\Backend
 */
class MemoryCache implements IBackend {
	/** @var ICache */
	private $cache;
	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * @param ICacheFactory $cacheFactory
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(ICacheFactory $cacheFactory,
								ITimeFactory $timeFactory) {
		$this->cache = $cacheFactory->createDistributed(__CLASS__);
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @param string $methodIdentifier
	 * @param string $userIdentifier
	 * @return string
	 */
	private function hash(string $methodIdentifier,
						  string $userIdentifier): string {
		return hash('sha512', $methodIdentifier . $userIdentifier);
	}

	/**
	 * @param string $identifier
	 * @return array
	 */
	private function getExistingAttempts(string $identifier): array {
		$cachedAttempts = $this->cache->get($identifier);
		if ($cachedAttempts === null) {
			return [];
		}

		$cachedAttempts = json_decode($cachedAttempts, true);
		if(\is_array($cachedAttempts)) {
			return $cachedAttempts;
		}

		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAttempts(string $methodIdentifier,
								string $userIdentifier,
								int $seconds): int {
		$identifier = $this->hash($methodIdentifier, $userIdentifier);
		$existingAttempts = $this->getExistingAttempts($identifier);

		$count = 0;
		$currentTime = $this->timeFactory->getTime();
		/** @var array $existingAttempts */
		foreach ($existingAttempts as $attempt) {
			if(($attempt + $seconds) > $currentTime) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * {@inheritDoc}
	 */
	public function registerAttempt(string $methodIdentifier,
									string $userIdentifier,
									int $period) {
		$identifier = $this->hash($methodIdentifier, $userIdentifier);
		$existingAttempts = $this->getExistingAttempts($identifier);
		$currentTime = $this->timeFactory->getTime();

		// Unset all attempts older than $period
		foreach ($existingAttempts as $key => $attempt) {
			if(($attempt + $period) < $currentTime) {
				unset($existingAttempts[$key]);
			}
		}
		$existingAttempts = array_values($existingAttempts);

		// Store the new attempt
		$existingAttempts[] = (string)$currentTime;
		$this->cache->set($identifier, json_encode($existingAttempts));
	}
}
