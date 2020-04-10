<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Patrick Paysant <ppaysant@linagora.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

use OC\AppFramework\DependencyInjection\DIContainer;
use OCP\AppFramework\App;
use OCP\Route\IRouter;

/**
 * Class RouteConfig
 * @package OC\AppFramework\routing
 */
class RouteConfig {
	/** @var DIContainer */
	private $container;

	/** @var IRouter */
	private $router;

	/** @var array */
	private $routes;

	/** @var string */
	private $appName;

	/** @var string[] */
	private $controllerNameCache = [];

	/**
	 * @param \OC\AppFramework\DependencyInjection\DIContainer $container
	 * @param \OCP\Route\IRouter $router
	 * @param array $routes
	 * @internal param $appName
	 */
	public function __construct(DIContainer $container, IRouter $router, $routes) {
		$this->routes = $routes;
		$this->container = $container;
		$this->router = $router;
		$this->appName = $container['AppName'];
	}

	/**
	 * The routes and resource will be registered to the \OCP\Route\IRouter
	 */
	public function register() {

		// parse simple
		$this->processSimpleRoutes($this->routes);

		// parse resources
		$this->processResources($this->routes);

		/*
		 * OCS routes go into a different collection
		 */
		$oldCollection = $this->router->getCurrentCollection();
		$this->router->useCollection($oldCollection . '.ocs');

		// parse ocs simple routes
		$this->processOCS($this->routes);

		// parse ocs simple routes
		$this->processOCSResources($this->routes);

		$this->router->useCollection($oldCollection);
	}

	private function processOCS(array $routes): void {
		$ocsRoutes = $routes['ocs'] ?? [];
		foreach ($ocsRoutes as $ocsRoute) {
			$name = $ocsRoute['name'];
			$postfix = $ocsRoute['postfix'] ?? '';
			$root = $ocsRoute['root'] ?? '/apps/' . $this->appName;

			$url = $root . $ocsRoute['url'];
			$verb = strtoupper($ocsRoute['verb'] ?? 'GET');

			$split = explode('#', $name, 2);
			if (count($split) !== 2) {
				throw new \UnexpectedValueException('Invalid route name');
			}
			list($controller, $action) = $split;

			$controllerName = $this->buildControllerName($controller);
			$actionName = $this->buildActionName($action);

			$routeName = 'ocs.' . $this->appName . '.' . $controller . '.' . $action . $postfix;

			// register the route
			$handler = new RouteActionHandler($this->container, $controllerName, $actionName);

			$router = $this->router->create($routeName, $url)
				->method($verb)
				->action($handler);

			// optionally register requirements for route. This is used to
			// tell the route parser how url parameters should be matched
			if(array_key_exists('requirements', $ocsRoute)) {
				$router->requirements($ocsRoute['requirements']);
			}

			// optionally register defaults for route. This is used to
			// tell the route parser how url parameters should be default valued
			if(array_key_exists('defaults', $ocsRoute)) {
				$router->defaults($ocsRoute['defaults']);
			}
		}
	}

	/**
	 * Creates one route base on the give configuration
	 * @param array $routes
	 * @throws \UnexpectedValueException
	 */
	private function processSimpleRoutes(array $routes): void {
		$simpleRoutes = $routes['routes'] ?? [];
		foreach ($simpleRoutes as $simpleRoute) {
			$name = $simpleRoute['name'];
			$postfix = $simpleRoute['postfix'] ?? '';

			$url = $simpleRoute['url'];
			$verb = strtoupper($simpleRoute['verb'] ?? 'GET');

			$split = explode('#', $name, 2);
			if (count($split) !== 2) {
				throw new \UnexpectedValueException('Invalid route name');
			}
			list($controller, $action) = $split;

			$controllerName = $this->buildControllerName($controller);
			$actionName = $this->buildActionName($action);
			$appName = $simpleRoute['app'] ?? $this->appName;

			if (isset($simpleRoute['app'])) {
				// Legacy routes that need to be globally available while they are handled by an app
				// E.g. '/f/{id}', '/s/{token}', '/call/{token}', …
				$controllerName = str_replace('controllerController', 'Controller', $controllerName);
				if ($controllerName === 'PublicpreviewController') {
					$controllerName = 'PublicPreviewController';
				} else if ($controllerName === 'RequesthandlerController') {
					$controllerName = 'RequestHandlerController';
				}
				$controllerName = App::buildAppNamespace($appName) . '\\Controller\\' . $controllerName;
			}

			$routeName = $appName . '.' . $controller . '.' . $action . $postfix;

			// register the route
			$handler = new RouteActionHandler($this->container, $controllerName, $actionName);
			$router = $this->router->create($routeName, $url)
							->method($verb)
							->action($handler);

			// optionally register requirements for route. This is used to
			// tell the route parser how url parameters should be matched
			if(array_key_exists('requirements', $simpleRoute)) {
				$router->requirements($simpleRoute['requirements']);
			}

			// optionally register defaults for route. This is used to
			// tell the route parser how url parameters should be default valued
			if(array_key_exists('defaults', $simpleRoute)) {
				$router->defaults($simpleRoute['defaults']);
			}
		}
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
	private function processOCSResources(array $routes): void {
		// declaration of all restful actions
		$actions = [
			['name' => 'index', 'verb' => 'GET', 'on-collection' => true],
			['name' => 'show', 'verb' => 'GET'],
			['name' => 'create', 'verb' => 'POST', 'on-collection' => true],
			['name' => 'update', 'verb' => 'PUT'],
			['name' => 'destroy', 'verb' => 'DELETE'],
		];

		$resources = $routes['ocs-resources'] ?? [];
		foreach ($resources as $resource => $config) {
			$root = $config['root'] ?? '/apps/' . $this->appName;

			// the url parameter used as id to the resource
			foreach($actions as $action) {
				$url = $root . $config['url'];
				$method = $action['name'];
				$verb = strtoupper($action['verb'] ?? 'GET');
				$collectionAction = $action['on-collection'] ?? false;
				if (!$collectionAction) {
					$url .= '/{id}';
				}
				if (isset($action['url-postfix'])) {
					$url .= '/' . $action['url-postfix'];
				}

				$controller = $resource;

				$controllerName = $this->buildControllerName($controller);
				$actionName = $this->buildActionName($method);

				$routeName = 'ocs.' . $this->appName . '.' . strtolower($resource) . '.' . strtolower($method);

				$this->router->create($routeName, $url)->method($verb)->action(
					new RouteActionHandler($this->container, $controllerName, $actionName)
				);
			}
		}
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
	private function processResources(array $routes): void {
		// declaration of all restful actions
		$actions = [
			['name' => 'index', 'verb' => 'GET', 'on-collection' => true],
			['name' => 'show', 'verb' => 'GET'],
			['name' => 'create', 'verb' => 'POST', 'on-collection' => true],
			['name' => 'update', 'verb' => 'PUT'],
			['name' => 'destroy', 'verb' => 'DELETE'],
		];

		$resources = $routes['resources'] ?? [];
		foreach ($resources as $resource => $config) {

			// the url parameter used as id to the resource
			foreach($actions as $action) {
				$url = $config['url'];
				$method = $action['name'];
				$verb = strtoupper($action['verb'] ?? 'GET');
				$collectionAction = $action['on-collection'] ?? false;
				if (!$collectionAction) {
					$url .= '/{id}';
				}
				if (isset($action['url-postfix'])) {
					$url .= '/' . $action['url-postfix'];
				}

				$controller = $resource;

				$controllerName = $this->buildControllerName($controller);
				$actionName = $this->buildActionName($method);

				$routeName = $this->appName . '.' . strtolower($resource) . '.' . strtolower($method);

				$this->router->create($routeName, $url)->method($verb)->action(
					new RouteActionHandler($this->container, $controllerName, $actionName)
				);
			}
		}
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
