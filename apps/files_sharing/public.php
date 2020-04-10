<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

// This file is just used to redirect the legacy sharing URLs (< ownCloud 8) to the new ones

$urlGenerator = \OC::$server->getURLGenerator();
$token = isset($_GET['t']) ? $_GET['t'] : '';
$route = isset($_GET['download']) ? 'files_sharing.sharecontroller.downloadShare' : 'files_sharing.sharecontroller.showShare';

if ($token !== '') {
	$protocol = \OC::$server->getRequest()->getHttpProtocol();
	if ($protocol == 'HTTP/1.0') {
		http_response_code(302);
	} else {
		http_response_code(307);
	}
	header('Location: ' . $urlGenerator->linkToRoute($route, ['token' => $token]));
} else {
	http_response_code(404);
	$tmpl = new OCP\Template('', '404', 'guest');
	print_unescaped($tmpl->fetchPage());
}
