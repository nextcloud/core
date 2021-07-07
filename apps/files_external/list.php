<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jesús Macias <jmacias@solidgear.es>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
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
// Check if we are a user
OC_Util::checkLoggedIn();
$config = \OC::$server->getConfig();
$userSession = \OC::$server->getUserSession();

$showgridview = $config->getUserValue($userSession->getUser()->getUID(), 'files', 'show_grid', true);

$tmpl = new OCP\Template('files_external', 'list', '');

// gridview not available for ie
$tmpl->assign('showgridview', $showgridview);

/* Load Status Manager */
\OCP\Util::addStyle('files_external', 'external');
\OCP\Util::addScript('files_external', 'statusmanager');
\OCP\Util::addScript('files_external', 'templates');
\OCP\Util::addScript('files_external', 'rollingqueue');

OCP\Util::addScript('files_external', 'app');
OCP\Util::addScript('files_external', 'mountsfilelist');

$tmpl->printPage();
