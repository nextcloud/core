<?php
/**
 * @copyright Copyright (c) 2018 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


require_once __DIR__ . '/../lib/base.php';

header('Content-Type: application/json');

$server = \OC::$server;

$isEnabled = $server->getAppManager()->isEnabledForUser('cloud_federation_api');

if ($isEnabled) {
	$capabilities = new OCA\CloudFederationAPI\Capabilities($server->getURLGenerator());
	header('Content-Type: application/json');
	echo json_encode($capabilities->getCapabilities()['ocm']);
} else {
	header($_SERVER["SERVER_PROTOCOL"]." 501 Not Implemented", true, 501);
	exit("501 Not Implemented");
}
