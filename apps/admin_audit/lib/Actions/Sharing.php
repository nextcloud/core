<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Sascha Wiswedel <sascha.wiswedel@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\AdminAudit\Actions;

use OCP\Share\IShare;

/**
 * Class Sharing logs the sharing actions
 *
 * @package OCA\AdminAudit\Actions
 */
class Sharing extends Action {
	/**
	 * Logs sharing of data
	 *
	 * @param array $params
	 */
	public function shared(array $params) {
		if ($params['shareType'] === IShare::TYPE_LINK) {
			$this->log(
				'The %s "%s" with ID "%s" has been shared via link with permissions "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'itemTarget',
					'itemSource',
					'permissions',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_USER) {
			$this->log(
				'The %s "%s" with ID "%s" has been shared to the user "%s" with permissions "%s"  (Share ID: %s)',
				$params,
				[
					'itemType',
					'itemTarget',
					'itemSource',
					'shareWith',
					'permissions',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_GROUP) {
			$this->log(
				'The %s "%s" with ID "%s" has been shared to the group "%s" with permissions "%s"  (Share ID: %s)',
				$params,
				[
					'itemType',
					'itemTarget',
					'itemSource',
					'shareWith',
					'permissions',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_ROOM) {
			$this->log(
				'The %s "%s" with ID "%s" has been shared to the room "%s" with permissions "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'itemTarget',
					'itemSource',
					'shareWith',
					'permissions',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_EMAIL) {
			$this->log(
				'The %s "%s" with ID "%s" has been shared to the email recipient "%s" with permissions "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'itemTarget',
					'itemSource',
					'shareWith',
					'permissions',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_CIRCLE) {
			$this->log(
				'The %s "%s" with ID "%s" has been shared to the circle "%s" with permissions "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'itemTarget',
					'itemSource',
					'shareWith',
					'permissions',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_REMOTE) {
			$this->log(
				'The %s "%s" with ID "%s" has been shared to the remote user "%s" with permissions "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'itemTarget',
					'itemSource',
					'shareWith',
					'permissions',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_REMOTE_GROUP) {
			$this->log(
				'The %s "%s" with ID "%s" has been shared to the remote group "%s" with permissions "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'itemTarget',
					'itemSource',
					'shareWith',
					'permissions',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_DECK) {
			$this->log(
				'The %s "%s" with ID "%s" has been shared to the deck card "%s" with permissions "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'itemTarget',
					'itemSource',
					'shareWith',
					'permissions',
					'id',
				]
			);
		}
	}

	/**
	 * Logs unsharing of data
	 *
	 * @param array $params
	 */
	public function unshare(array $params) {
		if ($params['shareType'] === IShare::TYPE_LINK) {
			$this->log(
				'The %s "%s" with ID "%s" has been unshared (Share ID: %s)',
				$params,
				[
					'itemType',
					'fileTarget',
					'itemSource',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_USER) {
			$this->log(
				'The %s "%s" with ID "%s" has been unshared from the user "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'fileTarget',
					'itemSource',
					'shareWith',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_GROUP) {
			$this->log(
				'The %s "%s" with ID "%s" has been unshared from the group "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'fileTarget',
					'itemSource',
					'shareWith',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_ROOM) {
			$this->log(
				'The %s "%s" with ID "%s" has been unshared from the room "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'fileTarget',
					'itemSource',
					'shareWith',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_EMAIL) {
			$this->log(
				'The %s "%s" with ID "%s" has been unshared from the email recipient "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'fileTarget',
					'itemSource',
					'shareWith',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_CIRCLE) {
			$this->log(
				'The %s "%s" with ID "%s" has been unshared from the circle "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'fileTarget',
					'itemSource',
					'shareWith',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_REMOTE) {
			$this->log(
				'The %s "%s" with ID "%s" has been unshared from the remote user "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'fileTarget',
					'itemSource',
					'shareWith',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_REMOTE_GROUP) {
			$this->log(
				'The %s "%s" with ID "%s" has been unshared from the remote group "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'fileTarget',
					'itemSource',
					'shareWith',
					'id',
				]
			);
		} elseif ($params['shareType'] === IShare::TYPE_DECK) {
			$this->log(
				'The %s "%s" with ID "%s" has been unshared from the deck card "%s" (Share ID: %s)',
				$params,
				[
					'itemType',
					'fileTarget',
					'itemSource',
					'shareWith',
					'id',
				]
			);
		}
	}

	/**
	 * Logs the updating of permission changes for shares
	 *
	 * @param array $params
	 */
	public function updatePermissions(array $params) {
		$this->log(
			'The permissions of the shared %s "%s" with ID "%s" have been changed to "%s"',
			$params,
			[
				'itemType',
				'path',
				'itemSource',
				'permissions',
			]
		);
	}

	/**
	 * Logs the password changes for a share
	 *
	 * @param array $params
	 */
	public function updatePassword(array $params) {
		$this->log(
			'The password of the publicly shared %s "%s" with ID "%s" has been changed',
			$params,
			[
				'itemType',
				'token',
				'itemSource',
			]
		);
	}

	/**
	 * Logs the expiration date changes for a share
	 *
	 * @param array $params
	 */
	public function updateExpirationDate(array $params) {
		$this->log(
			'The expiration date of the publicly shared %s with ID "%s" has been changed to "%s"',
			$params,
			[
				'itemType',
				'itemSource',
				'date',
			]
		);
	}

	/**
	 * Logs access of shared files
	 *
	 * @param array $params
	 */
	public function shareAccessed(array $params) {
		$this->log(
			'The shared %s with the token "%s" by "%s" has been accessed.',
			$params,
			[
				'itemType',
				'token',
				'uidOwner',
			]
		);
	}
}
