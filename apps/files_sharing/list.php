<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files\Event\LoadSidebar;
use OCA\Viewer\Event\LoadViewer;
use OCP\EventDispatcher\GenericEvent;

// Check if we are a user
OC_Util::checkLoggedIn();
$config = \OC::$server->getConfig();
$userSession = \OC::$server->getUserSession();
$legacyEventDispatcher = \OC::$server->getEventDispatcher();
/** @var \OCP\EventDispatcher\IEventDispatcher $eventDispatcher */
$eventDispatcher = \OC::$server->get(OCP\EventDispatcher\IEventDispatcher::class);

$showgridview = $config->getUserValue($userSession->getUser()->getUID(), 'files', 'show_grid', false);

$tmpl = new OCP\Template('files_sharing', 'list', '');

// gridview not available for ie
$tmpl->assign('showgridview', $showgridview);

// fire script events
$legacyEventDispatcher->dispatch('\OCP\Collaboration\Resources::loadAdditionalScripts', new GenericEvent());
$eventDispatcher->dispatchTyped(new LoadAdditionalScriptsEvent());
$eventDispatcher->dispatchTyped(new LoadSidebar());

// Load Viewer scripts
if (class_exists(LoadViewer::class)) {
	$eventDispatcher->dispatchTyped(new LoadViewer());
}

$tmpl->printPage();
