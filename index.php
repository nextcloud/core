<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sergio Bertolín <sbertolin@solidgear.es>
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

	OC::handleRequest();
} catch (\OC\ServiceUnavailableException $ex) {
	\OC::$server->getLogger()->logException($ex, ['app' => 'index']);

	//show the user a detailed error page
	OC_Template::printExceptionErrorPage($ex, 503);
} catch (\OC\HintException $ex) {
	try {
		OC_Template::printErrorPage($ex->getMessage(), $ex->getHint(), 503);
	} catch (Exception $ex2) {
		try {
			\OC::$server->getLogger()->logException($ex, ['app' => 'index']);
			\OC::$server->getLogger()->logException($ex2, ['app' => 'index']);
		} catch (Throwable $e) {
			// no way to log it properly - but to avoid a white page of death we try harder and ignore this one here
		}

		//show the user a detailed error page
		OC_Template::printExceptionErrorPage($ex, 500);
	}
} catch (\OC\User\LoginException $ex) {
	OC_Template::printErrorPage($ex->getMessage(), $ex->getMessage(), 403);
} catch (Exception $ex) {
	\OC::$server->getLogger()->logException($ex, ['app' => 'index']);

	//show the user a detailed error page
	OC_Template::printExceptionErrorPage($ex, 500);
} catch (Error $ex) {
	try {
		\OC::$server->getLogger()->logException($ex, ['app' => 'index']);
	} catch (Error $e) {
		http_response_code(500);
		header('Content-Type: text/plain; charset=utf-8');
		print("Internal Server Error\n\n");
		print("The server encountered an internal error and was unable to complete your request.\n");
		print("Please contact the server administrator if this error reappears multiple times, please include the technical details below in your report.\n");
		print("More details can be found in the webserver log.\n");

		throw $ex;
	}
	OC_Template::printExceptionErrorPage($ex, 500);
}
