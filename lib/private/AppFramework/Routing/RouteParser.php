<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

namespace OC\AppFramework\Routing;

use OC\Route\Route;
use Symfony\Component\Routing\RouteCollection;

class RouteParser {
	/** @var string[] */
	private $controllerNameCache = [];

	private const rootUrlApps = [
		'cloud_federation_api',
		'core',
		'files_sharing',
		'files',
		'settings',
		'spreed',
	];

	public function parseDefaultRoutes(array $routes, string $appName): RouteCollection {
		$collection = $this->processIndexRoutes($routes, $appName);
		$collection->addCollection($this->processIndexResources($routes, $appName));

		return $collection;
	}

	public function parseOCSRoutes(array $routes, string $appName): RouteCollection {
		$collection = $this->processOCS($routes, $appName);
		$collection->addCollection($this->processOCSResources($routes, $appName));

		return $collection;
	}

	private function processOCS(array $routes, string $appName): RouteCollection {
		$collection = new RouteCollection();
		$ocsRoutes = $routes['ocs'] ?? [];
		foreach ($ocsRoutes as $ocsRoute) {
			$result = $this->processRoute($ocsRoute, $appName, 'ocs.');

			$collection->add($result[0], $result[1]);
		}

		return $collection;
	}

	/**
	 * Creates one route base on the give configuration
	 * @param array $routes
	 * @throws \UnexpectedValueException
	 */
	private function processIndexRoutes(array $routes, string $appName): RouteCollection {
		$collection = new RouteCollection();
		$simpleRoutes = $routes['routes'] ?? [];
		foreach ($simpleRoutes as $simpleRoute) {
			$result = $this->processRoute($simpleRoute, $appName);

			$collection->add($result[0], $result[1]);
		}

		return $collection;
	}

	private function processRoute(array $route, string $appName, string $routeNamePrefix = ''): array {
		$name = $route['name'];
		$postfix = $route['postfix'] ?? '';
		$root = $this->buildRootPrefix($route, $appName, $routeNamePrefix);

		$url = $root . '/' . ltrim($route['url'], '/');
		$verb = strtoupper($route['verb'] ?? 'GET');

		$split = explode('#', $name, 2);
		if (count($split) !== 2) {
			throw new \UnexpectedValueException('Invalid route name');
		}
		list($controller, $action) = $split;

		$controllerName = $this->buildControllerName($controller);
		$actionName = $this->buildActionName($action);

		$routeName = $routeNamePrefix . $appName . '.' . $controller . '.' . $action . $postfix;

		$routeObject = new Route($url);
		$routeObject->method($verb);

		// optionally register requirements for route. This is used to
		// tell the route parser how url parameters should be matched
		if (array_key_exists('requirements', $route)) {
			$routeObject->requirements($route['requirements']);
		}

		// optionally register defaults for route. This is used to
		// tell the route parser how url parameters should be default valued
		$defaults = [];
		if (array_key_exists('defaults', $route)) {
			$defaults = $route['defaults'];
		}

		$defaults['caller'] = [$appName, $controllerName, $actionName];
		$routeObject->defaults($defaults);

		return [$routeName, $routeObject];
	}

	/**
	 * For a given name and url restful OCS routes are created:
	 *  - index
	 *  - show
	 *  - create
	 *  - update
	 *  - destroy
	 *
	 * @param array $routes
	 */
	private function processOCSResources(array $routes, string $appName): RouteCollection {
		return $this->processResources($routes['ocs-resources'] ?? [], $appName, 'ocs.');
	}

	/**
	 * For a given name and url restful routes are created:
	 *  - index
	 *  - show
	 *  - create
	 *  - update
	 *  - destroy
	 *
	 * @param array $routes
	 */
	private function processIndexResources(array $routes, string $appName): RouteCollection {
		return $this->processResources($routes['resources'] ?? [], $appName);
	}

	/**
	 * For a given name and url restful routes are created:
	 *  - index
	 *  - show
	 *  - create
	 *  - update
	 *  - destroy
	 *
	 * @param array $resources
	 * @param string $routeNamePrefix
	 */
	private function processResources(array $resources, string $appName, string $routeNamePrefix = ''): RouteCollection {
		// declaration of all restful actions
		$actions = [
			['name' => 'index', 'verb' => 'GET', 'on-collection' => true],
			['name' => 'show', 'verb' => 'GET'],
			['name' => 'create', 'verb' => 'POST', 'on-collection' => true],
			['name' => 'update', 'verb' => 'PUT'],
			['name' => 'destroy', 'verb' => 'DELETE'],
		];

		$collection = new RouteCollection();
		foreach ($resources as $resource => $config) {
			$root = $this->buildRootPrefix($config, $appName, $routeNamePrefix);

			// the url parameter used as id to the resource
			foreach ($actions as $action) {
				$url = $root . '/' . ltrim($config['url'], '/');
				$method = $action['name'];

				$verb = strtoupper($action['verb'] ?? 'GET');
				$collectionAction = $action['on-collection'] ?? false;
				if (!$collectionAction) {
					$url .= '/{id}';
				}

				$controller = $resource;

				$controllerName = $this->buildControllerName($controller);
				$actionName = $this->buildActionName($method);

				$routeName = $routeNamePrefix . $appName . '.' . strtolower($resource) . '.' . $method;

				$route = new Route($url);
				$route->method($verb);

				$route->defaults(['caller' => [$appName, $controllerName, $actionName]]);

				$collection->add($routeName, $route);
			}
		}

		return $collection;
	}

	private function buildRootPrefix(array $route, string $appName, string $routeNamePrefix): string {
		$defaultRoot = $appName === 'core' ? '' : '/apps/' . $appName;
		$root = $route['root'] ?? $defaultRoot;

		if ($routeNamePrefix !== '') {
			// In OCS all apps are whitelisted
			return $root;
		}

		if (!\in_array($appName, self::rootUrlApps, true)) {
			// Only allow root URLS for some apps
			return  $defaultRoot;
		}

		return $root;
	}

	/**
	 * Based on a given route name the controller name is generated
	 * @param string $controller
	 * @return string
	 */
	private function buildControllerName(string $controller): string {
		if (!isset($this->controllerNameCache[$controller])) {
			$this->controllerNameCache[$controller] = $this->underScoreToCamelCase(ucfirst($controller)) . 'Controller';
		}
		return $this->controllerNameCache[$controller];
	}

	/**
	 * Based on the action part of the route name the controller method name is generated
	 * @param string $action
	 * @return string
	 */
	private function buildActionName(string $action): string {
		return $this->underScoreToCamelCase($action);
	}

	/**
	 * Underscored strings are converted to camel case strings
	 * @param string $str
	 * @return string
	 */
	private function underScoreToCamelCase(string $str): string {
		$pattern = '/_[a-z]?/';
		return preg_replace_callback(
			$pattern,
			function ($matches) {
				return strtoupper(ltrim($matches[0], '_'));
			},
			$str);
	}
}
