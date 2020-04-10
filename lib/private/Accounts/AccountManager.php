<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Björn Schießle
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OC\Accounts;

use OCA\Settings\BackgroundJobs\VerifyUserData;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\IUser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use function json_decode;
use function json_last_error;

/**
 * Class AccountManager
 *
 * Manage system accounts table
 *
 * @group DB
 * @package OC\Accounts
 */
class AccountManager implements IAccountManager {

	/** @var  IDBConnection database connection */
	private $connection;

	/** @var string table name */
	private $table = 'accounts';

	/** @var EventDispatcherInterface */
	private $eventDispatcher;

	/** @var IJobList */
	private $jobList;

	/** @var ILogger */
	private $logger;

	/**
	 * AccountManager constructor.
	 *
	 * @param IDBConnection $connection
	 * @param EventDispatcherInterface $eventDispatcher
	 * @param IJobList $jobList
	 */
	public function __construct(IDBConnection $connection,
								EventDispatcherInterface $eventDispatcher,
								IJobList $jobList,
								ILogger $logger) {
		$this->connection = $connection;
		$this->eventDispatcher = $eventDispatcher;
		$this->jobList = $jobList;
		$this->logger = $logger;
	}

	/**
	 * update user record
	 *
	 * @param IUser $user
	 * @param $data
	 */
	public function updateUser(IUser $user, $data) {
		$userData = $this->getUser($user);
		$updated = true;
		if (empty($userData)) {
			$this->insertNewUser($user, $data);
		} elseif ($userData !== $data) {
			$data = $this->checkEmailVerification($userData, $data, $user);
			$data = $this->updateVerifyStatus($userData, $data);
			$this->updateExistingUser($user, $data);
		} else {
			// nothing needs to be done if new and old data set are the same
			$updated = false;
		}

		if ($updated) {
			$this->eventDispatcher->dispatch(
				'OC\AccountManager::userUpdated',
				new GenericEvent($user, $data)
			);
		}
	}

	/**
	 * delete user from accounts table
	 *
	 * @param IUser $user
	 */
	public function deleteUser(IUser $user) {
		$uid = $user->getUID();
		$query = $this->connection->getQueryBuilder();
		$query->delete($this->table)
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->execute();
	}

	/**
	 * get stored data from a given user
	 *
	 * @param IUser $user
	 * @return array
	 */
	public function getUser(IUser $user) {
		$uid = $user->getUID();
		$query = $this->connection->getQueryBuilder();
		$query->select('data')->from($this->table)
			->where($query->expr()->eq('uid', $query->createParameter('uid')))
			->setParameter('uid', $uid);
		$query->execute();
		$result = $query->execute()->fetchAll();

		if (empty($result)) {
			$userData = $this->buildDefaultUserRecord($user);
			$this->insertNewUser($user, $userData);
			return $userData;
		}

		$userDataArray = json_decode($result[0]['data'], true);
		$jsonError = json_last_error();
		if ($userDataArray === null || $jsonError !== JSON_ERROR_NONE) {
			$this->logger->critical("User data of $uid contained invalid JSON (error $jsonError), hence falling back to a default user record");
			return $this->buildDefaultUserRecord($user);
		}

		$userDataArray = $this->addMissingDefaultValues($userDataArray);

		return $userDataArray;
	}

	/**
	 * check if we need to ask the server for email verification, if yes we create a cronjob
	 *
	 * @param $oldData
	 * @param $newData
	 * @param IUser $user
	 * @return array
	 */
	protected function checkEmailVerification($oldData, $newData, IUser $user) {
		if ($oldData[self::PROPERTY_EMAIL]['value'] !== $newData[self::PROPERTY_EMAIL]['value']) {
			$this->jobList->add(VerifyUserData::class,
				[
					'verificationCode' => '',
					'data' => $newData[self::PROPERTY_EMAIL]['value'],
					'type' => self::PROPERTY_EMAIL,
					'uid' => $user->getUID(),
					'try' => 0,
					'lastRun' => time()
				]
			);
			$newData[AccountManager::PROPERTY_EMAIL]['verified'] = AccountManager::VERIFICATION_IN_PROGRESS;
		}

		return $newData;
	}

	/**
	 * make sure that all expected data are set
	 *
	 * @param array $userData
	 * @return array
	 */
	protected function addMissingDefaultValues(array $userData) {

		foreach ($userData as $key => $value) {
			if (!isset($userData[$key]['verified'])) {
				$userData[$key]['verified'] = self::NOT_VERIFIED;
			}
		}

		return $userData;
	}

	/**
	 * reset verification status if personal data changed
	 *
	 * @param array $oldData
	 * @param array $newData
	 * @return array
	 */
	protected function updateVerifyStatus($oldData, $newData) {

		// which account was already verified successfully?
		$twitterVerified = isset($oldData[self::PROPERTY_TWITTER]['verified']) && $oldData[self::PROPERTY_TWITTER]['verified'] === self::VERIFIED;
		$websiteVerified = isset($oldData[self::PROPERTY_WEBSITE]['verified']) && $oldData[self::PROPERTY_WEBSITE]['verified'] === self::VERIFIED;
		$emailVerified = isset($oldData[self::PROPERTY_EMAIL]['verified']) && $oldData[self::PROPERTY_EMAIL]['verified'] === self::VERIFIED;

		// keep old verification status if we don't have a new one
		if(!isset($newData[self::PROPERTY_TWITTER]['verified'])) {
			// keep old verification status if value didn't changed and an old value exists
			$keepOldStatus = $newData[self::PROPERTY_TWITTER]['value'] === $oldData[self::PROPERTY_TWITTER]['value'] && isset($oldData[self::PROPERTY_TWITTER]['verified']);
			$newData[self::PROPERTY_TWITTER]['verified'] = $keepOldStatus ? $oldData[self::PROPERTY_TWITTER]['verified'] : self::NOT_VERIFIED;
		}

		if(!isset($newData[self::PROPERTY_WEBSITE]['verified'])) {
			// keep old verification status if value didn't changed and an old value exists
			$keepOldStatus = $newData[self::PROPERTY_WEBSITE]['value'] === $oldData[self::PROPERTY_WEBSITE]['value'] && isset($oldData[self::PROPERTY_WEBSITE]['verified']);
			$newData[self::PROPERTY_WEBSITE]['verified'] = $keepOldStatus ? $oldData[self::PROPERTY_WEBSITE]['verified'] : self::NOT_VERIFIED;
		}

		if(!isset($newData[self::PROPERTY_EMAIL]['verified'])) {
			// keep old verification status if value didn't changed and an old value exists
			$keepOldStatus = $newData[self::PROPERTY_EMAIL]['value'] === $oldData[self::PROPERTY_EMAIL]['value'] && isset($oldData[self::PROPERTY_EMAIL]['verified']);
			$newData[self::PROPERTY_EMAIL]['verified'] = $keepOldStatus ? $oldData[self::PROPERTY_EMAIL]['verified'] : self::VERIFICATION_IN_PROGRESS;
		}

		// reset verification status if a value from a previously verified data was changed
		if($twitterVerified &&
			$oldData[self::PROPERTY_TWITTER]['value'] !== $newData[self::PROPERTY_TWITTER]['value']
		) {
			$newData[self::PROPERTY_TWITTER]['verified'] = self::NOT_VERIFIED;
		}

		if($websiteVerified &&
			$oldData[self::PROPERTY_WEBSITE]['value'] !== $newData[self::PROPERTY_WEBSITE]['value']
		) {
			$newData[self::PROPERTY_WEBSITE]['verified'] = self::NOT_VERIFIED;
		}

		if($emailVerified &&
			$oldData[self::PROPERTY_EMAIL]['value'] !== $newData[self::PROPERTY_EMAIL]['value']
		) {
			$newData[self::PROPERTY_EMAIL]['verified'] = self::NOT_VERIFIED;
		}

		return $newData;

	}

	/**
	 * add new user to accounts table
	 *
	 * @param IUser $user
	 * @param array $data
	 */
	protected function insertNewUser(IUser $user, $data) {
		$uid = $user->getUID();
		$jsonEncodedData = json_encode($data);
		$query = $this->connection->getQueryBuilder();
		$query->insert($this->table)
			->values(
				[
					'uid' => $query->createNamedParameter($uid),
					'data' => $query->createNamedParameter($jsonEncodedData),
				]
			)
			->execute();
	}

	/**
	 * update existing user in accounts table
	 *
	 * @param IUser $user
	 * @param array $data
	 */
	protected function updateExistingUser(IUser $user, $data) {
		$uid = $user->getUID();
		$jsonEncodedData = json_encode($data);
		$query = $this->connection->getQueryBuilder();
		$query->update($this->table)
			->set('data', $query->createNamedParameter($jsonEncodedData))
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->execute();
	}

	/**
	 * build default user record in case not data set exists yet
	 *
	 * @param IUser $user
	 * @return array
	 */
	protected function buildDefaultUserRecord(IUser $user) {
		return [
			self::PROPERTY_DISPLAYNAME =>
				[
					'value' => $user->getDisplayName(),
					'scope' => self::VISIBILITY_CONTACTS_ONLY,
					'verified' => self::NOT_VERIFIED,
				],
			self::PROPERTY_ADDRESS =>
				[
					'value' => '',
					'scope' => self::VISIBILITY_PRIVATE,
					'verified' => self::NOT_VERIFIED,
				],
			self::PROPERTY_WEBSITE =>
				[
					'value' => '',
					'scope' => self::VISIBILITY_PRIVATE,
					'verified' => self::NOT_VERIFIED,
				],
			self::PROPERTY_EMAIL =>
				[
					'value' => $user->getEMailAddress(),
					'scope' => self::VISIBILITY_CONTACTS_ONLY,
					'verified' => self::NOT_VERIFIED,
				],
			self::PROPERTY_AVATAR =>
				[
					'scope' => self::VISIBILITY_CONTACTS_ONLY
				],
			self::PROPERTY_PHONE =>
				[
					'value' => '',
					'scope' => self::VISIBILITY_PRIVATE,
					'verified' => self::NOT_VERIFIED,
				],
			self::PROPERTY_TWITTER =>
				[
					'value' => '',
					'scope' => self::VISIBILITY_PRIVATE,
					'verified' => self::NOT_VERIFIED,
				],
		];
	}

	private function parseAccountData(IUser $user, $data): Account {
		$account = new Account($user);
		foreach($data as $property => $accountData) {
			$account->setProperty($property, $accountData['value'] ?? '', $accountData['scope'] ?? self::VISIBILITY_PRIVATE, $accountData['verified'] ?? self::NOT_VERIFIED);
		}
		return $account;
	}

	public function getAccount(IUser $user): IAccount {
		return $this->parseAccountData($user, $this->getUser($user));
	}

}
