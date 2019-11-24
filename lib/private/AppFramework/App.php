<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */


namespace OC\AppFramework;

use OC\AppFramework\Http\Dispatcher;
use OC\AppFramework\DependencyInjection\DIContainer;
use OC\HintException;
use OCP\AppFramework\Http;
use OCP\AppFramework\QueryException;
use OCP\AppFramework\Http\ICallbackResponse;
use OCP\AppFramework\Http\IOutput;
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
	public static function buildAppNamespace(string $appId, string $topNamespace='OCA\\'): string {
		// Hit the cache!
		if (isset(self::$nameSpaceCache[$appId])) {
			return $topNamespace . self::$nameSpaceCache[$appId];
		}

		$appInfo = \OC_App::getAppInfo($appId);
		if (isset($appInfo['namespace'])) {
			self::$nameSpaceCache[$appId] = trim($appInfo['namespace']);
		} else {
			// if the tag is not found, fall back to uppercasing the first letter
			self::$nameSpaceCache[$appId] = ucfirst($appId);
		}

		return $topNamespace . self::$nameSpaceCache[$appId];
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
			$container->query(IRequest::class)->setUrlParameters($urlParams);
		} else if (isset($container['urlParams']) && !is_null($container['urlParams'])) {
			$container->query(IRequest::class)->setUrlParameters($container['urlParams']);
		}
		$appName = $container['AppName'];

		// first try $controllerName then go for \OCA\AppName\Controller\$controllerName
		try {
			$controller = $container->query($controllerName);
		} catch(QueryException $e) {
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
		) = $dispatcher->dispatch($controller, $methodName, $container->query(IRequest::class));
		$io = $container[IOutput::class];

		if(!is_null($httpHeaders)) {
			$io->setHeader($httpHeaders);
		}

		foreach($responseHeaders as $name => $value) {
			$io->setHeader($name . ': ' . $value);
		}

		foreach($responseCookies as $name => $value) {
			$expireDate = null;
			if($value['expireDate'] instanceof \DateTime) {
				$expireDate = $value['expireDate']->getTimestamp();
			}
			$io->setCookie(
				$name,
				$value['value'],
				$expireDate,
				$container->getServer()->getWebRoot(),
				null,
				$container->getServer()->getRequest()->getServerProtocol() === 'https',
				true
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
			} else if (!is_null($output)) {
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
								DIContainer $container){

		$container['urlParams'] = $urlParams;
		$controller = $container[$controllerName];

		$dispatcher = $container['Dispatcher'];

		list(, , $output) =  $dispatcher->dispatch($controller, $methodName);
		return $output;
	}

}
