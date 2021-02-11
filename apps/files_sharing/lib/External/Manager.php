<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Hansson <daniel@techandme.se>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
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

namespace OCA\Files_Sharing\External;

use Doctrine\DBAL\Driver\Exception;
use OC\Files\Filesystem;
use OCA\FederatedFileSharing\Events\FederatedShareAddedEvent;
use OCA\Files_Sharing\Helper;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Files;
use OCP\Files\Storage\IStorageFactory;
use OCP\Http\Client\IClientService;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OCP\OCS\IDiscoveryService;
use OCP\Share;
use OCP\Share\IShare;

class Manager {
	public const STORAGE = '\OCA\Files_Sharing\External\Storage';

	/** @var string|null */
	private $uid;

	/** @var IDBConnection */
	private $connection;

	/** @var \OC\Files\Mount\Manager */
	private $mountManager;

	/** @var IStorageFactory */
	private $storageLoader;

	/** @var IClientService */
	private $clientService;

	/** @var IManager */
	private $notificationManager;

	/** @var IDiscoveryService */
	private $discoveryService;

	/** @var ICloudFederationProviderManager */
	private $cloudFederationProviderManager;

	/** @var ICloudFederationFactory */
	private $cloudFederationFactory;

	/** @var IGroupManager  */
	private $groupManager;

	/** @var IUserManager */
	private $userManager;

	/** @var IEventDispatcher */
	private $eventDispatcher;

	public function __construct(IDBConnection $connection,
								\OC\Files\Mount\Manager $mountManager,
								IStorageFactory $storageLoader,
								IClientService $clientService,
								IManager $notificationManager,
								IDiscoveryService $discoveryService,
								ICloudFederationProviderManager $cloudFederationProviderManager,
								ICloudFederationFactory $cloudFederationFactory,
								IGroupManager $groupManager,
								IUserManager $userManager,
								?string $uid,
								IEventDispatcher $eventDispatcher) {
		$this->connection = $connection;
		$this->mountManager = $mountManager;
		$this->storageLoader = $storageLoader;
		$this->clientService = $clientService;
		$this->uid = $uid;
		$this->notificationManager = $notificationManager;
		$this->discoveryService = $discoveryService;
		$this->cloudFederationProviderManager = $cloudFederationProviderManager;
		$this->cloudFederationFactory = $cloudFederationFactory;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * add new server-to-server share
	 *
	 * @param string $remote
	 * @param string $token
	 * @param string $password
	 * @param string $name
	 * @param string $owner
	 * @param int $shareType
	 * @param boolean $accepted
	 * @param string $user
	 * @param string $remoteId
	 * @param int $parent
	 * @return Mount|null
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function addShare($remote, $token, $password, $name, $owner, $shareType, $accepted = false, $user = null, $remoteId = '', $parent = -1) {
		$user = $user ? $user : $this->uid;
		$accepted = $accepted ? IShare::STATUS_ACCEPTED : IShare::STATUS_PENDING;
		$name = Filesystem::normalizePath('/' . $name);

		if ($accepted !== IShare::STATUS_ACCEPTED) {
			// To avoid conflicts with the mount point generation later,
			// we only use a temporary mount point name here. The real
			// mount point name will be generated when accepting the share,
			// using the original share item name.
			$tmpMountPointName = '{{TemporaryMountPointName#' . $name . '}}';
			$mountPoint = $tmpMountPointName;
			$hash = md5($tmpMountPointName);
			$data = [
				'remote' => $remote,
				'share_token' => $token,
				'password' => $password,
				'name' => $name,
				'owner' => $owner,
				'user' => $user,
				'mountpoint' => $mountPoint,
				'mountpoint_hash' => $hash,
				'accepted' => $accepted,
				'remote_id' => $remoteId,
				'share_type' => $shareType,
			];

			$i = 1;
			while (!$this->connection->insertIfNotExist('*PREFIX*share_external', $data, ['user', 'mountpoint_hash'])) {
				// The external share already exists for the user
				$data['mountpoint'] = $tmpMountPointName . '-' . $i;
				$data['mountpoint_hash'] = md5($data['mountpoint']);
				$i++;
			}
			return null;
		}

		$mountPoint = Files::buildNotExistingFileName('/', $name);
		$mountPoint = Filesystem::normalizePath('/' . $mountPoint);
		$hash = md5($mountPoint);

		$this->writeShareToDb($remote, $token, $password, $name, $owner, $user, $mountPoint, $hash, $accepted, $remoteId, $parent, $shareType);

		$options = [
			'remote' => $remote,
			'token' => $token,
			'password' => $password,
			'mountpoint' => $mountPoint,
			'owner' => $owner
		];
		return $this->mountShare($options);
	}

	/**
	 * write remote share to the database
	 *
	 * @param $remote
	 * @param $token
	 * @param $password
	 * @param $name
	 * @param $owner
	 * @param $user
	 * @param $mountPoint
	 * @param $hash
	 * @param $accepted
	 * @param $remoteId
	 * @param $parent
	 * @param $shareType
	 *
	 * @return void
	 * @throws \Doctrine\DBAL\Driver\Exception
	 */
	private function writeShareToDb($remote, $token, $password, $name, $owner, $user, $mountPoint, $hash, $accepted, $remoteId, $parent, $shareType): void {
		$query = $this->connection->prepare('
				INSERT INTO `*PREFIX*share_external`
					(`remote`, `share_token`, `password`, `name`, `owner`, `user`, `mountpoint`, `mountpoint_hash`, `accepted`, `remote_id`, `parent`, `share_type`)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			');
		$query->execute([$remote, $token, $password, $name, $owner, $user, $mountPoint, $hash, $accepted, $remoteId, $parent, $shareType]);
	}

	/**
	 * get share
	 *
	 * @param int $id share id
	 * @return mixed share of false
	 */
	public function getShare($id) {
		$getShare = $this->connection->prepare('
			SELECT `id`, `remote`, `remote_id`, `share_token`, `name`, `owner`, `user`, `mountpoint`, `accepted`, `parent`, `share_type`, `password`, `mountpoint_hash`
			FROM  `*PREFIX*share_external`
			WHERE `id` = ?');
		$result = $getShare->execute([$id]);
		$share = $result->fetch();
		$result->closeCursor();
		$validShare = is_array($share) && isset($share['share_type']) && isset($share['user']);

		// check if the user is allowed to access it
		if ($validShare && (int)$share['share_type'] === IShare::TYPE_USER && $share['user'] === $this->uid) {
			return $share;
		} elseif ($validShare && (int)$share['share_type'] === IShare::TYPE_GROUP) {
			$user = $this->userManager->get($this->uid);
			if ($this->groupManager->get($share['user'])->inGroup($user)) {
				return $share;
			}
		}

		return false;
	}

	/**
	 * accept server-to-server share
	 *
	 * @param int $id
	 * @return bool True if the share could be accepted, false otherwise
	 */
	public function acceptShare($id) {
		$share = $this->getShare($id);
		$result = false;

		if ($share) {
			\OC_Util::setupFS($this->uid);
			$shareFolder = Helper::getShareFolder();
			$mountPoint = Files::buildNotExistingFileName($shareFolder, $share['name']);
			$mountPoint = Filesystem::normalizePath($mountPoint);
			$hash = md5($mountPoint);
			$userShareAccepted = false;

			if ((int)$share['share_type'] === IShare::TYPE_USER) {
				$acceptShare = $this->connection->prepare('
				UPDATE `*PREFIX*share_external`
				SET `accepted` = ?,
					`mountpoint` = ?,
					`mountpoint_hash` = ?
				WHERE `id` = ? AND `user` = ?');
				$userShareAccepted = $acceptShare->execute([1, $mountPoint, $hash, $id, $this->uid]);
			} else {
				try {
					$this->writeShareToDb(
						$share['remote'],
						$share['share_token'],
						$share['password'],
						$share['name'],
						$share['owner'],
						$this->uid,
						$mountPoint, $hash, 1,
						$share['remote_id'],
						$id,
						$share['share_type']);
					$result = true;
				} catch (Exception $e) {
					$result = false;
				}
			}
			if ($userShareAccepted !== false) {
				$this->sendFeedbackToRemote($share['remote'], $share['share_token'], $share['remote_id'], 'accept');
				$event = new FederatedShareAddedEvent($share['remote']);
				$this->eventDispatcher->dispatchTyped($event);
				$result = true;
			}
		}

		// Make sure the user has no notification for something that does not exist anymore.
		$this->processNotification($id);

		return $result;
	}

	/**
	 * decline server-to-server share
	 *
	 * @param int $id
	 * @return bool True if the share could be declined, false otherwise
	 */
	public function declineShare($id) {
		$share = $this->getShare($id);
		$result = false;

		if ($share && (int)$share['share_type'] === IShare::TYPE_USER) {
			$removeShare = $this->connection->prepare('
				DELETE FROM `*PREFIX*share_external` WHERE `id` = ? AND `user` = ?');
			$removeShare->execute([$id, $this->uid]);
			$this->sendFeedbackToRemote($share['remote'], $share['share_token'], $share['remote_id'], 'decline');

			$this->processNotification($id);
			$result = true;
		} elseif ($share && (int)$share['share_type'] === IShare::TYPE_GROUP) {
			try {
				$this->writeShareToDb(
					$share['remote'],
					$share['share_token'],
					$share['password'],
					$share['name'],
					$share['owner'],
					$this->uid,
					$share['mountpoint'],
					$share['mountpoint_hash'],
					0,
					$share['remote_id'],
					$id,
					$share['share_type']);
				$result = true;
			} catch (Exception $e) {
				$result = false;
			}
			$this->processNotification($id);
		}

		return $result;
	}

	/**
	 * @param int $remoteShare
	 */
	public function processNotification($remoteShare) {
		$filter = $this->notificationManager->createNotification();
		$filter->setApp('files_sharing')
			->setUser($this->uid)
			->setObject('remote_share', (int) $remoteShare);
		$this->notificationManager->markProcessed($filter);
	}

	/**
	 * inform remote server whether server-to-server share was accepted/declined
	 *
	 * @param string $remote
	 * @param string $token
	 * @param string $remoteId Share id on the remote host
	 * @param string $feedback
	 * @return boolean
	 */
	private function sendFeedbackToRemote($remote, $token, $remoteId, $feedback) {
		$result = $this->tryOCMEndPoint($remote, $token, $remoteId, $feedback);

		if ($result === true) {
			return true;
		}

		$federationEndpoints = $this->discoveryService->discover($remote, 'FEDERATED_SHARING');
		$endpoint = isset($federationEndpoints['share']) ? $federationEndpoints['share'] : '/ocs/v2.php/cloud/shares';

		$url = rtrim($remote, '/') . $endpoint . '/' . $remoteId . '/' . $feedback . '?format=' . Share::RESPONSE_FORMAT;
		$fields = ['token' => $token];

		$client = $this->clientService->newClient();

		try {
			$response = $client->post(
				$url,
				[
					'body' => $fields,
					'connect_timeout' => 10,
				]
			);
		} catch (\Exception $e) {
			return false;
		}

		$status = json_decode($response->getBody(), true);

		return ($status['ocs']['meta']['statuscode'] === 100 || $status['ocs']['meta']['statuscode'] === 200);
	}

	/**
	 * try send accept message to ocm end-point
	 *
	 * @param string $remoteDomain
	 * @param string $token
	 * @param string $remoteId id of the share
	 * @param string $feedback
	 * @return bool
	 */
	protected function tryOCMEndPoint($remoteDomain, $token, $remoteId, $feedback) {
		switch ($feedback) {
			case 'accept':
				$notification = $this->cloudFederationFactory->getCloudFederationNotification();
				$notification->setMessage(
					'SHARE_ACCEPTED',
					'file',
					$remoteId,
					[
						'sharedSecret' => $token,
						'message' => 'Recipient accept the share'
					]

				);
				return $this->cloudFederationProviderManager->sendNotification($remoteDomain, $notification);
			case 'decline':
				$notification = $this->cloudFederationFactory->getCloudFederationNotification();
				$notification->setMessage(
					'SHARE_DECLINED',
					'file',
					$remoteId,
					[
						'sharedSecret' => $token,
						'message' => 'Recipient declined the share'
					]

				);
				return $this->cloudFederationProviderManager->sendNotification($remoteDomain, $notification);
		}

		return false;
	}


	/**
	 * remove '/user/files' from the path and trailing slashes
	 *
	 * @param string $path
	 * @return string
	 */
	protected function stripPath($path) {
		$prefix = '/' . $this->uid . '/files';
		return rtrim(substr($path, strlen($prefix)), '/');
	}

	public function getMount($data) {
		$data['manager'] = $this;
		$mountPoint = '/' . $this->uid . '/files' . $data['mountpoint'];
		$data['mountpoint'] = $mountPoint;
		$data['certificateManager'] = \OC::$server->getCertificateManager();
		return new Mount(self::STORAGE, $mountPoint, $data, $this, $this->storageLoader);
	}

	/**
	 * @param array $data
	 * @return Mount
	 */
	protected function mountShare($data) {
		$mount = $this->getMount($data);
		$this->mountManager->addMount($mount);
		return $mount;
	}

	/**
	 * @return \OC\Files\Mount\Manager
	 */
	public function getMountManager() {
		return $this->mountManager;
	}

	/**
	 * @param string $source
	 * @param string $target
	 * @return bool
	 */
	public function setMountPoint($source, $target) {
		$source = $this->stripPath($source);
		$target = $this->stripPath($target);
		$sourceHash = md5($source);
		$targetHash = md5($target);

		$query = $this->connection->prepare('
			UPDATE `*PREFIX*share_external`
			SET `mountpoint` = ?, `mountpoint_hash` = ?
			WHERE `mountpoint_hash` = ?
			AND `user` = ?
		');
		$result = (bool)$query->execute([$target, $targetHash, $sourceHash, $this->uid]);

		return $result;
	}

	public function removeShare($mountPoint): bool {
		$mountPointObj = $this->mountManager->find($mountPoint);
		$id = $mountPointObj->getStorage()->getCache()->getId('');

		$mountPoint = $this->stripPath($mountPoint);
		$hash = md5($mountPoint);

		try {
			$getShare = $this->connection->prepare('
				SELECT `remote`, `share_token`, `remote_id`, `share_type`, `id`
				FROM  `*PREFIX*share_external`
				WHERE `mountpoint_hash` = ? AND `user` = ?');
			$result = $getShare->execute([$hash, $this->uid]);
			$share = $result->fetch();
			$result->closeCursor();
			if ($share !== false && (int)$share['share_type'] === IShare::TYPE_USER) {
				try {
					$this->sendFeedbackToRemote($share['remote'], $share['share_token'], $share['remote_id'], 'decline');
				} catch (\Throwable $e) {
					// if we fail to notify the remote (probably cause the remote is down)
					// we still want the share to be gone to prevent undeletable remotes
				}

				$query = $this->connection->prepare('
				DELETE FROM `*PREFIX*share_external`
				WHERE `id` = ?
				');
				$deleteResult = $query->execute([(int)$share['id']]);
				$deleteResult->closeCursor();
			} elseif ($share !== false && (int)$share['share_type'] === IShare::TYPE_GROUP) {
				$query = $this->connection->prepare('
					UPDATE `*PREFIX*share_external`
					SET `accepted` = ?
					WHERE `id` = ?');
				$updateResult = $query->execute([0, (int)$share['id']]);
				$updateResult->closeCursor();
			}

			$this->removeReShares($id);
		} catch (\Doctrine\DBAL\Exception $ex) {
			return false;
		}

		return true;
	}

	/**
	 * remove re-shares from share table and mapping in the federated_reshares table
	 *
	 * @param $mountPointId
	 */
	protected function removeReShares($mountPointId) {
		$selectQuery = $this->connection->getQueryBuilder();
		$query = $this->connection->getQueryBuilder();
		$selectQuery->select('id')->from('share')
			->where($selectQuery->expr()->eq('file_source', $query->createNamedParameter($mountPointId)));
		$select = $selectQuery->getSQL();


		$query->delete('federated_reshares')
			->where($query->expr()->in('share_id', $query->createFunction('(' . $select . ')')));
		$query->execute();

		$deleteReShares = $this->connection->getQueryBuilder();
		$deleteReShares->delete('share')
			->where($deleteReShares->expr()->eq('file_source', $deleteReShares->createNamedParameter($mountPointId)));
		$deleteReShares->execute();
	}

	/**
	 * remove all shares for user $uid if the user was deleted
	 *
	 * @param string $uid
	 */
	public function removeUserShares($uid): bool {
		try {
			$getShare = $this->connection->prepare('
				SELECT `remote`, `share_token`, `remote_id`
				FROM  `*PREFIX*share_external`
				WHERE `user` = ?');
			$result = $getShare->execute([$uid]);
			$shares = $result->fetchAll();
			$result->closeCursor();
			foreach ($shares as $share) {
				$this->sendFeedbackToRemote($share['remote'], $share['share_token'], $share['remote_id'], 'decline');
			}

			$query = $this->connection->prepare('
				DELETE FROM `*PREFIX*share_external`
				WHERE `user` = ?
			');
			$deleteResult = $query->execute([$uid]);
			$deleteResult->closeCursor();
		} catch (\Doctrine\DBAL\Exception $ex) {
			return false;
		}

		return true;
	}

	/**
	 * return a list of shares which are not yet accepted by the user
	 *
	 * @return array list of open server-to-server shares
	 */
	public function getOpenShares() {
		return $this->getShares(false);
	}

	/**
	 * return a list of shares which are accepted by the user
	 *
	 * @return array list of accepted server-to-server shares
	 */
	public function getAcceptedShares() {
		return $this->getShares(true);
	}

	/**
	 * return a list of shares for the user
	 *
	 * @param bool|null $accepted True for accepted only,
	 *                            false for not accepted,
	 *                            null for all shares of the user
	 * @return array list of open server-to-server shares
	 */
	private function getShares($accepted) {
		$user = $this->userManager->get($this->uid);
		$groups = $this->groupManager->getUserGroups($user);
		$userGroups = [];
		foreach ($groups as $group) {
			$userGroups[] = $group->getGID();
		}

		$query = 'SELECT `id`, `remote`, `remote_id`, `share_token`, `name`, `owner`, `user`, `mountpoint`, `accepted`
		          FROM `*PREFIX*share_external`
				  WHERE (`user` = ? OR `user` IN (?))';
		$parameters = [$this->uid, implode(',',$userGroups)];
		if (!is_null($accepted)) {
			$query .= ' AND `accepted` = ?';
			$parameters[] = (int) $accepted;
		}
		$query .= ' ORDER BY `id` ASC';

		$sharesQuery = $this->connection->prepare($query);
		try {
			$result = $sharesQuery->execute($parameters);
			$shares = $result->fetchAll();
			$result->closeCursor();
			return $shares;
		} catch (\Doctrine\DBAL\Exception $e) {
			return [];
		}
	}
}
