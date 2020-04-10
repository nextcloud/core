<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

use OCP\IUser;

/**
 * Class UserManagement logs all user management related actions.
 *
 * @package OCA\AdminAudit\Actions
 */
class UserManagement extends Action {
	/**
	 * Log creation of users
	 *
	 * @param array $params
	 */
	public function create(array $params) {
		$this->log(
			'User created: "%s"',
			$params,
			[
				'uid',
			]
		);
	}

	/**
	 * Log assignments of users (typically user backends)
	 *
	 * @param string $uid
	 */
	public function assign(string $uid) {
		$this->log(
		'UserID assigned: "%s"',
			[ 'uid' => $uid ],
			[ 'uid' ]
		);
	}

	/**
	 * Log deletion of users
	 *
	 * @param array $params
	 */
	public function delete(array $params) {
		$this->log(
			'User deleted: "%s"',
			$params,
			[
				'uid',
			]
		);
	}

	/**
	 * Log unassignments of users (typically user backends, no data removed)
	 *
	 * @param string $uid
	 */
	public function unassign(string $uid) {
		$this->log(
			'UserID unassigned: "%s"',
			[ 'uid' => $uid ],
			[ 'uid' ]
		);
	}

	/**
	 * Log enabling of users
	 *
	 * @param array $params
	 */
	public function change(array $params) {
		switch($params['feature']) {
			case 'enabled':
				$this->log(
					$params['value'] === true
						? 'User enabled: "%s"'
						: 'User disabled: "%s"',
					['user' => $params['user']->getUID()],
					[
						'user',
					]
				);
				break;
			case 'eMailAddress':
				$this->log(
					'Email address changed for user %s',
					['user' => $params['user']->getUID()],
					[
						'user',
					]
				);
				break;
		}
	}

	/**
	 * Logs changing of the user scope
	 *
	 * @param IUser $user
	 */
	public function setPassword(IUser $user) {
		if($user->getBackendClassName() === 'Database') {
			$this->log(
				'Password of user "%s" has been changed',
				[
					'user' => $user->getUID(),
				],
				[
					'user',
				]
			);
		}
	}
}
