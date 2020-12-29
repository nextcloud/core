<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\FederatedFileSharing;

use OCA\FederatedFileSharing\Events\FederatedShareAddedEvent;
use OCP\AppFramework\Http;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Http\Client\IClientService;
use OCP\OCS\IDiscoveryService;

class Notifications {
	public const RESPONSE_FORMAT = 'json'; // default response format for ocs calls

	/** @var AddressHandler */
	private $addressHandler;

	/** @var IClientService */
	private $httpClientService;

	/** @var IDiscoveryService */
	private $discoveryService;

	/** @var IJobList  */
	private $jobList;

	/** @var ICloudFederationProviderManager */
	private $federationProviderManager;

	/** @var ICloudFederationFactory */
	private $cloudFederationFactory;

	/** @var IEventDispatcher */
	private $eventDispatcher;

	public function __construct(
		AddressHandler $addressHandler,
		IClientService $httpClientService,
		IDiscoveryService $discoveryService,
		IJobList $jobList,
		ICloudFederationProviderManager $federationProviderManager,
		ICloudFederationFactory $cloudFederationFactory,
		IEventDispatcher $eventDispatcher
	) {
		$this->addressHandler = $addressHandler;
		$this->httpClientService = $httpClientService;
		$this->discoveryService = $discoveryService;
		$this->jobList = $jobList;
		$this->federationProviderManager = $federationProviderManager;
		$this->cloudFederationFactory = $cloudFederationFactory;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * send server-to-server share to remote server
	 *
	 * @param string $token
	 * @param string $shareWith
	 * @param string $name
	 * @param string $remoteId
	 * @param string $owner
	 * @param string $ownerFederatedId
	 * @param string $sharedBy
	 * @param string $sharedByFederatedId
	 * @param int $shareType (can be a remote user or group share)
	 * @return bool
	 * @throws \OC\HintException
	 * @throws \OC\ServerNotAvailableException
	 */
	public function sendRemoteShare($token, $shareWith, $name, $remoteId, $owner, $ownerFederatedId, $sharedBy, $sharedByFederatedId, $shareType) {
		list($user, $remote) = $this->addressHandler->splitUserRemote($shareWith);

		if ($user && $remote) {
			$local = $this->addressHandler->generateRemoteURL();

			$fields = [
				'shareWith' => $user,
				'token' => $token,
				'name' => $name,
				'remoteId' => $remoteId,
				'owner' => $owner,
				'ownerFederatedId' => $ownerFederatedId,
				'sharedBy' => $sharedBy,
				'sharedByFederatedId' => $sharedByFederatedId,
				'remote' => $local,
				'shareType' => $shareType
			];

			$result = $this->tryHttpPostToShareEndpoint($remote, '', $fields);
			$status = json_decode($result['result'], true);

			$ocsStatus = isset($status['ocs']);
			$ocsSuccess = $ocsStatus && ($status['ocs']['meta']['statuscode'] === 100 || $status['ocs']['meta']['statuscode'] === 200);

			if ($result['success'] && (!$ocsStatus || $ocsSuccess)) {
				$event = new FederatedShareAddedEvent($remote);
				$this->eventDispatcher->dispatchTyped($event);
				return true;
			}
		}

		return false;
	}

	/**
	 * ask owner to re-share the file with the given user
	 *
	 * @param string $token
	 * @param string $id remote Id
	 * @param string $shareId internal share Id
	 * @param string $remote remote address of the owner
	 * @param string $shareWith
	 * @param int $permission
	 * @param string $filename
	 * @return array|false
	 * @throws \OC\HintException
	 * @throws \OC\ServerNotAvailableException
	 */
	public function requestReShare($token, $id, $shareId, $remote, $shareWith, $permission, $filename, $shareType) {
		$fields = [
			'shareWith' => $shareWith,
			'token' => $token,
			'permission' => $permission,
			'remoteId' => $shareId,
		];

		$ocmFields = $fields;
		$ocmFields['remoteId'] = (string)$id;
		$ocmFields['localId'] = $shareId;
		$ocmFields['name'] = $filename;
		$ocmFields['shareType'] = $shareType;

		$ocmResult = $this->tryOCMEndPoint($remote, $ocmFields, 'reshare');
		if (is_array($ocmResult) && isset($ocmResult['token']) && isset($ocmResult['providerId'])) {
			return [$ocmResult['token'], $ocmResult['providerId']];
		}

		$result = $this->tryLegacyEndPoint(rtrim($remote, '/'), '/' . $id . '/reshare', $fields);
		$status = json_decode($result['result'], true);

		$httpRequestSuccessful = $result['success'];
		$ocsCallSuccessful = $status['ocs']['meta']['statuscode'] === 100 || $status['ocs']['meta']['statuscode'] === 200;
		$validToken = isset($status['ocs']['data']['token']) && is_string($status['ocs']['data']['token']);
		$validRemoteId = isset($status['ocs']['data']['remoteId']);

		if ($httpRequestSuccessful && $ocsCallSuccessful && $validToken && $validRemoteId) {
			return [
				$status['ocs']['data']['token'],
				$status['ocs']['data']['remoteId']
			];
		}

		return false;
	}

	/**
	 * send server-to-server unshare to remote server
	 *
	 * @param string $remote url
	 * @param string $id share id
	 * @param string $token
	 * @return bool
	 */
	public function sendRemoteUnShare($remote, $id, $token) {
		$this->sendUpdateToRemote($remote, $id, $token, 'unshare');
	}

	/**
	 * send server-to-server unshare to remote server
	 *
	 * @param string $remote url
	 * @param string $id share id
	 * @param string $token
	 * @return bool
	 */
	public function sendRevokeShare($remote, $id, $token) {
		$this->sendUpdateToRemote($remote, $id, $token, 'reshare_undo');
	}

	/**
	 * send notification to remote server if the permissions was changed
	 *
	 * @param string $remote
	 * @param string $remoteId
	 * @param string $token
	 * @param int $permissions
	 * @return bool
	 */
	public function sendPermissionChange($remote, $remoteId, $token, $permissions) {
		$this->sendUpdateToRemote($remote, $remoteId, $token, 'permissions', ['permissions' => $permissions]);
	}

	/**
	 * forward accept reShare to remote server
	 *
	 * @param string $remote
	 * @param string $remoteId
	 * @param string $token
	 */
	public function sendAcceptShare($remote, $remoteId, $token) {
		$this->sendUpdateToRemote($remote, $remoteId, $token, 'accept');
	}

	/**
	 * forward decline reShare to remote server
	 *
	 * @param string $remote
	 * @param string $remoteId
	 * @param string $token
	 */
	public function sendDeclineShare($remote, $remoteId, $token) {
		$this->sendUpdateToRemote($remote, $remoteId, $token, 'decline');
	}

	/**
	 * inform remote server whether server-to-server share was accepted/declined
	 *
	 * @param string $remote
	 * @param string $token
	 * @param string $remoteId Share id on the remote host
	 * @param string $action possible actions: accept, decline, unshare, revoke, permissions
	 * @param array $data
	 * @param int $try
	 * @return boolean
	 */
	public function sendUpdateToRemote($remote, $remoteId, $token, $action, $data = [], $try = 0) {
		$fields = [
			'token' => $token,
			'remoteId' => $remoteId
		];
		foreach ($data as $key => $value) {
			$fields[$key] = $value;
		}

		$result = $this->tryHttpPostToShareEndpoint(rtrim($remote, '/'), '/' . $remoteId . '/' . $action, $fields, $action);
		$status = json_decode($result['result'], true);

		if ($result['success'] &&
			($status['ocs']['meta']['statuscode'] === 100 ||
				$status['ocs']['meta']['statuscode'] === 200
			)
		) {
			return true;
		} elseif ($try === 0) {
			// only add new job on first try
			$this->jobList->add('OCA\FederatedFileSharing\BackgroundJob\RetryJob',
				[
					'remote' => $remote,
					'remoteId' => $remoteId,
					'token' => $token,
					'action' => $action,
					'data' => json_encode($data),
					'try' => $try,
					'lastRun' => $this->getTimestamp()
				]
			);
		}

		return false;
	}


	/**
	 * return current timestamp
	 *
	 * @return int
	 */
	protected function getTimestamp() {
		return time();
	}

	/**
	 * try http post with the given protocol, if no protocol is given we pick
	 * the secure one (https)
	 *
	 * @param string $remoteDomain
	 * @param string $urlSuffix
	 * @param array $fields post parameters
	 * @param string $action define the action (possible values: share, reshare, accept, decline, unshare, revoke, permissions)
	 * @return array
	 * @throws \Exception
	 */
	protected function tryHttpPostToShareEndpoint($remoteDomain, $urlSuffix, array $fields, $action = "share") {
		if ($this->addressHandler->urlContainProtocol($remoteDomain) === false) {
			$remoteDomain = 'https://' . $remoteDomain;
		}

		$result = [
			'success' => false,
			'result' => '',
		];

		// if possible we use the new OCM API
		$ocmResult = $this->tryOCMEndPoint($remoteDomain, $fields, $action);
		if (is_array($ocmResult)) {
			$result['success'] = true;
			$result['result'] = json_encode([
				'ocs' => ['meta' => ['statuscode' => 200]]]);
			return $result;
		}

		return $this->tryLegacyEndPoint($remoteDomain, $urlSuffix, $fields);
	}

	/**
	 * try old federated sharing API if the OCM api doesn't work
	 *
	 * @param $remoteDomain
	 * @param $urlSuffix
	 * @param array $fields
	 * @return mixed
	 * @throws \Exception
	 */
	protected function tryLegacyEndPoint($remoteDomain, $urlSuffix, array $fields) {
		$result = [
			'success' => false,
			'result' => '',
		];

		// Fall back to old API
		$client = $this->httpClientService->newClient();
		$federationEndpoints = $this->discoveryService->discover($remoteDomain, 'FEDERATED_SHARING');
		$endpoint = isset($federationEndpoints['share']) ? $federationEndpoints['share'] : '/ocs/v2.php/cloud/shares';
		try {
			$response = $client->post($remoteDomain . $endpoint . $urlSuffix . '?format=' . self::RESPONSE_FORMAT, [
				'body' => $fields,
				'timeout' => 10,
				'connect_timeout' => 10,
			]);
			$result['result'] = $response->getBody();
			$result['success'] = true;
		} catch (\Exception $e) {
			// if flat re-sharing is not supported by the remote server
			// we re-throw the exception and fall back to the old behaviour.
			// (flat re-shares has been introduced in Nextcloud 9.1)
			if ($e->getCode() === Http::STATUS_INTERNAL_SERVER_ERROR) {
				throw $e;
			}
		}

		return $result;
	}

	/**
	 * send action regarding federated sharing to the remote server using the OCM API
	 *
	 * @param $remoteDomain
	 * @param $fields
	 * @param $action
	 *
	 * @return bool
	 */
	protected function tryOCMEndPoint($remoteDomain, $fields, $action) {
		switch ($action) {
			case 'share':
				$share = $this->cloudFederationFactory->getCloudFederationShare(
					$fields['shareWith'] . '@' . $remoteDomain,
					$fields['name'],
					'',
					$fields['remoteId'],
					$fields['ownerFederatedId'],
					$fields['owner'],
					$fields['sharedByFederatedId'],
					$fields['sharedBy'],
					$fields['token'],
					$fields['shareType'],
					'file'
				);
				return $this->federationProviderManager->sendShare($share);
			case 'reshare':
				// ask owner to reshare a file
				$notification = $this->cloudFederationFactory->getCloudFederationNotification();
				$notification->setMessage('REQUEST_RESHARE',
					'file',
					$fields['remoteId'],
					[
						'sharedSecret' => $fields['token'],
						'shareWith' => $fields['shareWith'],
						'senderId' => $fields['localId'],
						'shareType' => $fields['shareType'],
						'message' => 'Ask owner to reshare the file'
					]
				);
				return $this->federationProviderManager->sendNotification($remoteDomain, $notification);
			case 'unshare':
				//owner unshares the file from the recipient again
				$notification = $this->cloudFederationFactory->getCloudFederationNotification();
				$notification->setMessage('SHARE_UNSHARED',
					'file',
					$fields['remoteId'],
					[
						'sharedSecret' => $fields['token'],
						'messgage' => 'file is no longer shared with you'
					]
				);
				return $this->federationProviderManager->sendNotification($remoteDomain, $notification);
			case 'reshare_undo':
				// if a reshare was unshared we send the information to the initiator/owner
				$notification = $this->cloudFederationFactory->getCloudFederationNotification();
				$notification->setMessage('RESHARE_UNDO',
					'file',
					$fields['remoteId'],
					[
						'sharedSecret' => $fields['token'],
						'message' => 'reshare was revoked'
					]
				);
				return $this->federationProviderManager->sendNotification($remoteDomain, $notification);
		}

		return false;
	}
}
