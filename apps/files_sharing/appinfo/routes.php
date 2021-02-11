<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Maxence Lange <maxence@artificial-owl.com>
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

return [
	'resources' => [
		'ExternalShares' => ['url' => '/api/externalShares'],
	],
	'routes' => [
		[
			'name' => 'Share#showShare',
			'url' => '/s/{token}',
			'verb' => 'GET',
			'root' => '',
		],
		[
			'name' => 'Share#showAuthenticate',
			'url' => '/s/{token}/authenticate/{redirect}',
			'verb' => 'GET',
			'root' => '',
		],
		[
			'name' => 'Share#authenticate',
			'url' => '/s/{token}/authenticate/{redirect}',
			'verb' => 'POST',
			'root' => '',
		],
		[
			'name' => 'Share#downloadShare',
			'url' => '/s/{token}/download/{filename}',
			'verb' => 'GET',
			'root' => '',
			'defaults' => ['filename' => '']
		],
		[
			'name' => 'PublicPreview#directLink',
			'url' => '/s/{token}/preview',
			'verb' => 'GET',
			'root' => '',
		],

		[
			'name' => 'externalShares#testRemote',
			'url' => '/testremote',
			'verb' => 'GET'
		],
		[
			'name' => 'PublicPreview#getPreview',
			'url' => '/publicpreview/{token}',
			'verb' => 'GET',
		],
		[
			'name' => 'ShareInfo#info',
			'url' => '/shareinfo',
			'verb' => 'POST',
		],
		[
			'name' => 'Settings#setDefaultAccept',
			'url' => '/settings/defaultAccept',
			'verb' => 'PUT',
		],
		[
			'name' => 'Accept#accept',
			'url' => '/accept/{shareId}',
			'verb' => 'GET',
		],
	],
	'ocs' => [
		/*
		 * OCS Share API
		 */
		[
			'name' => 'ShareAPI#getShares',
			'url' => '/api/v1/shares',
			'verb' => 'GET',
		],
		[
			'name' => 'ShareAPI#getInheritedShares',
			'url' => '/api/v1/shares/inherited',
			'verb' => 'GET',
		],
		[
			'name' => 'ShareAPI#createShare',
			'url' => '/api/v1/shares',
			'verb' => 'POST',
		],
		[
			'name' => 'ShareAPI#pendingShares',
			'url' => '/api/v1/shares/pending',
			'verb' => 'GET',
		],
		[
			'name' => 'ShareAPI#getShare',
			'url' => '/api/v1/shares/{id}',
			'verb' => 'GET',
		],
		[
			'name' => 'ShareAPI#updateShare',
			'url' => '/api/v1/shares/{id}',
			'verb' => 'PUT',
		],
		[
			'name' => 'ShareAPI#deleteShare',
			'url' => '/api/v1/shares/{id}',
			'verb' => 'DELETE',
		],
		[
			'name' => 'ShareAPI#acceptShare',
			'url' => '/api/v1/shares/pending/{id}',
			'verb' => 'POST',
		],
		/*
		 * Deleted Shares
		 */
		[
			'name' => 'DeletedShareAPI#index',
			'url' => '/api/v1/deletedshares',
			'verb' => 'GET',
		],
		[
			'name' => 'DeletedShareAPI#undelete',
			'url' => '/api/v1/deletedshares/{id}',
			'verb' => 'POST',
		],
		/*
		 * OCS Sharee API
		 */
		[
			'name' => 'ShareesAPI#search',
			'url' => '/api/v1/sharees',
			'verb' => 'GET',
		],
		[
			'name' => 'ShareesAPI#findRecommended',
			'url' => '/api/v1/sharees_recommended',
			'verb' => 'GET',
		],
		/*
		 * Remote Shares
		 */
		[
			'name' => 'Remote#getShares',
			'url' => '/api/v1/remote_shares',
			'verb' => 'GET',
		],
		[
			'name' => 'Remote#getOpenShares',
			'url' => '/api/v1/remote_shares/pending',
			'verb' => 'GET',
		],
		[
			'name' => 'Remote#acceptShare',
			'url' => '/api/v1/remote_shares/pending/{id}',
			'verb' => 'POST',
		],
		[
			'name' => 'Remote#declineShare',
			'url' => '/api/v1/remote_shares/pending/{id}',
			'verb' => 'DELETE',
		],
		[
			'name' => 'Remote#getShare',
			'url' => '/api/v1/remote_shares/{id}',
			'verb' => 'GET',
		],
		[
			'name' => 'Remote#unshare',
			'url' => '/api/v1/remote_shares/{id}',
			'verb' => 'DELETE',
		],
	],
];
