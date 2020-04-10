<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roger Szabo <roger.szabo@web.de>
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

use OCP\IGroup;
use OCP\IUser;

/**
 * Class GroupManagement logs all group manager related events
 *
 * @package OCA\AdminAudit\Actions
 */
class GroupManagement extends Action {

	/**
	 * log add user to group event
	 *
	 * @param IGroup $group
	 * @param IUser $user
	 */
	public function addUser(IGroup $group, IUser $user) {
		$this->log('User "%s" added to group "%s"',
			[
				'group' => $group->getGID(),
				'user' => $user->getUID()
			],
			[
				'user', 'group'
			]
		);
	}

	/**
	 * log remove user from group event
	 *
	 * @param IGroup $group
	 * @param IUser $user
	 */
	public function removeUser(IGroup $group, IUser $user) {
		$this->log('User "%s" removed from group "%s"',
			[
				'group' => $group->getGID(),
				'user' => $user->getUID()
			],
			[
				'user', 'group'
			]
		);
	}
	
	/**
	 * log create group to group event
	 *
	 * @param IGroup $group
	 */
	public function createGroup(IGroup $group) {
		$this->log('Group created: "%s"',
			[
				'group' => $group->getGID()
			],
			[
				'group'
			]
		);
	}

	/**
	 * log delete group to group event
	 *
	 * @param IGroup $group
	 */
	public function deleteGroup(IGroup $group) {
		$this->log('Group deleted: "%s"',
			[
				'group' => $group->getGID()
			],
			[
				'group'
			]
		);
	}
}
