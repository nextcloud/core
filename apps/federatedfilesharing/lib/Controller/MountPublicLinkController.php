<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Björn Schießle <bjoern@schiessle.org>
 *
 * @author Allan Nordhøy <epost@anotheragency.no>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

use OC\HintException;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\FederatedShareProvider;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Federation\ICloudIdManager;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use OCP\Share\IManager;

/**
 * Class MountPublicLinkController
 *
 * convert public links to federated shares
 *
 * @package OCA\FederatedFileSharing\Controller
 */
class MountPublicLinkController extends Controller {

	/** @var FederatedShareProvider */
	private $federatedShareProvider;

	/** @var AddressHandler */
	private $addressHandler;

	/** @var IManager  */
	private $shareManager;

	/** @var  ISession */
	private $session;

	/** @var IL10N */
	private $l;

	/** @var IUserSession */
	private $userSession;

	/** @var IClientService */
	private $clientService;

	/** @var ICloudIdManager  */
	private $cloudIdManager;

	/**
	 * MountPublicLinkController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param FederatedShareProvider $federatedShareProvider
	 * @param IManager $shareManager
	 * @param AddressHandler $addressHandler
	 * @param ISession $session
	 * @param IL10N $l
	 * @param IUserSession $userSession
	 * @param IClientService $clientService
	 * @param ICloudIdManager $cloudIdManager
	 */
	public function __construct($appName,
								IRequest $request,
								FederatedShareProvider $federatedShareProvider,
								IManager $shareManager,
								AddressHandler $addressHandler,
								ISession $session,
								IL10N $l,
								IUserSession $userSession,
								IClientService $clientService,
								ICloudIdManager $cloudIdManager
	) {
		parent::__construct($appName, $request);

		$this->federatedShareProvider = $federatedShareProvider;
		$this->shareManager = $shareManager;
		$this->addressHandler = $addressHandler;
		$this->session = $session;
		$this->l = $l;
		$this->userSession = $userSession;
		$this->clientService = $clientService;
		$this->cloudIdManager = $cloudIdManager;
	}

	/**
	 * send federated share to a user of a public link
	 *
	 * @NoCSRFRequired
	 * @PublicPage
	 * @BruteForceProtection(action=publicLink2FederatedShare)
	 *
	 * @param string $shareWith
	 * @param string $token
	 * @param string $password
	 * @return JSONResponse
	 */
	public function createFederatedShare($shareWith, $token, $password = '') {

		if (!$this->federatedShareProvider->isOutgoingServer2serverShareEnabled()) {
			return new JSONResponse(
				['message' => 'This server doesn\'t support outgoing federated shares'],
				Http::STATUS_BAD_REQUEST
			);
		}

		try {
			list(, $server) = $this->addressHandler->splitUserRemote($shareWith);
			$share = $this->shareManager->getShareByToken($token);
		} catch (HintException $e) {
			return new JSONResponse(['message' => $e->getHint()], Http::STATUS_BAD_REQUEST);
		}

		// make sure that user is authenticated in case of a password protected link
		$storedPassword = $share->getPassword();
		$authenticated = $this->session->get('public_link_authenticated') === $share->getId() ||
			$this->shareManager->checkPassword($share, $password);
		if (!empty($storedPassword) && !$authenticated) {
			$response = new JSONResponse(
				['message' => 'No permission to access the share'],
				Http::STATUS_BAD_REQUEST
			);
			$response->throttle();
			return $response;
		}

		$share->setSharedWith($shareWith);

		try {
			$this->federatedShareProvider->create($share);
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, [
				'level' => ILogger::WARN,
				'app' => 'federatedfilesharing',
			]);
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(['remoteUrl' => $server]);
	}

	/**
	 * ask other server to get a federated share
	 *
	 * @NoAdminRequired
	 *
	 * @param string $token
	 * @param string $remote
	 * @param string $password
	 * @param string $owner (only for legacy reasons, can be removed with legacyMountPublicLink())
	 * @param string $ownerDisplayName (only for legacy reasons, can be removed with legacyMountPublicLink())
	 * @param string $name (only for legacy reasons, can be removed with legacyMountPublicLink())
	 * @return JSONResponse
	 */
	public function askForFederatedShare($token, $remote, $password = '', $owner = '', $ownerDisplayName = '', $name = '') {
		// check if server admin allows to mount public links from other servers
		if ($this->federatedShareProvider->isIncomingServer2serverShareEnabled() === false) {
			return new JSONResponse(['message' => $this->l->t('Server to server sharing is not enabled on this server')], Http::STATUS_BAD_REQUEST);
		}

		$cloudId = $this->cloudIdManager->getCloudId($this->userSession->getUser()->getUID(), $this->addressHandler->generateRemoteURL());

		$httpClient = $this->clientService->newClient();

		try {
			$response = $httpClient->post($remote . '/index.php/apps/federatedfilesharing/createFederatedShare',
				[
					'body' =>
						[
							'token' => $token,
							'shareWith' => rtrim($cloudId->getId(), '/'),
							'password' => $password
						],
					'connect_timeout' => 10,
				]
			);
		} catch (\Exception $e) {
			if (empty($password)) {
				$message = $this->l->t("Couldn't establish a federated share.");
			} else {
				$message = $this->l->t("Couldn't establish a federated share, maybe the password was wrong.");
			}
			return new JSONResponse(['message' => $message], Http::STATUS_BAD_REQUEST);
		}

		$body = $response->getBody();
		$result = json_decode($body, true);

		if (is_array($result) && isset($result['remoteUrl'])) {
			return new JSONResponse(['message' => $this->l->t('Federated Share request sent, you will receive an invitation. Check your notifications.')]);
		}

		// if we doesn't get the expected response we assume that we try to add
		// a federated share from a Nextcloud <= 9 server
		$message = $this->l->t("Couldn't establish a federated share, it looks like the server to federate with is too old (Nextcloud <= 9).");
		return new JSONResponse(['message' => $message], Http::STATUS_BAD_REQUEST);
	}
}
