<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * Class Sharing logs the sharing actions
 *
 * @package OCA\AdminAudit\Actions
 */
class Security extends Action {
	/**
	 * Log twofactor auth enabled
	 *
	 * @param IUser $user
	 * @param array $params
	 */
	public function twofactorFailed(IUser $user, array $params) {
		$params['uid'] = $user->getUID();
		$params['displayName'] = $user->getDisplayName();

		$this->log(
			'Failed two factor attempt by user %s (%s) with provider %s',
			$params,
			[
				'displayName',
				'uid',
				'provider',
			]
		);
	}

	/**
	 * Logs unsharing of data
	 *
	 * @param IUser $user
	 * @param array $params
	 */
	public function twofactorSuccess(IUser $user, array $params) {
		$params['uid'] = $user->getUID();
		$params['displayName'] = $user->getDisplayName();

		$this->log(
			'Successful two factor attempt by user %s (%s) with provider %s',
			$params,
			[
				'displayName',
				'uid',
				'provider',
			]
		);
	}
}
