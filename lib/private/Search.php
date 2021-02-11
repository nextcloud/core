<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andrew Brown <andrew@casabrown.com>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC;

use OCP\ISearch;
use OCP\Search\PagedProvider;
use OCP\Search\Provider;

/**
 * Provide an interface to all search providers
 */
class Search implements ISearch {
	/** @var Provider[] */
	private $providers = [];
	private $registeredProviders = [];

	/**
	 * Search all providers for $query
	 * @param string $query
	 * @param string[] $inApps optionally limit results to the given apps
	 * @param int $page pages start at page 1
	 * @param int $size, 0 = all
	 * @return array An array of OC\Search\Result's
	 */
	public function searchPaged($query, array $inApps = [], $page = 1, $size = 30) {
		$this->initProviders();
		$results = [];
		foreach ($this->providers as $provider) {
			if (! $provider->providesResultsFor($inApps)) {
				continue;
			}
			if ($provider instanceof PagedProvider) {
				$results = array_merge($results, $provider->searchPaged($query, $page, $size));
			} elseif ($provider instanceof Provider) {
				$providerResults = $provider->search($query);
				if ($size > 0) {
					$slicedResults = array_slice($providerResults, ($page - 1) * $size, $size);
					$results = array_merge($results, $slicedResults);
				} else {
					$results = array_merge($results, $providerResults);
				}
			} else {
				\OC::$server->getLogger()->warning('Ignoring Unknown search provider', ['provider' => $provider]);
			}
		}
		return $results;
	}

	/**
	 * Remove all registered search providers
	 */
	public function clearProviders() {
		$this->providers = [];
		$this->registeredProviders = [];
	}

	/**
	 * Remove one existing search provider
	 * @param string $provider class name of a OC\Search\Provider
	 */
	public function removeProvider($provider) {
		$this->registeredProviders = array_filter(
			$this->registeredProviders,
			function ($element) use ($provider) {
				return ($element['class'] != $provider);
			}
		);
		// force regeneration of providers on next search
		$this->providers = [];
	}

	/**
	 * Register a new search provider to search with
	 * @param string $class class name of a OC\Search\Provider
	 * @param array $options optional
	 */
	public function registerProvider($class, array $options = []) {
		$this->registeredProviders[] = ['class' => $class, 'options' => $options];
	}

	/**
	 * Create instances of all the registered search providers
	 */
	private function initProviders() {
		if (! empty($this->providers)) {
			return;
		}
		foreach ($this->registeredProviders as $provider) {
			$class = $provider['class'];
			$options = $provider['options'];
			$this->providers[] = new $class($options);
		}
	}
}
