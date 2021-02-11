<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Ross Nicoll <jrn@jrn.me.uk>
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

/**
 * @var $this \OCP\Route\IRouter
 **/
\OCA\Files_External\MountConfig::$app->registerRoutes(
	$this,
	[
		'resources' => [
			'global_storages' => ['url' => '/globalstorages'],
			'user_storages' => ['url' => '/userstorages'],
			'user_global_storages' => ['url' => '/userglobalstorages'],
		],
		'routes' => [
			[
				'name' => 'Ajax#getSshKeys',
				'url' => '/ajax/public_key.php',
				'verb' => 'POST',
				'requirements' => [],
			],
			[
				'name' => 'Ajax#saveGlobalCredentials',
				'url' => '/globalcredentials',
				'verb' => 'POST',
			],
		],
		'ocs' => [
			[
				'name' => 'Api#getUserMounts',
				'url' => '/api/v1/mounts',
				'verb' => 'GET',
			],
		],
	]
);

$this->create('files_external_oauth1', 'apps/files_external/ajax/oauth1.php')
	->actionInclude('files_external/ajax/oauth1.php');
$this->create('files_external_oauth2', 'apps/files_external/ajax/oauth2.php')
	->actionInclude('files_external/ajax/oauth2.php');


$this->create('files_external_list_applicable', '/apps/files_external/applicable')
	->actionInclude('files_external/ajax/applicable.php');
