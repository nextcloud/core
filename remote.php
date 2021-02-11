<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Brice Maron <brice@bmaron.net>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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

require_once __DIR__ . '/lib/versioncheck.php';

use OCA\DAV\Connector\Sabre\ExceptionLoggerPlugin;
use Sabre\DAV\Exception\ServiceUnavailable;
use Sabre\DAV\Server;

/**
 * Class RemoteException
 * Dummy exception class to be use locally to identify certain conditions
 * Will not be logged to avoid DoS
 */
class RemoteException extends Exception {
}

/**
 * @param Exception|Error $e
 */
function handleException($e) {
	$request = \OC::$server->getRequest();
	// in case the request content type is text/xml - we assume it's a WebDAV request
	$isXmlContentType = strpos($request->getHeader('Content-Type'), 'text/xml');
	if ($isXmlContentType === 0) {
		// fire up a simple server to properly process the exception
		$server = new Server();
		if (!($e instanceof RemoteException)) {
			// we shall not log on RemoteException
			$server->addPlugin(new ExceptionLoggerPlugin('webdav', \OC::$server->getLogger()));
		}
		$server->on('beforeMethod:*', function () use ($e) {
			if ($e instanceof RemoteException) {
				switch ($e->getCode()) {
					case 503:
						throw new ServiceUnavailable($e->getMessage());
					case 404:
						throw new \Sabre\DAV\Exception\NotFound($e->getMessage());
				}
			}
			$class = get_class($e);
			$msg = $e->getMessage();
			throw new ServiceUnavailable("$class: $msg");
		});
		$server->exec();
	} else {
		$statusCode = 500;
		if ($e instanceof \OC\ServiceUnavailableException) {
			$statusCode = 503;
		}
		if ($e instanceof RemoteException) {
			// we shall not log on RemoteException
			OC_Template::printErrorPage($e->getMessage(), '', $e->getCode());
		} else {
			\OC::$server->getLogger()->logException($e, ['app' => 'remote']);
			OC_Template::printExceptionErrorPage($e, $statusCode);
		}
	}
}

/**
 * @param $service
 * @return string
 */
function resolveService($service) {
	$services = [
		'webdav' => 'dav/appinfo/v1/webdav.php',
		'dav' => 'dav/appinfo/v2/remote.php',
		'caldav' => 'dav/appinfo/v1/caldav.php',
		'calendar' => 'dav/appinfo/v1/caldav.php',
		'carddav' => 'dav/appinfo/v1/carddav.php',
		'contacts' => 'dav/appinfo/v1/carddav.php',
		'files' => 'dav/appinfo/v1/webdav.php',
		'direct' => 'dav/appinfo/v2/direct.php',
	];
	if (isset($services[$service])) {
		return $services[$service];
	}

	return \OC::$server->getConfig()->getAppValue('core', 'remote_' . $service);
}

try {
	require_once __DIR__ . '/lib/base.php';

	// All resources served via the DAV endpoint should have the strictest possible
	// policy. Exempted from this is the SabreDAV browser plugin which overwrites
	// this policy with a softer one if debug mode is enabled.
	header("Content-Security-Policy: default-src 'none';");

	if (\OCP\Util::needUpgrade()) {
		// since the behavior of apps or remotes are unpredictable during
		// an upgrade, return a 503 directly
		throw new RemoteException('Service unavailable', 503);
	}

	$request = \OC::$server->getRequest();
	$pathInfo = $request->getPathInfo();
	if ($pathInfo === false || $pathInfo === '') {
		throw new RemoteException('Path not found', 404);
	}
	if (!$pos = strpos($pathInfo, '/', 1)) {
		$pos = strlen($pathInfo);
	}
	$service = substr($pathInfo, 1, $pos - 1);

	$file = resolveService($service);

	if (is_null($file)) {
		throw new RemoteException('Path not found', 404);
	}

	$file = ltrim($file, '/');

	$parts = explode('/', $file, 2);
	$app = $parts[0];

	// Load all required applications
	\OC::$REQUESTEDAPP = $app;
	OC_App::loadApps(['authentication']);
	OC_App::loadApps(['filesystem', 'logging']);

	switch ($app) {
		case 'core':
			$file = OC::$SERVERROOT .'/'. $file;
			break;
		default:
			if (!\OC::$server->getAppManager()->isInstalled($app)) {
				throw new RemoteException('App not installed: ' . $app);
			}
			OC_App::loadApp($app);
			$file = OC_App::getAppPath($app) .'/'. $parts[1];
			break;
	}
	$baseuri = OC::$WEBROOT . '/remote.php/'.$service.'/';
	require_once $file;
} catch (Exception $ex) {
	handleException($ex);
} catch (Error $e) {
	handleException($e);
}
