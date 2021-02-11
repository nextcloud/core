<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OC;

use Closure;
use OC\AppFramework\Bootstrap\Coordinator;
use OCP\AppFramework\QueryException;
use OCP\AppFramework\Services\InitialStateProvider;
use OCP\IInitialStateService;
use OCP\ILogger;
use OCP\IServerContainer;

class InitialStateService implements IInitialStateService {

	/** @var ILogger */
	private $logger;

	/** @var string[][] */
	private $states = [];

	/** @var Closure[][] */
	private $lazyStates = [];

	/** @var Coordinator */
	private $bootstrapCoordinator;

	/** @var IServerContainer */
	private $container;

	public function __construct(ILogger $logger, Coordinator $bootstrapCoordinator, IServerContainer $container) {
		$this->logger = $logger;
		$this->bootstrapCoordinator = $bootstrapCoordinator;
		$this->container = $container;
	}

	public function provideInitialState(string $appName, string $key, $data): void {
		// Scalars and JsonSerializable are fine
		if (is_scalar($data) || $data instanceof \JsonSerializable || is_array($data)) {
			if (!isset($this->states[$appName])) {
				$this->states[$appName] = [];
			}
			$this->states[$appName][$key] = json_encode($data);
			return;
		}

		$this->logger->warning('Invalid data provided to provideInitialState by ' . $appName);
	}

	public function provideLazyInitialState(string $appName, string $key, Closure $closure): void {
		if (!isset($this->lazyStates[$appName])) {
			$this->lazyStates[$appName] = [];
		}
		$this->lazyStates[$appName][$key] = $closure;
	}

	/**
	 * Invoke all callbacks to populate the `states` property
	 */
	private function invokeLazyStateCallbacks(): void {
		foreach ($this->lazyStates as $app => $lazyStates) {
			foreach ($lazyStates as $key => $lazyState) {
				$startTime = microtime(true);
				$this->provideInitialState($app, $key, $lazyState());
				$endTime = microtime(true);
				$duration = $endTime - $startTime;
				if ($duration > 1) {
					$this->logger->warning('Lazy initial state provider for {key} took {duration} seconds.', [
						'app' => $app,
						'key' => $key,
						'duration' => round($duration, 2),
					]);
				}
			}
		}
		$this->lazyStates = [];
	}

	/**
	 * Load the lazy states via the IBootstrap mechanism
	 */
	private function loadLazyStates(): void {
		$context = $this->bootstrapCoordinator->getRegistrationContext();

		if ($context === null) {
			// To early, nothing to do yet
			return;
		}

		$initialStates = $context->getInitialStates();
		foreach ($initialStates as $initialState) {
			try {
				$provider = $this->container->query($initialState['class']);
			} catch (QueryException $e) {
				// Log an continue. We can be fault tolerant here.
				$this->logger->logException($e, [
					'message' => 'Could not load initial state provider dynamically: ' . $e->getMessage(),
					'level' => ILogger::ERROR,
					'app' => $initialState['appId'],
				]);
				continue;
			}

			if (!($provider instanceof InitialStateProvider)) {
				// Log an continue. We can be fault tolerant here.
				$this->logger->error('Initial state provider is not an InitialStateProvider instance: ' . $initialState['class'], [
					'app' => $initialState['appId'],
				]);
			}

			$this->provideInitialState($initialState['appId'], $provider->getKey(), $provider);
		}
	}

	public function getInitialStates(): array {
		$this->invokeLazyStateCallbacks();
		$this->loadLazyStates();

		$appStates = [];
		foreach ($this->states as $app => $states) {
			foreach ($states as $key => $value) {
				$appStates["$app-$key"] = $value;
			}
		}
		return $appStates;
	}
}
