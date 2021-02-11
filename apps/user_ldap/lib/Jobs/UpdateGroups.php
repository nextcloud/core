<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCA\User_LDAP\Jobs;

use OC\BackgroundJob\TimedJob;
use OCA\User_LDAP\Group_Proxy;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class UpdateGroups extends TimedJob {
	private $groupsFromDB;

	/** @var Group_Proxy */
	private $groupBackend;
	/** @var IEventDispatcher */
	private $dispatcher;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IUserManager */
	private $userManager;
	/** @var LoggerInterface */
	private $logger;
	/** @var IDBConnection */
	private $dbc;

	public function __construct(
		Group_Proxy $groupBackend,
		IEventDispatcher $dispatcher,
		IGroupManager $groupManager,
		IUserManager $userManager,
		LoggerInterface $logger,
		IDBConnection $dbc
	) {
		$this->interval = $this->getRefreshInterval();
		$this->groupBackend = $groupBackend;
		$this->dispatcher = $dispatcher;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->dbc = $dbc;
	}

	/**
	 * @return int
	 */
	private function getRefreshInterval() {
		//defaults to every hour
		return \OC::$server->getConfig()->getAppValue('user_ldap', 'bgjRefreshInterval', 3600);
	}

	/**
	 * @param mixed $argument
	 */
	public function run($argument) {
		$this->updateGroups();
	}

	public function updateGroups() {
		\OCP\Util::writeLog('user_ldap', 'Run background job "updateGroups"', ILogger::DEBUG);

		$knownGroups = array_keys($this->getKnownGroups());
		$actualGroups = $this->groupBackend->getGroups();

		if (empty($actualGroups) && empty($knownGroups)) {
			\OCP\Util::writeLog('user_ldap',
				'bgJ "updateGroups" – groups do not seem to be configured properly, aborting.',
				ILogger::INFO);
			return;
		}

		$this->handleKnownGroups(array_intersect($actualGroups, $knownGroups));
		$this->handleCreatedGroups(array_diff($actualGroups, $knownGroups));
		$this->handleRemovedGroups(array_diff($knownGroups, $actualGroups));

		\OCP\Util::writeLog('user_ldap', 'bgJ "updateGroups" – Finished.', ILogger::DEBUG);
	}

	/**
	 * @return array
	 */
	private function getKnownGroups() {
		if (is_array($this->groupsFromDB)) {
			$this->groupsFromDB;
		}
		$qb = $this->dbc->getQueryBuilder();
		$qb->select(['owncloudname', 'owncloudusers'])
			->from('ldap_group_members');

		$qResult = $qb->execute();
		$result = $qResult->fetchAll();
		$qResult->closeCursor();

		$this->groupsFromDB = [];
		foreach ($result as $dataset) {
			$this->groupsFromDB[$dataset['owncloudname']] = $dataset;
		}

		return $this->groupsFromDB;
	}

	private function handleKnownGroups(array $groups) {
		$this->logger->debug(
			'bgJ "updateGroups" – Dealing with known Groups.',
			['app' => 'user_ldap']
		);
		$qb = $this->dbc->getQueryBuilder();
		$qb->update('ldap_group_members')
			->set('owncloudusers', $qb->createParameter('members'))
			->where($qb->expr()->eq('owncloudname', $qb->createParameter('groupId')));

		if (!is_array($this->groupsFromDB)) {
			$this->getKnownGroups();
		}
		foreach ($groups as $group) {
			$knownUsers = unserialize($this->groupsFromDB[$group]['owncloudusers']);
			$actualUsers = $this->groupBackend->usersInGroup($group);
			$hasChanged = false;

			$groupObject = $this->groupManager->get($group);
			foreach (array_diff($knownUsers, $actualUsers) as $removedUser) {
				$userObject = $this->userManager->get($removedUser);
				if ($userObject instanceof IUser) {
					$this->dispatcher->dispatchTyped(new UserRemovedEvent($groupObject, $userObject));
				}
				$this->logger->info(
					'bgJ "updateGroups" – {user} removed from {group}',
					[
						'app' => 'user_ldap',
						'user' => $removedUser,
						'group' => $group
					]
				);
				$hasChanged = true;
			}
			foreach (array_diff($actualUsers, $knownUsers) as $addedUser) {
				$userObject = $this->userManager->get($addedUser);
				if ($userObject instanceof IUser) {
					$this->dispatcher->dispatchTyped(new UserAddedEvent($groupObject, $userObject));
				}
				$this->logger->info(
					'bgJ "updateGroups" – {user} added to {group}',
					[
						'app' => 'user_ldap',
						'user' => $addedUser,
						'group' => $group
					]
				);
				$hasChanged = true;
			}
			if ($hasChanged) {
				$qb->setParameters([
					'members' => serialize($actualUsers),
					'groupId' => $group
				]);
				$qb->execute();
			}
		}
		$this->logger->debug(
			'bgJ "updateGroups" – FINISHED dealing with known Groups.',
			['app' => 'user_ldap']
		);
	}

	/**
	 * @param string[] $createdGroups
	 */
	private function handleCreatedGroups($createdGroups) {
		\OCP\Util::writeLog('user_ldap', 'bgJ "updateGroups" – dealing with created Groups.', ILogger::DEBUG);

		$query = $this->dbc->getQueryBuilder();
		$query->insert('ldap_group_members')
			->setValue('owncloudname', $query->createParameter('owncloudname'))
			->setValue('owncloudusers', $query->createParameter('owncloudusers'));
		foreach ($createdGroups as $createdGroup) {
			\OCP\Util::writeLog('user_ldap',
				'bgJ "updateGroups" – new group "' . $createdGroup . '" found.',
				ILogger::INFO);
			$users = serialize($this->groupBackend->usersInGroup($createdGroup));

			$query->setParameter('owncloudname', $createdGroup)
				->setParameter('owncloudusers', $users);
			$query->execute();
		}
		\OCP\Util::writeLog('user_ldap',
			'bgJ "updateGroups" – FINISHED dealing with created Groups.',
			ILogger::DEBUG);
	}

	/**
	 * @param string[] $removedGroups
	 */
	private function handleRemovedGroups($removedGroups) {
		\OCP\Util::writeLog('user_ldap', 'bgJ "updateGroups" – dealing with removed groups.', ILogger::DEBUG);

		$query = $this->dbc->getQueryBuilder();
		$query->delete('ldap_group_members')
			->where($query->expr()->eq('owncloudname', $query->createParameter('owncloudname')));

		foreach ($removedGroups as $removedGroup) {
			\OCP\Util::writeLog('user_ldap',
				'bgJ "updateGroups" – group "' . $removedGroup . '" was removed.',
				ILogger::INFO);
			$query->setParameter('owncloudname', $removedGroup);
			$query->execute();
		}
		\OCP\Util::writeLog('user_ldap',
			'bgJ "updateGroups" – FINISHED dealing with removed groups.',
			ILogger::DEBUG);
	}
}
