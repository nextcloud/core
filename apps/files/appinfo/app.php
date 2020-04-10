<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

use OC\Search\Provider\File;
use OCA\Files\AppInfo\Application;

$app = \OC::$server->query(Application::class);

// required for translation purpose
// t('Files')
$l = \OC::$server->getL10N('files');

\OC::$server->getSearch()->registerProvider(File::class, ['apps' => ['files']]);

$templateManager = \OC_Helper::getFileTemplateManager();
$templateManager->registerTemplate('application/vnd.oasis.opendocument.presentation', 'core/templates/filetemplates/template.odp');
$templateManager->registerTemplate('application/vnd.oasis.opendocument.text', 'core/templates/filetemplates/template.odt');
$templateManager->registerTemplate('application/vnd.oasis.opendocument.spreadsheet', 'core/templates/filetemplates/template.ods');

\OCA\Files\App::getNavigationManager()->add([
	'id'      => 'files',
	'appname' => 'files',
	'script'  => 'list.php',
	'order'   => 0,
	'name'    => $l->t('All files')
]);

\OCA\Files\App::getNavigationManager()->add([
	'id'      => 'recent',
	'appname' => 'files',
	'script'  => 'recentlist.php',
	'order'   => 2,
	'name'    => $l->t('Recent')
]);

\OCA\Files\App::getNavigationManager()->add([
	'id'            => 'favorites',
	'appname'       => 'files',
	'script'        => 'simplelist.php',
	'order'         => 5,
	'name'          => $l->t('Favorites'),
	'expandedState' => 'show_Quick_Access'
]);

\OCP\Util::connectHook('\OCP\Config', 'js', '\OCA\Files\App', 'extendJsConfig');
