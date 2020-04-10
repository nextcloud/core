<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

use OC\AppFramework\App;
use OC\AppFramework\DependencyInjection\DIContainer;
use OC\AppFramework\Utility\SimpleContainer;
use OCP\AppFramework\QueryException;

/**
 * Class ServerContainer
 *
 * @package OC
 */
class ServerContainer extends SimpleContainer {
	/** @var DIContainer[] */
	protected $appContainers;

	/** @var string[] */
	protected $hasNoAppContainer;

	/** @var string[] */
	protected $namespaces;

	/**
	 * ServerContainer constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->appContainers = [];
		$this->namespaces = [];
		$this->hasNoAppContainer = [];
	}

	/**
	 * @param string $appName
	 * @param string $appNamespace
	 */
	public function registerNamespace(string $appName, string $appNamespace): void {
		// Cut of OCA\ and lowercase
		$appNamespace = strtolower(substr($appNamespace, strrpos($appNamespace, '\\') + 1));
		$this->namespaces[$appNamespace] = $appName;
	}

	/**
	 * @param string $appName
	 * @param DIContainer $container
	 */
	public function registerAppContainer(string $appName, DIContainer $container): void {
		$this->appContainers[strtolower(App::buildAppNamespace($appName, ''))] = $container;
	}

	/**
	 * @param string $appName
	 * @return DIContainer
	 * @throws QueryException
	 */
	public function getRegisteredAppContainer(string $appName): DIContainer {
		if (isset($this->appContainers[strtolower(App::buildAppNamespace($appName, ''))])) {
			return $this->appContainers[strtolower(App::buildAppNamespace($appName, ''))];
		}

		throw new QueryException();
	}

	/**
	 * @param string $namespace
	 * @param string $sensitiveNamespace
	 * @return DIContainer
	 * @throws QueryException
	 */
	protected function getAppContainer(string $namespace, string $sensitiveNamespace): DIContainer {
		if (isset($this->appContainers[$namespace])) {
			return $this->appContainers[$namespace];
		}

		if (isset($this->namespaces[$namespace])) {
			if (!isset($this->hasNoAppContainer[$namespace])) {
				$applicationClassName = 'OCA\\' . $sensitiveNamespace . '\\AppInfo\\Application';
				if (class_exists($applicationClassName)) {
					$app = new $applicationClassName();
					if (isset($this->appContainers[$namespace])) {
						$this->appContainers[$namespace]->offsetSet($applicationClassName, $app);
						return $this->appContainers[$namespace];
					}
				}
				$this->hasNoAppContainer[$namespace] = true;
			}

			return new DIContainer($this->namespaces[$namespace]);
		}
		throw new QueryException();
	}

	public function query(string $name, bool $autoload = true) {
		$name = $this->sanitizeName($name);

		if (isset($this[$name])) {
			return $this[$name];
		}

		// In case the service starts with OCA\ we try to find the service in
		// the apps container first.
		if (strpos($name, 'OCA\\') === 0 && substr_count($name, '\\') >= 2) {
			$segments = explode('\\', $name);
			try {
				$appContainer = $this->getAppContainer(strtolower($segments[1]), $segments[1]);
				return $appContainer->queryNoFallback($name);
			} catch (QueryException $e) {
				// Didn't find the service or the respective app container,
				// ignore it and fall back to the core container.
			}
		} else if (strpos($name, 'OC\\Settings\\') === 0 && substr_count($name, '\\') >= 3) {
			$segments = explode('\\', $name);
			try {
				$appContainer = $this->getAppContainer(strtolower($segments[1]), $segments[1]);
				return $appContainer->queryNoFallback($name);
			} catch (QueryException $e) {
				// Didn't find the service or the respective app container,
				// ignore it and fall back to the core container.
			}
		}

		return parent::query($name, $autoload);
	}
}
