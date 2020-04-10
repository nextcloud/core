<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\FederatedFileSharing\Controller;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\FederatedShareProvider;
use OCA\FederatedFileSharing\Notifications;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCSController;
use OCP\Constants;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\Exceptions\ProviderDoesNotExistsException;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Share;
use OCP\Share\Exceptions\ShareNotFound;

class RequestHandlerController extends OCSController {

	/** @var FederatedShareProvider */
	private $federatedShareProvider;

	/** @var IDBConnection */
	private $connection;

	/** @var Share\IManager */
	private $shareManager;

	/** @var Notifications */
	private $notifications;

	/** @var AddressHandler */
	private $addressHandler;

	/** @var  IUserManager */
	private $userManager;

	/** @var string */
	private $shareTable = 'share';

	/** @var ICloudIdManager */
	private $cloudIdManager;

	/** @var ILogger */
	private $logger;

	/** @var ICloudFederationFactory */
	private $cloudFederationFactory;

	/** @var ICloudFederationProviderManager */
	private $cloudFederationProviderManager;

	/**
	 * Server2Server constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param FederatedShareProvider $federatedShareProvider
	 * @param IDBConnection $connection
	 * @param Share\IManager $shareManager
	 * @param Notifications $notifications
	 * @param AddressHandler $addressHandler
	 * @param IUserManager $userManager
	 * @param ICloudIdManager $cloudIdManager
	 * @param ILogger $logger
	 * @param ICloudFederationFactory $cloudFederationFactory
	 * @param ICloudFederationProviderManager $cloudFederationProviderManager
	 */
	public function __construct($appName,
								IRequest $request,
								FederatedShareProvider $federatedShareProvider,
								IDBConnection $connection,
								Share\IManager $shareManager,
								Notifications $notifications,
								AddressHandler $addressHandler,
								IUserManager $userManager,
								ICloudIdManager $cloudIdManager,
								ILogger $logger,
								ICloudFederationFactory $cloudFederationFactory,
								ICloudFederationProviderManager $cloudFederationProviderManager
	) {
		parent::__construct($appName, $request);

		$this->federatedShareProvider = $federatedShareProvider;
		$this->connection = $connection;
		$this->shareManager = $shareManager;
		$this->notifications = $notifications;
		$this->addressHandler = $addressHandler;
		$this->userManager = $userManager;
		$this->cloudIdManager = $cloudIdManager;
		$this->logger = $logger;
		$this->cloudFederationFactory = $cloudFederationFactory;
		$this->cloudFederationProviderManager = $cloudFederationProviderManager;
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * create a new share
	 *
	 * @return Http\DataResponse
	 * @throws OCSException
	 */
	public function createShare() {

		$remote = isset($_POST['remote']) ? $_POST['remote'] : null;
		$token = isset($_POST['token']) ? $_POST['token'] : null;
		$name = isset($_POST['name']) ? $_POST['name'] : null;
		$owner = isset($_POST['owner']) ? $_POST['owner'] : null;
		$sharedBy = isset($_POST['sharedBy']) ? $_POST['sharedBy'] : null;
		$shareWith = isset($_POST['shareWith']) ? $_POST['shareWith'] : null;
		$remoteId = isset($_POST['remoteId']) ? (int)$_POST['remoteId'] : null;
		$sharedByFederatedId = isset($_POST['sharedByFederatedId']) ? $_POST['sharedByFederatedId'] : null;
		$ownerFederatedId = isset($_POST['ownerFederatedId']) ? $_POST['ownerFederatedId'] : null;

		if ($ownerFederatedId === null) {
			$ownerFederatedId = $this->cloudIdManager->getCloudId($owner, $this->cleanupRemote($remote))->getId();
		}
		// if the owner of the share and the initiator are the same user
		// we also complete the federated share ID for the initiator
		if ($sharedByFederatedId === null && $owner === $sharedBy) {
			$sharedByFederatedId = $ownerFederatedId;
		}

		$share = $this->cloudFederationFactory->getCloudFederationShare(
			$shareWith,
			$name,
			'',
			$remoteId,
			$ownerFederatedId,
			$owner,
			$sharedByFederatedId,
			$sharedBy,
			$token,
			'user',
			'file'
		);

		try {
			$provider = $this->cloudFederationProviderManager->getCloudFederationProvider('file');
			$provider->shareReceived($share);
		} catch (ProviderDoesNotExistsException $e) {
			throw new OCSException('Server does not support federated cloud sharing', 503);
		} catch (ProviderCouldNotAddShareException $e) {
			throw new OCSException($e->getMessage(), 400);
		} catch (\Exception $e) {
			throw new OCSException('internal server error, was not able to add share from ' . $remote, 500);
		}

		return new Http\DataResponse();
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * create re-share on behalf of another user
	 *
	 * @param int $id
	 * @return Http\DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSException
	 * @throws OCSForbiddenException
	 */
	public function reShare($id) {

		$token = $this->request->getParam('token', null);
		$shareWith = $this->request->getParam('shareWith', null);
		$permission = (int)$this->request->getParam('permission', null);
		$remoteId = (int)$this->request->getParam('remoteId', null);

		if ($id === null ||
			$token === null ||
			$shareWith === null ||
			$permission === null ||
			$remoteId === null
		) {
			throw new OCSBadRequestException();
		}

		$notification = [
			'sharedSecret' => $token,
			'shareWith' => $shareWith,
			'senderId' => $remoteId,
			'message' => 'Recipient of a share ask the owner to reshare the file'
		];

		try {
			$provider = $this->cloudFederationProviderManager->getCloudFederationProvider('file');
			list($newToken, $localId) = $provider->notificationReceived('REQUEST_RESHARE', $id, $notification);
			return new Http\DataResponse([
				'token' => $newToken,
				'remoteId' => $localId
			]);
		} catch (ProviderDoesNotExistsException $e) {
			throw new OCSException('Server does not support federated cloud sharing', 503);
		} catch (ShareNotFound $e) {
			$this->logger->debug('Share not found: ' . $e->getMessage());
		} catch (\Exception $e) {
			$this->logger->debug('internal server error, can not process notification: ' . $e->getMessage());
		}

		throw new OCSBadRequestException();
	}


	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * accept server-to-server share
	 *
	 * @param int $id
	 * @return Http\DataResponse
	 * @throws OCSException
	 * @throws ShareNotFound
	 * @throws \OC\HintException
	 */
	public function acceptShare($id) {

		$token = isset($_POST['token']) ? $_POST['token'] : null;

		$notification = [
			'sharedSecret' => $token,
			'message' => 'Recipient accept the share'
		];

		try {
			$provider = $this->cloudFederationProviderManager->getCloudFederationProvider('file');
			$provider->notificationReceived('SHARE_ACCEPTED', $id, $notification);
		} catch (ProviderDoesNotExistsException $e) {
			throw new OCSException('Server does not support federated cloud sharing', 503);
		} catch (ShareNotFound $e) {
			$this->logger->debug('Share not found: ' . $e->getMessage());
		} catch (\Exception $e) {
			$this->logger->debug('internal server error, can not process notification: ' . $e->getMessage());
		}

		return new Http\DataResponse();
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * decline server-to-server share
	 *
	 * @param int $id
	 * @return Http\DataResponse
	 * @throws OCSException
	 */
	public function declineShare($id) {

		$token = isset($_POST['token']) ? $_POST['token'] : null;

		$notification = [
			'sharedSecret' => $token,
			'message' => 'Recipient declined the share'
		];

		try {
			$provider = $this->cloudFederationProviderManager->getCloudFederationProvider('file');
			$provider->notificationReceived('SHARE_DECLINED', $id, $notification);
		} catch (ProviderDoesNotExistsException $e) {
			throw new OCSException('Server does not support federated cloud sharing', 503);
		} catch (ShareNotFound $e) {
			$this->logger->debug('Share not found: ' . $e->getMessage());
		} catch (\Exception $e) {
			$this->logger->debug('internal server error, can not process notification: ' . $e->getMessage());
		}

		return new Http\DataResponse();
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * remove server-to-server share if it was unshared by the owner
	 *
	 * @param int $id
	 * @return Http\DataResponse
	 * @throws OCSException
	 */
	public function unshare($id) {

		if (!$this->isS2SEnabled()) {
			throw new OCSException('Server does not support federated cloud sharing', 503);
		}

		$token = isset($_POST['token']) ? $_POST['token'] : null;

		try {
			$provider = $this->cloudFederationProviderManager->getCloudFederationProvider('file');
			$notification = ['sharedSecret' => $token];
			$provider->notificationReceived('SHARE_UNSHARED', $id, $notification);
		} catch (\Exception $e) {
			$this->logger->debug('processing unshare notification failed: ' . $e->getMessage());
		}

		return new Http\DataResponse();
	}

	private function cleanupRemote($remote) {
		$remote = substr($remote, strpos($remote, '://') + 3);

		return rtrim($remote, '/');
	}


	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * federated share was revoked, either by the owner or the re-sharer
	 *
	 * @param int $id
	 * @return Http\DataResponse
	 * @throws OCSBadRequestException
	 */
	public function revoke($id) {

		$token = $this->request->getParam('token');

		try {
			$provider = $this->cloudFederationProviderManager->getCloudFederationProvider('file');
			$notification = ['sharedSecret' => $token];
			$provider->notificationReceived('RESHARE_UNDO', $id, $notification);
			return new Http\DataResponse();
		} catch (\Exception $e) {
			throw new OCSBadRequestException();
		}

	}

	/**
	 * check if server-to-server sharing is enabled
	 *
	 * @param bool $incoming
	 * @return bool
	 */
	private function isS2SEnabled($incoming = false) {

		$result = \OCP\App::isEnabled('files_sharing');

		if ($incoming) {
			$result = $result && $this->federatedShareProvider->isIncomingServer2serverShareEnabled();
		} else {
			$result = $result && $this->federatedShareProvider->isOutgoingServer2serverShareEnabled();
		}

		return $result;
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * update share information to keep federated re-shares in sync
	 *
	 * @param int $id
	 * @return Http\DataResponse
	 * @throws OCSBadRequestException
	 */
	public function updatePermissions($id) {
		$token = $this->request->getParam('token', null);
		$ncPermissions = $this->request->getParam('permissions', null);

		try {
			$provider = $this->cloudFederationProviderManager->getCloudFederationProvider('file');
			$ocmPermissions = $this->ncPermissions2ocmPermissions((int)$ncPermissions);
			$notification = ['sharedSecret' => $token, 'permission' => $ocmPermissions];
			$provider->notificationReceived('RESHARE_CHANGE_PERMISSION', $id, $notification);
		} catch (\Exception $e) {
			$this->logger->debug($e->getMessage());
			throw new OCSBadRequestException();
		}

		return new Http\DataResponse();
	}

	/**
	 * translate Nextcloud permissions to OCM Permissions
	 *
	 * @param $ncPermissions
	 * @return array
	 */
	protected function ncPermissions2ocmPermissions($ncPermissions) {

		$ocmPermissions = [];

		if ($ncPermissions & Constants::PERMISSION_SHARE) {
			$ocmPermissions[] = 'share';
		}

		if ($ncPermissions & Constants::PERMISSION_READ) {
			$ocmPermissions[] = 'read';
		}

		if (($ncPermissions & Constants::PERMISSION_CREATE) ||
			($ncPermissions & Constants::PERMISSION_UPDATE)) {
			$ocmPermissions[] = 'write';
		}

		return $ocmPermissions;

	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * change the owner of a server-to-server share
	 *
	 * @param int $id
	 * @return Http\DataResponse
	 * @throws \InvalidArgumentException
	 * @throws OCSException
	 */
	public function move($id) {

		if (!$this->isS2SEnabled()) {
			throw new OCSException('Server does not support federated cloud sharing', 503);
		}

		$token = $this->request->getParam('token');
		$remote = $this->request->getParam('remote');
		$newRemoteId = $this->request->getParam('remote_id', $id);
		$cloudId = $this->cloudIdManager->resolveCloudId($remote);

		$qb = $this->connection->getQueryBuilder();
		$query = $qb->update('share_external')
			->set('remote', $qb->createNamedParameter($cloudId->getRemote()))
			->set('owner', $qb->createNamedParameter($cloudId->getUser()))
			->set('remote_id', $qb->createNamedParameter($newRemoteId))
			->where($qb->expr()->eq('remote_id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('share_token', $qb->createNamedParameter($token)));
		$affected = $query->execute();

		if ($affected > 0) {
			return new Http\DataResponse(['remote' => $cloudId->getRemote(), 'owner' => $cloudId->getUser()]);
		} else {
			throw new OCSBadRequestException('Share not found or token invalid');
		}
	}
}
