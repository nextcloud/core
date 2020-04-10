<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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
\OC_JSON::checkAppEnabled('files_external');
\OC_JSON::callCheck();

\OC_JSON::checkAdminUser();

$pattern = '';
$limit = null;
$offset = null;
if (isset($_GET['pattern'])) {
	$pattern = (string)$_GET['pattern'];
}
if (isset($_GET['limit'])) {
	$limit = (int)$_GET['limit'];
}
if (isset($_GET['offset'])) {
	$offset = (int)$_GET['offset'];
}

$groups = [];
foreach (\OC::$server->getGroupManager()->search($pattern, $limit, $offset) as $group) {
	$groups[$group->getGID()] = $group->getDisplayName();
}

$users = [];
foreach (\OC::$server->getUserManager()->searchDisplayName($pattern, $limit, $offset) as $user) {
	$users[$user->getUID()] = $user->getDisplayName();
}

$results = ['groups' => $groups, 'users' => $users];

\OC_JSON::success($results);
