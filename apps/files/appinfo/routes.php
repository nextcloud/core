<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Felix Nüsse <Felix.nuesse@t-online.de>
 * @author fnuesse <felix.nuesse@t-online.de>
 * @author fnuesse <fnuesse@techfak.uni-bielefeld.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tobias Kaminsky <tobias@kaminsky.me>
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
namespace OCA\Files\AppInfo;

/** @var Application $application */
$application = \OC::$server->query(Application::class);
$application->registerRoutes(
	$this,
	[
		'routes' => [
			[
				'name' => 'View#showFile',
				'url' => '/f/{fileid}',
				'verb' => 'GET',
				'root' => '',
			],

			[
				'name' => 'API#getThumbnail',
				'url' => '/api/v1/thumbnail/{x}/{y}/{file}',
				'verb' => 'GET',
				'requirements' => ['file' => '.+']
			],
			[
				'name' => 'API#updateFileTags',
				'url' => '/api/v1/files/{path}',
				'verb' => 'POST',
				'requirements' => ['path' => '.+'],
			],
			[
				'name' => 'API#getRecentFiles',
				'url' => '/api/v1/recent/',
				'verb' => 'GET'
			],
			[
				'name' => 'API#updateFileSorting',
				'url' => '/api/v1/sorting',
				'verb' => 'POST'
			],
			[
				'name' => 'API#showHiddenFiles',
				'url' => '/api/v1/showhidden',
				'verb' => 'POST'
			],
			[
				'name' => 'API#cropImagePreviews',
				'url' => '/api/v1/cropimagepreviews',
				'verb' => 'POST'
			],
			[
				'name' => 'API#showGridView',
				'url' => '/api/v1/showgridview',
				'verb' => 'POST'
			],
			[
				'name' => 'API#getGridView',
				'url' => '/api/v1/showgridview',
				'verb' => 'GET'
			],
			[
				'name' => 'view#index',
				'url' => '/',
				'verb' => 'GET',
			],
			[
				'name' => 'ajax#getStorageStats',
				'url' => '/ajax/getstoragestats.php',
				'verb' => 'GET',
			],
			[
				'name' => 'API#toggleShowFolder',
				'url' => '/api/v1/toggleShowFolder/{key}',
				'verb' => 'POST'
			],
			[
				'name' => 'API#getNodeType',
				'url' => '/api/v1/quickaccess/get/NodeType',
				'verb' => 'GET',
			],
			[
				'name' => 'DirectEditingView#edit',
				'url' => '/directEditing/{token}',
				'verb' => 'GET'
			],
		],
		'ocs' => [
			[
				'name' => 'DirectEditing#info',
				'url' => '/api/v1/directEditing',
				'verb' => 'GET'
			],
			[
				'name' => 'DirectEditing#templates',
				'url' => '/api/v1/directEditing/templates/{editorId}/{creatorId}',
				'verb' => 'GET'
			],
			[
				'name' => 'DirectEditing#open',
				'url' => '/api/v1/directEditing/open',
				'verb' => 'POST'
			],
			[
				'name' => 'DirectEditing#create',
				'url' => '/api/v1/directEditing/create',
				'verb' => 'POST'
			],
			[
				'name' => 'Template#list',
				'url' => '/api/v1/templates',
				'verb' => 'GET'
			],
			[
				'name' => 'Template#create',
				'url' => '/api/v1/templates/create',
				'verb' => 'POST'
			],
			[
				'name' => 'Template#path',
				'url' => '/api/v1/templates/path',
				'verb' => 'POST'
			],
			[
				'name' => 'TransferOwnership#transfer',
				'url' => '/api/v1/transferownership',
				'verb' => 'POST',
			],
			[
				'name' => 'TransferOwnership#accept',
				'url' => '/api/v1/transferownership/{id}',
				'verb' => 'POST',
			],
			[
				'name' => 'TransferOwnership#reject',
				'url' => '/api/v1/transferownership/{id}',
				'verb' => 'DELETE',
			],
		],
	]
);

/** @var $this \OC\Route\Router */

$this->create('files_ajax_download', 'apps/files/ajax/download.php')
	->actionInclude('files/ajax/download.php');
$this->create('files_ajax_list', 'apps/files/ajax/list.php')
	->actionInclude('files/ajax/list.php');
