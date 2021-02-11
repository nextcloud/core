<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\AppFramework;

use OC\AppFramework\DependencyInjection\DIContainer;
use OC\AppFramework\Http\Dispatcher;
use OC\AppFramework\Http\Request;
use OC\HintException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\QueryException;
use OCP\IRequest;

/**
 * Entry point for every request in your app. You can consider this as your
 * public static void main() method
 *
 * Handles all the dependency injection, controllers and output flow
 */
class App {

	/** @var string[] */
	private static $nameSpaceCache = [];

	/**
	 * Turns an app id into a namespace by either reading the appinfo.xml's
	 * namespace tag or uppercasing the appid's first letter
	 * @param string $appId the app id
	 * @param string $topNamespace the namespace which should be prepended to
	 * the transformed app id, defaults to OCA\
	 * @return string the starting namespace for the app
	 */
	public static function buildAppNamespace(string $appId, string $topNamespace = 'OCA\\'): string {
		// Hit the cache!
		if (isset(self::$nameSpaceCache[$appId])) {
			return $topNamespace . self::$nameSpaceCache[$appId];
		}

		$appInfo = \OC_App::getAppInfo($appId);
		if (isset($appInfo['namespace'])) {
			self::$nameSpaceCache[$appId] = trim($appInfo['namespace']);
		} else {
			if ($appId !== 'spreed') {
				// if the tag is not found, fall back to uppercasing the first letter
				self::$nameSpaceCache[$appId] = ucfirst($appId);
			} else {
				// For the Talk app (appid spreed) the above fallback doesn't work.
				// This leads to a problem when trying to install it freshly,
				// because the apps namespace is already registered before the
				// app is downloaded from the appstore, because of the hackish
				// global route index.php/call/{token} which is registered via
				// the core/routes.php so it does not have the app namespace.
				// @ref https://github.com/nextcloud/server/pull/19433
				self::$nameSpaceCache[$appId] = 'Talk';
			}
		}

		return $topNamespace . self::$nameSpaceCache[$appId];
	}

	public static function getAppIdForClass(string $className, string $topNamespace = 'OCA\\'): ?string {
		if (strpos($className, $topNamespace) !== 0) {
			return null;
		}

		foreach (self::$nameSpaceCache as $appId => $namespace) {
			if (strpos($className, $topNamespace . $namespace . '\\') === 0) {
				return $appId;
			}
		}

		return null;
	}


	/**
	 * Shortcut for calling a controller method and printing the result
	 * @param string $controllerName the name of the controller under which it is
	 *                               stored in the DI container
	 * @param string $methodName the method that you want to call
	 * @param DIContainer $container an instance of a pimple container.
	 * @param array $urlParams list of URL parameters (optional)
	 * @throws HintException
	 */
	public static function main(string $controllerName, string $methodName, DIContainer $container, array $urlParams = null) {
		if (!is_null($urlParams)) {
			/** @var Request $request */
			$request = $container->query(IRequest::class);
			$request->setUrlParameters($urlParams);
		} elseif (isset($container['urlParams']) && !is_null($container['urlParams'])) {
			/** @var Request $request */
			$request = $container->query(IRequest::class);
			$request->setUrlParameters($container['urlParams']);
		}
		$appName = $container['AppName'];

		// first try $controllerName then go for \OCA\AppName\Controller\$controllerName
		try {
			$controller = $container->query($controllerName);
		} catch (QueryException $e) {
			if (strpos($controllerName, '\\Controller\\') !== false) {
				// This is from a global registered app route that is not enabled.
				[/*OC(A)*/, $app, /* Controller/Name*/] = explode('\\', $controllerName, 3);
				throw new HintException('App ' . strtolower($app) . ' is not enabled');
			}

			if ($appName === 'core') {
				$appNameSpace = 'OC\\Core';
			} else {
				$appNameSpace = self::buildAppNamespace($appName);
			}
			$controllerName = $appNameSpace . '\\Controller\\' . $controllerName;
			$controller = $container->query($controllerName);
		}

		// initialize the dispatcher and run all the middleware before the controller
		/** @var Dispatcher $dispatcher */
		$dispatcher = $container['Dispatcher'];

		list(
			$httpHeaders,
			$responseHeaders,
			$responseCookies,
			$output,
			$response
		) = $dispatcher->dispatch($controller, $methodName);

		$io = $container[IOutput::class];

		if (!is_null($httpHeaders)) {
			$io->setHeader($httpHeaders);
		}

		foreach ($responseHeaders as $name => $value) {
			$io->setHeader($name . ': ' . $value);
		}

		foreach ($responseCookies as $name => $value) {
			$expireDate = null;
			if ($value['expireDate'] instanceof \DateTime) {
				$expireDate = $value['expireDate']->getTimestamp();
			}
			$sameSite = $value['sameSite'] ?? 'Lax';

			$io->setCookie(
				$name,
				$value['value'],
				$expireDate,
				$container->getServer()->getWebRoot(),
				null,
				$container->getServer()->getRequest()->getServerProtocol() === 'https',
				true,
				$sameSite
			);
		}

		/*
		 * Status 204 does not have a body and no Content Length
		 * Status 304 does not have a body and does not need a Content Length
		 * https://tools.ietf.org/html/rfc7230#section-3.3
		 * https://tools.ietf.org/html/rfc7230#section-3.3.2
		 */
		$emptyResponse = false;
		if (preg_match('/^HTTP\/\d\.\d (\d{3}) .*$/', $httpHeaders, $matches)) {
			$status = (int)$matches[1];
			if ($status === Http::STATUS_NO_CONTENT || $status === Http::STATUS_NOT_MODIFIED) {
				$emptyResponse = true;
			}
		}

		if (!$emptyResponse) {
			if ($response instanceof ICallbackResponse) {
				$response->callback($io);
			} elseif (!is_null($output)) {
				$io->setHeader('Content-Length: ' . strlen($output));
				$io->setOutput($output);
			}
		}
	}

	/**
	 * Shortcut for calling a controller method and printing the result.
	 * Similar to App:main except that no headers will be sent.
	 * This should be used for example when registering sections via
	 * \OC\AppFramework\Core\API::registerAdmin()
	 *
	 * @param string $controllerName the name of the controller under which it is
	 *                               stored in the DI container
	 * @param string $methodName the method that you want to call
	 * @param array $urlParams an array with variables extracted from the routes
	 * @param DIContainer $container an instance of a pimple container.
	 */
	public static function part(string $controllerName, string $methodName, array $urlParams,
								DIContainer $container) {
		$container['urlParams'] = $urlParams;
		$controller = $container[$controllerName];

		$dispatcher = $container['Dispatcher'];

		list(, , $output) = $dispatcher->dispatch($controller, $methodName);
		return $output;
	}
}
