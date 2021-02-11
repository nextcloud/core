<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

try {
	require_once __DIR__ . '/lib/base.php';
	if (\OCP\Util::needUpgrade()) {
		// since the behavior of apps or remotes are unpredictable during
		// an upgrade, return a 503 directly
		OC_Template::printErrorPage('Service unavailable', '', 503);
		exit;
	}

	OC::checkMaintenanceMode(\OC::$server->get(\OC\SystemConfig::class));
	$request = \OC::$server->getRequest();
	$pathInfo = $request->getPathInfo();

	if (!$pathInfo && $request->getParam('service', '') === '') {
		http_response_code(404);
		exit;
	} elseif ($request->getParam('service', '')) {
		$service = $request->getParam('service', '');
	} else {
		$pathInfo = trim($pathInfo, '/');
		list($service) = explode('/', $pathInfo);
	}
	$file = \OC::$server->getConfig()->getAppValue('core', 'public_' . strip_tags($service));
	if ($file === '') {
		http_response_code(404);
		exit;
	}

	$parts = explode('/', $file, 2);
	$app = $parts[0];

	// Load all required applications
	\OC::$REQUESTEDAPP = $app;
	OC_App::loadApps(['authentication']);
	OC_App::loadApps(['filesystem', 'logging']);

	if (!\OC::$server->getAppManager()->isInstalled($app)) {
		http_response_code(404);
		exit;
	}
	OC_App::loadApp($app);
	OC_User::setIncognitoMode(true);

	$baseuri = OC::$WEBROOT . '/public.php/' . $service . '/';

	require_once OC_App::getAppPath($app) . '/' . $parts[1];
} catch (Exception $ex) {
	$status = 500;
	if ($ex instanceof \OC\ServiceUnavailableException) {
		$status = 503;
	}
	//show the user a detailed error page
	\OC::$server->getLogger()->logException($ex, ['app' => 'public']);
	OC_Template::printExceptionErrorPage($ex, $status);
} catch (Error $ex) {
	//show the user a detailed error page
	\OC::$server->getLogger()->logException($ex, ['app' => 'public']);
	OC_Template::printExceptionErrorPage($ex, 500);
}
