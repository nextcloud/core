<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files\Event\LoadSidebar;
use OCA\Viewer\Event\LoadViewer;
use OCP\EventDispatcher\GenericEvent;

// Check if we are a user
OCP\User::checkLoggedIn();
$config = \OC::$server->getConfig();
$userSession = \OC::$server->getUserSession();
$eventDispatcher = \OC::$server->getEventDispatcher();

$showgridview = $config->getUserValue($userSession->getUser()->getUID(), 'files', 'show_grid', false);
$isIE = \OCP\Util::isIE();

$tmpl = new OCP\Template('files_sharing', 'list', '');

// gridview not available for ie
$tmpl->assign('showgridview', $showgridview && !$isIE);

// fire script events
$eventDispatcher->dispatch('\OCP\Collaboration\Resources::loadAdditionalScripts', new GenericEvent());
$eventDispatcher->dispatch(LoadAdditionalScriptsEvent::class, new LoadAdditionalScriptsEvent());
$eventDispatcher->dispatch(LoadSidebar::class, new LoadSidebar());

// Load Viewer scripts
if (class_exists(LoadViewer::class)) {
	$eventDispatcher->dispatch(LoadViewer::class, new LoadViewer());
}

$tmpl->printPage();
