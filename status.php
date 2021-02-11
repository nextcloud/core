<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Frank Karlitschek <frank@karlitschek.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Kristof Provost <github@sigsegv.be>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author martin.mattel@diemattels.at <martin.mattel@diemattels.at>
 * @author Masaki Kawabata Neto <masaki.kawabata@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

require_once __DIR__ . '/lib/versioncheck.php';

try {
	require_once __DIR__ . '/lib/base.php';

	$systemConfig = \OC::$server->getSystemConfig();

	$installed = (bool) $systemConfig->getValue('installed', false);
	$maintenance = (bool) $systemConfig->getValue('maintenance', false);
	# see core/lib/private/legacy/defaults.php and core/themes/example/defaults.php
	# for description and defaults
	$defaults = new \OCP\Defaults();
	$values = [
		'installed' => $installed,
		'maintenance' => $maintenance,
		'needsDbUpgrade' => \OCP\Util::needUpgrade(),
		'version' => implode('.', \OCP\Util::getVersion()),
		'versionstring' => OC_Util::getVersionString(),
		'edition' => '',
		'productname' => $defaults->getName(),
		'extendedSupport' => \OCP\Util::hasExtendedSupport()
	];
	if (OC::$CLI) {
		print_r($values);
	} else {
		header('Access-Control-Allow-Origin: *');
		header('Content-Type: application/json');
		echo json_encode($values);
	}
} catch (Exception $ex) {
	http_response_code(500);
	\OC::$server->getLogger()->logException($ex, ['app' => 'remote']);
}
