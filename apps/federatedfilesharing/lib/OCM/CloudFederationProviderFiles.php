<?php
/**
 * @copyright Copyright (c) 2018 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\FederatedFileSharing\OCM;

use OC\AppFramework\Http;
use OC\Files\Filesystem;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\FederatedShareProvider;
use OCA\Files_Sharing\Activity\Providers\RemoteShares;
use OCP\Activity\IManager as IActivityManager;
use OCP\App\IAppManager;
use OCP\Constants;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\Exceptions\ActionNotSupportedException;
use OCP\Federation\Exceptions\AuthenticationFailedException;
use OCP\Federation\Exceptions\BadRequestException;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProvider;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudIdManager;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\Util;

class CloudFederationProviderFiles implements ICloudFederationProvider {

	/** @var IAppManager */
	private $appManager;

	/** @var FederatedShareProvider */
	private $federatedShareProvider;

	/** @var AddressHandler */
	private $addressHandler;

	/** @var ILogger */
	private $logger;

	/** @var IUserManager */
	private $userManager;

	/** @var IManager */
	private $shareManager;

	/** @var ICloudIdManager */
	private $cloudIdManager;

	/** @var IActivityManager */
	private $activityManager;

	/** @var INotificationManager */
	private $notificationManager;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ICloudFederationFactory */
	private $cloudFederationFactory;

	/** @var ICloudFederationProviderManager */
	private $cloudFederationProviderManager;

	/** @var IDBConnection */
	private $connection;

	/** @var IGroupManager */
	private $groupManager;

	/**
	 * CloudFederationProvider constructor.
	 *
	 * @param IAppManager $appManager
	 * @param FederatedShareProvider $federatedShareProvider
	 * @param AddressHandler $addressHandler
	 * @param ILogger $logger
	 * @param IUserManager $userManager
	 * @param IManager $shareManager
	 * @param ICloudIdManager $cloudIdManager
	 * @param IActivityManager $activityManager
	 * @param INotificationManager $notificationManager
	 * @param IURLGenerator $urlGenerator
	 * @param ICloudFederationFactory $cloudFederationFactory
	 * @param ICloudFederationProviderManager $cloudFederationProviderManager
	 * @param IDBConnection $connection
	 * @param IGroupManager $groupManager
	 */
	public function __construct(IAppManager $appManager,
								FederatedShareProvider $federatedShareProvider,
								AddressHandler $addressHandler,
								ILogger $logger,
								IUserManager $userManager,
								IManager $shareManager,
								ICloudIdManager $cloudIdManager,
								IActivityManager $activityManager,
								INotificationManager $notificationManager,
								IURLGenerator $urlGenerator,
								ICloudFederationFactory $cloudFederationFactory,
								ICloudFederationProviderManager $cloudFederationProviderManager,
								IDBConnection $connection,
								IGroupManager $groupManager
	) {
		$this->appManager = $appManager;
		$this->federatedShareProvider = $federatedShareProvider;
		$this->addressHandler = $addressHandler;
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;
		$this->cloudIdManager = $cloudIdManager;
		$this->activityManager = $activityManager;
		$this->notificationManager = $notificationManager;
		$this->urlGenerator = $urlGenerator;
		$this->cloudFederationFactory = $cloudFederationFactory;
		$this->cloudFederationProviderManager = $cloudFederationProviderManager;
		$this->connection = $connection;
		$this->groupManager = $groupManager;
	}



	/**
	 * @return string
	 */
	public function getShareType() {
		return 'file';
	}

	/**
	 * share received from another server
	 *
	 * @param ICloudFederationShare $share
	 * @return string provider specific unique ID of the share
	 *
	 * @throws ProviderCouldNotAddShareException
	 * @throws \OCP\AppFramework\QueryException
	 * @throws \OC\HintException
	 * @since 14.0.0
	 */
	public function shareReceived(ICloudFederationShare $share) {
		if (!$this->isS2SEnabled(true)) {
			throw new ProviderCouldNotAddShareException('Server does not support federated cloud sharing', '', Http::STATUS_SERVICE_UNAVAILABLE);
		}

		$protocol = $share->getProtocol();
		if ($protocol['name'] !== 'webdav') {
			throw new ProviderCouldNotAddShareException('Unsupported protocol for data exchange.', '', Http::STATUS_NOT_IMPLEMENTED);
		}

		list($ownerUid, $remote) = $this->addressHandler->splitUserRemote($share->getOwner());
		// for backward compatibility make sure that the remote url stored in the
		// database ends with a trailing slash
		if (substr($remote, -1) !== '/') {
			$remote = $remote . '/';
		}

		$token = $share->getShareSecret();
		$name = $share->getResourceName();
		$owner = $share->getOwnerDisplayName();
		$sharedBy = $share->getSharedByDisplayName();
		$shareWith = $share->getShareWith();
		$remoteId = $share->getProviderId();
		$sharedByFederatedId = $share->getSharedBy();
		$ownerFederatedId = $share->getOwner();
		$shareType = $this->mapShareTypeToNextcloud($share->getShareType());

		// if no explicit information about the person who created the share was send
		// we assume that the share comes from the owner
		if ($sharedByFederatedId === null) {
			$sharedBy = $owner;
			$sharedByFederatedId = $ownerFederatedId;
		}

		if ($remote && $token && $name && $owner && $remoteId && $shareWith) {
			if (!Util::isValidFileName($name)) {
				throw new ProviderCouldNotAddShareException('The mountpoint name contains invalid characters.', '', Http::STATUS_BAD_REQUEST);
			}

			// FIXME this should be a method in the user management instead
			if ($shareType === IShare::TYPE_USER) {
				$this->logger->debug('shareWith before, ' . $shareWith, ['app' => 'files_sharing']);
				Util::emitHook(
					'\OCA\Files_Sharing\API\Server2Server',
					'preLoginNameUsedAsUserName',
					['uid' => &$shareWith]
				);
				$this->logger->debug('shareWith after, ' . $shareWith, ['app' => 'files_sharing']);

				if (!$this->userManager->userExists($shareWith)) {
					throw new ProviderCouldNotAddShareException('User does not exists', '',Http::STATUS_BAD_REQUEST);
				}

				\OC_Util::setupFS($shareWith);
			}

			if ($shareType === IShare::TYPE_GROUP && !$this->groupManager->groupExists($shareWith)) {
				throw new ProviderCouldNotAddShareException('Group does not exists', '',Http::STATUS_BAD_REQUEST);
			}

			$externalManager = new \OCA\Files_Sharing\External\Manager(
				\OC::$server->getDatabaseConnection(),
				Filesystem::getMountManager(),
				Filesystem::getLoader(),
				\OC::$server->getHTTPClientService(),
				\OC::$server->getNotificationManager(),
				\OC::$server->query(\OCP\OCS\IDiscoveryService::class),
				\OC::$server->getCloudFederationProviderManager(),
				\OC::$server->getCloudFederationFactory(),
				\OC::$server->getGroupManager(),
				\OC::$server->getUserManager(),
				$shareWith,
				\OC::$server->query(IEventDispatcher::class)
			);

			try {
				$externalManager->addShare($remote, $token, '', $name, $owner, $shareType,false, $shareWith, $remoteId);
				$shareId = \OC::$server->getDatabaseConnection()->lastInsertId('*PREFIX*share_external');

				if ($shareType === IShare::TYPE_USER) {
					$event = $this->activityManager->generateEvent();
					$event->setApp('files_sharing')
						->setType('remote_share')
						->setSubject(RemoteShares::SUBJECT_REMOTE_SHARE_RECEIVED, [$ownerFederatedId, trim($name, '/')])
						->setAffectedUser($shareWith)
						->setObject('remote_share', (int)$shareId, $name);
					\OC::$server->getActivityManager()->publish($event);
					$this->notifyAboutNewShare($shareWith, $shareId, $ownerFederatedId, $sharedByFederatedId, $name);
				} else {
					$groupMembers = $this->groupManager->get($shareWith)->getUsers();
					foreach ($groupMembers as $user) {
						$event = $this->activityManager->generateEvent();
						$event->setApp('files_sharing')
							->setType('remote_share')
							->setSubject(RemoteShares::SUBJECT_REMOTE_SHARE_RECEIVED, [$ownerFederatedId, trim($name, '/')])
							->setAffectedUser($user->getUID())
							->setObject('remote_share', (int)$shareId, $name);
						\OC::$server->getActivityManager()->publish($event);
						$this->notifyAboutNewShare($user->getUID(), $shareId, $ownerFederatedId, $sharedByFederatedId, $name);
					}
				}
				return $shareId;
			} catch (\Exception $e) {
				$this->logger->logException($e, [
					'message' => 'Server can not add remote share.',
					'level' => ILogger::ERROR,
					'app' => 'files_sharing'
				]);
				throw new ProviderCouldNotAddShareException('internal server error, was not able to add share from ' . $remote, '', HTTP::STATUS_INTERNAL_SERVER_ERROR);
			}
		}

		throw new ProviderCouldNotAddShareException('server can not add remote share, missing parameter', '', HTTP::STATUS_BAD_REQUEST);
	}

	/**
	 * notification received from another server
	 *
	 * @param string $notificationType (e.g. SHARE_ACCEPTED)
	 * @param string $providerId id of the share
	 * @param array $notification payload of the notification
	 * @return array data send back to the sender
	 *
	 * @throws ActionNotSupportedException
	 * @throws AuthenticationFailedException
	 * @throws BadRequestException
	 * @throws \OC\HintException
	 * @since 14.0.0
	 */
	public function notificationReceived($notificationType, $providerId, array $notification) {
		switch ($notificationType) {
			case 'SHARE_ACCEPTED':
				return $this->shareAccepted($providerId, $notification);
			case 'SHARE_DECLINED':
				return $this->shareDeclined($providerId, $notification);
			case 'SHARE_UNSHARED':
				return $this->unshare($providerId, $notification);
			case 'REQUEST_RESHARE':
				return $this->reshareRequested($providerId, $notification);
			case 'RESHARE_UNDO':
				return $this->undoReshare($providerId, $notification);
			case 'RESHARE_CHANGE_PERMISSION':
				return $this->updateResharePermissions($providerId, $notification);
		}


		throw new BadRequestException([$notificationType]);
	}

	/**
	 * map OCM share type (strings) to Nextcloud internal share types (integer)
	 *
	 * @param string $shareType
	 * @return int
	 */
	private function mapShareTypeToNextcloud($shareType) {
		$result = IShare::TYPE_USER;
		if ($shareType === 'group') {
			$result = IShare::TYPE_GROUP;
		}

		return $result;
	}

	private function notifyAboutNewShare($shareWith, $shareId, $ownerFederatedId, $sharedByFederatedId, $name): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('files_sharing')
			->setUser($shareWith)
			->setDateTime(new \DateTime())
			->setObject('remote_share', $shareId)
			->setSubject('remote_share', [$ownerFederatedId, $sharedByFederatedId, trim($name, '/')]);

		$declineAction = $notification->createAction();
		$declineAction->setLabel('decline')
			->setLink($this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkTo('', 'ocs/v2.php/apps/files_sharing/api/v1/remote_shares/pending/' . $shareId)), 'DELETE');
		$notification->addAction($declineAction);

		$acceptAction = $notification->createAction();
		$acceptAction->setLabel('accept')
			->setLink($this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkTo('', 'ocs/v2.php/apps/files_sharing/api/v1/remote_shares/pending/' . $shareId)), 'POST');
		$notification->addAction($acceptAction);

		$this->notificationManager->notify($notification);
	}

	/**
	 * process notification that the recipient accepted a share
	 *
	 * @param string $id
	 * @param array $notification
	 * @return array
	 * @throws ActionNotSupportedException
	 * @throws AuthenticationFailedException
	 * @throws BadRequestException
	 * @throws \OC\HintException
	 */
	private function shareAccepted($id, array $notification) {
		if (!$this->isS2SEnabled()) {
			throw new ActionNotSupportedException('Server does not support federated cloud sharing');
		}

		if (!isset($notification['sharedSecret'])) {
			throw new BadRequestException(['sharedSecret']);
		}

		$token = $notification['sharedSecret'];

		$share = $this->federatedShareProvider->getShareById($id);

		$this->verifyShare($share, $token);
		$this->executeAcceptShare($share);
		if ($share->getShareOwner() !== $share->getSharedBy()) {
			list(, $remote) = $this->addressHandler->splitUserRemote($share->getSharedBy());
			$remoteId = $this->federatedShareProvider->getRemoteId($share);
			$notification = $this->cloudFederationFactory->getCloudFederationNotification();
			$notification->setMessage(
				'SHARE_ACCEPTED',
				'file',
				$remoteId,
				[
					'sharedSecret' => $token,
					'message' => 'Recipient accepted the re-share'
				]

			);
			$this->cloudFederationProviderManager->sendNotification($remote, $notification);
		}

		return [];
	}

	/**
	 * @param IShare $share
	 * @throws ShareNotFound
	 */
	protected function executeAcceptShare(IShare $share) {
		try {
			$fileId = (int)$share->getNode()->getId();
			list($file, $link) = $this->getFile($this->getCorrectUid($share), $fileId);
		} catch (\Exception $e) {
			throw new ShareNotFound();
		}

		$event = $this->activityManager->generateEvent();
		$event->setApp('files_sharing')
			->setType('remote_share')
			->setAffectedUser($this->getCorrectUid($share))
			->setSubject(RemoteShares::SUBJECT_REMOTE_SHARE_ACCEPTED, [$share->getSharedWith(), [$fileId => $file]])
			->setObject('files', $fileId, $file)
			->setLink($link);
		$this->activityManager->publish($event);
	}

	/**
	 * process notification that the recipient declined a share
	 *
	 * @param string $id
	 * @param array $notification
	 * @return array
	 * @throws ActionNotSupportedException
	 * @throws AuthenticationFailedException
	 * @throws BadRequestException
	 * @throws ShareNotFound
	 * @throws \OC\HintException
	 *
	 */
	protected function shareDeclined($id, array $notification) {
		if (!$this->isS2SEnabled()) {
			throw new ActionNotSupportedException('Server does not support federated cloud sharing');
		}

		if (!isset($notification['sharedSecret'])) {
			throw new BadRequestException(['sharedSecret']);
		}

		$token = $notification['sharedSecret'];

		$share = $this->federatedShareProvider->getShareById($id);

		$this->verifyShare($share, $token);

		if ($share->getShareOwner() !== $share->getSharedBy()) {
			list(, $remote) = $this->addressHandler->splitUserRemote($share->getSharedBy());
			$remoteId = $this->federatedShareProvider->getRemoteId($share);
			$notification = $this->cloudFederationFactory->getCloudFederationNotification();
			$notification->setMessage(
				'SHARE_DECLINED',
				'file',
				$remoteId,
				[
					'sharedSecret' => $token,
					'message' => 'Recipient declined the re-share'
				]

			);
			$this->cloudFederationProviderManager->sendNotification($remote, $notification);
		}

		$this->executeDeclineShare($share);

		return [];
	}

	/**
	 * delete declined share and create a activity
	 *
	 * @param IShare $share
	 * @throws ShareNotFound
	 */
	protected function executeDeclineShare(IShare $share) {
		$this->federatedShareProvider->removeShareFromTable($share);

		try {
			$fileId = (int)$share->getNode()->getId();
			list($file, $link) = $this->getFile($this->getCorrectUid($share), $fileId);
		} catch (\Exception $e) {
			throw new ShareNotFound();
		}

		$event = $this->activityManager->generateEvent();
		$event->setApp('files_sharing')
			->setType('remote_share')
			->setAffectedUser($this->getCorrectUid($share))
			->setSubject(RemoteShares::SUBJECT_REMOTE_SHARE_DECLINED, [$share->getSharedWith(), [$fileId => $file]])
			->setObject('files', $fileId, $file)
			->setLink($link);
		$this->activityManager->publish($event);
	}

	/**
	 * received the notification that the owner unshared a file from you
	 *
	 * @param string $id
	 * @param array $notification
	 * @return array
	 * @throws AuthenticationFailedException
	 * @throws BadRequestException
	 */
	private function undoReshare($id, array $notification) {
		if (!isset($notification['sharedSecret'])) {
			throw new BadRequestException(['sharedSecret']);
		}
		$token = $notification['sharedSecret'];

		$share = $this->federatedShareProvider->getShareById($id);

		$this->verifyShare($share, $token);
		$this->federatedShareProvider->removeShareFromTable($share);
		return [];
	}

	/**
	 * unshare file from self
	 *
	 * @param string $id
	 * @param array $notification
	 * @return array
	 * @throws ActionNotSupportedException
	 * @throws BadRequestException
	 */
	private function unshare($id, array $notification) {
		if (!$this->isS2SEnabled(true)) {
			throw new ActionNotSupportedException("incoming shares disabled!");
		}

		if (!isset($notification['sharedSecret'])) {
			throw new BadRequestException(['sharedSecret']);
		}
		$token = $notification['sharedSecret'];

		$qb = $this->connection->getQueryBuilder();
		$qb->select('*')
			->from('share_external')
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('remote_id', $qb->createNamedParameter($id)),
					$qb->expr()->eq('share_token', $qb->createNamedParameter($token))
				)
			);

		$result = $qb->execute();
		$share = $result->fetch();
		$result->closeCursor();

		if ($token && $id && !empty($share)) {
			$remote = $this->cleanupRemote($share['remote']);

			$owner = $this->cloudIdManager->getCloudId($share['owner'], $remote);
			$mountpoint = $share['mountpoint'];
			$user = $share['user'];

			$qb = $this->connection->getQueryBuilder();
			$qb->delete('share_external')
				->where(
					$qb->expr()->andX(
						$qb->expr()->eq('remote_id', $qb->createNamedParameter($id)),
						$qb->expr()->eq('share_token', $qb->createNamedParameter($token))
					)
				);

			$qb->execute();

			// delete all child in case of a group share
			$qb = $this->connection->getQueryBuilder();
			$qb->delete('share_external')
				->where($qb->expr()->eq('parent', $qb->createNamedParameter((int)$share['id'])));
			$qb->execute();

			if ((int)$share['share_type'] === IShare::TYPE_USER) {
				if ($share['accepted']) {
					$path = trim($mountpoint, '/');
				} else {
					$path = trim($share['name'], '/');
				}
				$notification = $this->notificationManager->createNotification();
				$notification->setApp('files_sharing')
					->setUser($share['user'])
					->setObject('remote_share', (int)$share['id']);
				$this->notificationManager->markProcessed($notification);

				$event = $this->activityManager->generateEvent();
				$event->setApp('files_sharing')
					->setType('remote_share')
					->setSubject(RemoteShares::SUBJECT_REMOTE_SHARE_UNSHARED, [$owner->getId(), $path])
					->setAffectedUser($user)
					->setObject('remote_share', (int)$share['id'], $path);
				\OC::$server->getActivityManager()->publish($event);
			}
		}

		return [];
	}

	private function cleanupRemote($remote) {
		$remote = substr($remote, strpos($remote, '://') + 3);

		return rtrim($remote, '/');
	}

	/**
	 * recipient of a share request to re-share the file with another user
	 *
	 * @param string $id
	 * @param array $notification
	 * @return array
	 * @throws AuthenticationFailedException
	 * @throws BadRequestException
	 * @throws ProviderCouldNotAddShareException
	 * @throws ShareNotFound
	 */
	protected function reshareRequested($id, array $notification) {
		if (!isset($notification['sharedSecret'])) {
			throw new BadRequestException(['sharedSecret']);
		}
		$token = $notification['sharedSecret'];

		if (!isset($notification['shareWith'])) {
			throw new BadRequestException(['shareWith']);
		}
		$shareWith = $notification['shareWith'];

		if (!isset($notification['senderId'])) {
			throw new BadRequestException(['senderId']);
		}
		$senderId = $notification['senderId'];

		$share = $this->federatedShareProvider->getShareById($id);
		// don't allow to share a file back to the owner
		try {
			list($user, $remote) = $this->addressHandler->splitUserRemote($shareWith);
			$owner = $share->getShareOwner();
			$currentServer = $this->addressHandler->generateRemoteURL();
			if ($this->addressHandler->compareAddresses($user, $remote, $owner, $currentServer)) {
				throw new ProviderCouldNotAddShareException('Resharing back to the owner is not allowed: ' . $id);
			}
		} catch (\Exception $e) {
			throw new ProviderCouldNotAddShareException($e->getMessage());
		}

		$this->verifyShare($share, $token);

		// check if re-sharing is allowed
		if ($share->getPermissions() & Constants::PERMISSION_SHARE) {
			// the recipient of the initial share is now the initiator for the re-share
			$share->setSharedBy($share->getSharedWith());
			$share->setSharedWith($shareWith);
			$result = $this->federatedShareProvider->create($share);
			$this->federatedShareProvider->storeRemoteId((int)$result->getId(), $senderId);
			return ['token' => $result->getToken(), 'providerId' => $result->getId()];
		} else {
			throw new ProviderCouldNotAddShareException('resharing not allowed for share: ' . $id);
		}
	}

	/**
	 * update permission of a re-share so that the share dialog shows the right
	 * permission if the owner or the sender changes the permission
	 *
	 * @param string $id
	 * @param array $notification
	 * @return array
	 * @throws AuthenticationFailedException
	 * @throws BadRequestException
	 */
	protected function updateResharePermissions($id, array $notification) {
		if (!isset($notification['sharedSecret'])) {
			throw new BadRequestException(['sharedSecret']);
		}
		$token = $notification['sharedSecret'];

		if (!isset($notification['permission'])) {
			throw new BadRequestException(['permission']);
		}
		$ocmPermissions = $notification['permission'];

		$share = $this->federatedShareProvider->getShareById($id);

		$ncPermission = $this->ocmPermissions2ncPermissions($ocmPermissions);

		$this->verifyShare($share, $token);
		$this->updatePermissionsInDatabase($share, $ncPermission);

		return [];
	}

	/**
	 * translate OCM Permissions to Nextcloud permissions
	 *
	 * @param array $ocmPermissions
	 * @return int
	 * @throws BadRequestException
	 */
	protected function ocmPermissions2ncPermissions(array $ocmPermissions) {
		$ncPermissions = 0;
		foreach ($ocmPermissions as $permission) {
			switch (strtolower($permission)) {
				case 'read':
					$ncPermissions += Constants::PERMISSION_READ;
					break;
				case 'write':
					$ncPermissions += Constants::PERMISSION_CREATE + Constants::PERMISSION_UPDATE;
					break;
				case 'share':
					$ncPermissions += Constants::PERMISSION_SHARE;
					break;
				default:
					throw new BadRequestException(['permission']);
			}
		}

		return $ncPermissions;
	}

	/**
	 * update permissions in database
	 *
	 * @param IShare $share
	 * @param int $permissions
	 */
	protected function updatePermissionsInDatabase(IShare $share, $permissions) {
		$query = $this->connection->getQueryBuilder();
		$query->update('share')
			->where($query->expr()->eq('id', $query->createNamedParameter($share->getId())))
			->set('permissions', $query->createNamedParameter($permissions))
			->execute();
	}


	/**
	 * get file
	 *
	 * @param string $user
	 * @param int $fileSource
	 * @return array with internal path of the file and a absolute link to it
	 */
	private function getFile($user, $fileSource) {
		\OC_Util::setupFS($user);

		try {
			$file = Filesystem::getPath($fileSource);
		} catch (NotFoundException $e) {
			$file = null;
		}
		$args = Filesystem::is_dir($file) ? ['dir' => $file] : ['dir' => dirname($file), 'scrollto' => $file];
		$link = Util::linkToAbsolute('files', 'index.php', $args);

		return [$file, $link];
	}

	/**
	 * check if we are the initiator or the owner of a re-share and return the correct UID
	 *
	 * @param IShare $share
	 * @return string
	 */
	protected function getCorrectUid(IShare $share) {
		if ($this->userManager->userExists($share->getShareOwner())) {
			return $share->getShareOwner();
		}

		return $share->getSharedBy();
	}



	/**
	 * check if we got the right share
	 *
	 * @param IShare $share
	 * @param string $token
	 * @return bool
	 * @throws AuthenticationFailedException
	 */
	protected function verifyShare(IShare $share, $token) {
		if (
			$share->getShareType() === IShare::TYPE_REMOTE &&
			$share->getToken() === $token
		) {
			return true;
		}

		if ($share->getShareType() === IShare::TYPE_CIRCLE) {
			try {
				$knownShare = $this->shareManager->getShareByToken($token);
				if ($knownShare->getId() === $share->getId()) {
					return true;
				}
			} catch (ShareNotFound $e) {
			}
		}

		throw new AuthenticationFailedException();
	}



	/**
	 * check if server-to-server sharing is enabled
	 *
	 * @param bool $incoming
	 * @return bool
	 */
	private function isS2SEnabled($incoming = false) {
		$result = $this->appManager->isEnabledForUser('files_sharing');

		if ($incoming) {
			$result = $result && $this->federatedShareProvider->isIncomingServer2serverShareEnabled();
		} else {
			$result = $result && $this->federatedShareProvider->isOutgoingServer2serverShareEnabled();
		}

		return $result;
	}


	/**
	 * get the supported share types, e.g. "user", "group", etc.
	 *
	 * @return array
	 *
	 * @since 14.0.0
	 */
	public function getSupportedShareTypes() {
		return ['user', 'group'];
	}
}
