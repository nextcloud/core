<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author j-ed <juergen@eisfair.org>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

use OCA\Files_External\Config\ConfigAdapter;

OC::$CLASSPATH['OC_Mount_Config'] = 'files_external/lib/config.php';

require_once __DIR__ . '/../3rdparty/autoload.php';

// register Application object singleton
\OC_Mount_Config::$app = \OC::$server->query(\OCA\Files_External\AppInfo\Application::class);
\OC_Mount_Config::$app->registerListeners();

$appContainer = \OC_Mount_Config::$app->getContainer();

\OCA\Files\App::getNavigationManager()->add(function () {
	$l = \OC::$server->getL10N('files_external');
	return [
		'id' => 'extstoragemounts',
		'appname' => 'files_external',
		'script' => 'list.php',
		'order' => 30,
		'name' => $l->t('External storages'),
	];
});

$mountProvider = $appContainer->query(ConfigAdapter::class);
\OC::$server->getMountProviderCollection()->registerProvider($mountProvider);
