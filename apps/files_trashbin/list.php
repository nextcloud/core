<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

$showgridview = $config->getUserValue($userSession->getUser()->getUID(), 'files', 'show_grid', false);

$tmpl = new OCP\Template('files_trashbin', 'index', '');

// gridview not available for ie
$tmpl->assign('showgridview', $showgridview);
OCP\Util::addScript('files_trashbin', 'files_trashbin');
$tmpl->printPage();
