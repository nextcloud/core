<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Robin Appelman <robin@icewind.nl>
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
use OCP\Share\IManager;

$config = \OC::$server->getConfig();
$userSession = \OC::$server->getUserSession();
// TODO: move this to the generated config.js
/** @var IManager $shareManager */
$shareManager = \OC::$server->get(IManager::class);
$publicUploadEnabled = $shareManager->shareApiLinkAllowPublicUpload() ? 'yes' : 'no';;

$showgridview = $config->getUserValue($userSession->getUser()->getUID(), 'files', 'show_grid', false);

// renders the controls and table headers template
$tmpl = new OCP\Template('files', 'list', '');

// gridview not available for ie
$tmpl->assign('showgridview', $showgridview);
$tmpl->assign('publicUploadEnabled', $publicUploadEnabled);
$tmpl->printPage();
