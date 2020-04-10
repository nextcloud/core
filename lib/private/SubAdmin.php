<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC;

use OC\Hooks\PublicEmitter;
use OCP\Group\ISubAdmin;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

class SubAdmin extends PublicEmitter implements ISubAdmin {

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IDBConnection */
	private $dbConn;

	/**
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param IDBConnection $dbConn
	 */
	public function __construct(IUserManager $userManager,
								IGroupManager $groupManager,
								IDBConnection $dbConn) {
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->dbConn = $dbConn;

		$this->userManager->listen('\OC\User', 'postDelete', function ($user) {
			$this->post_deleteUser($user);
		});
		$this->groupManager->listen('\OC\Group', 'postDelete', function ($group) {
			$this->post_deleteGroup($group);
		});
	}

	/**
	 * add a SubAdmin
	 * @param IUser $user user to be SubAdmin
	 * @param IGroup $group group $user becomes subadmin of
	 */
	public function createSubAdmin(IUser $user, IGroup $group): void {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('group_admin')
			->values([
				'gid' => $qb->createNamedParameter($group->getGID()),
				'uid' => $qb->createNamedParameter($user->getUID())
			])
			->execute();

		$this->emit('\OC\SubAdmin', 'postCreateSubAdmin', [$user, $group]);
		\OC_Hook::emit("OC_SubAdmin", "post_createSubAdmin", ["gid" => $group->getGID()]);
	}

	/**
	 * delete a SubAdmin
	 * @param IUser $user the user that is the SubAdmin
	 * @param IGroup $group the group
	 */
	public function deleteSubAdmin(IUser $user, IGroup $group): void {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->delete('group_admin')
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($group->getGID())))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($user->getUID())))
			->execute();

		$this->emit('\OC\SubAdmin', 'postDeleteSubAdmin', [$user, $group]);
		\OC_Hook::emit("OC_SubAdmin", "post_deleteSubAdmin", ["gid" => $group->getGID()]);
	}

	/**
	 * get groups of a SubAdmin
	 * @param IUser $user the SubAdmin
	 * @return IGroup[]
	 */
	public function getSubAdminsGroups(IUser $user): array {
		$qb = $this->dbConn->getQueryBuilder();

		$result = $qb->select('gid')
			->from('group_admin')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($user->getUID())))
			->execute();

		$groups = [];
		while($row = $result->fetch()) {
			$group = $this->groupManager->get($row['gid']);
			if(!is_null($group)) {
				$groups[$group->getGID()] = $group;
			}
		}
		$result->closeCursor();

		return $groups;
	}

	/**
	 * get an array of groupid and displayName for a user
	 * @param IUser $user
	 * @return array ['displayName' => displayname]
	 */
	public function getSubAdminsGroupsName(IUser $user): array {
		return array_map(function ($group) {
			return ['displayName' => $group->getDisplayName()];
		}, $this->getSubAdminsGroups($user));
	}

	/**
	 * get SubAdmins of a group
	 * @param IGroup $group the group
	 * @return IUser[]
	 */
	public function getGroupsSubAdmins(IGroup $group): array {
		$qb = $this->dbConn->getQueryBuilder();

		$result = $qb->select('uid')
			->from('group_admin')
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($group->getGID())))
			->execute();

		$users = [];
		while($row = $result->fetch()) {
			$user = $this->userManager->get($row['uid']);
			if(!is_null($user)) {
				$users[] = $user;
			}
		}
		$result->closeCursor();

		return $users;
	}

	/**
	 * get all SubAdmins
	 * @return array
	 */
	public function getAllSubAdmins(): array {
		$qb = $this->dbConn->getQueryBuilder();

		$result = $qb->select('*')
			->from('group_admin')
			->execute();

		$subadmins = [];
		while($row = $result->fetch()) {
			$user = $this->userManager->get($row['uid']);
			$group = $this->groupManager->get($row['gid']);
			if(!is_null($user) && !is_null($group)) {
				$subadmins[] = [
					'user'  => $user,
					'group' => $group
				];
			}
		}
		$result->closeCursor();

		return $subadmins;
	}

	/**
	 * checks if a user is a SubAdmin of a group
	 * @param IUser $user
	 * @param IGroup $group
	 * @return bool
	 */
	public function isSubAdminOfGroup(IUser $user, IGroup $group): bool {
		$qb = $this->dbConn->getQueryBuilder();

		/*
		 * Primary key is ('gid', 'uid') so max 1 result possible here
		 */
		$result = $qb->select('*')
			->from('group_admin')
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($group->getGID())))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($user->getUID())))
			->execute();

		$fetch =  $result->fetch();
		$result->closeCursor();
		$result = !empty($fetch) ? true : false;

		return $result;
	}

	/**
	 * checks if a user is a SubAdmin
	 * @param IUser $user
	 * @return bool
	 */
	public function isSubAdmin(IUser $user): bool {
		// Check if the user is already an admin
		if ($this->groupManager->isAdmin($user->getUID())) {
			return true;
		}

		$qb = $this->dbConn->getQueryBuilder();

		$result = $qb->select('gid')
			->from('group_admin')
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($user->getUID())))
			->setMaxResults(1)
			->execute();

		$isSubAdmin = $result->fetch();
		$result->closeCursor();

		return $isSubAdmin !== false;
	}

	/**
	 * checks if a user is a accessible by a subadmin
	 * @param IUser $subadmin
	 * @param IUser $user
	 * @return bool
	 */
	public function isUserAccessible(IUser $subadmin, IUser $user): bool {
		if(!$this->isSubAdmin($subadmin)) {
			return false;
		}
		if($this->groupManager->isAdmin($user->getUID())) {
			return false;
		}
		$accessibleGroups = $this->getSubAdminsGroups($subadmin);
		foreach($accessibleGroups as $accessibleGroup) {
			if($accessibleGroup->inGroup($user)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * delete all SubAdmins by $user
	 * @param IUser $user
	 */
	private function post_deleteUser(IUser $user) {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->delete('group_admin')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($user->getUID())))
			->execute();
	}

	/**
	 * delete all SubAdmins by $group
	 * @param IGroup $group
	 */
	private function post_deleteGroup(IGroup $group) {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->delete('group_admin')
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($group->getGID())))
			->execute();
	}
}
