<?php
/**
 * @copyright Copyright (c) 2018 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\CloudFederationAPI\Controller;

use OCA\CloudFederationAPI\Config;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Federation\Exceptions\ActionNotSupportedException;
use OCP\Federation\Exceptions\AuthenticationFailedException;
use OCP\Federation\Exceptions\BadRequestException;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\Exceptions\ProviderDoesNotExistsException;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share\Exceptions\ShareNotFound;

/**
 * Class RequestHandlerController
 *
 * handle API between different Cloud instances
 *
 * @package OCA\CloudFederationAPI\Controller
 */
class RequestHandlerController extends Controller {

	/** @var ILogger */
	private $logger;

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ICloudFederationProviderManager */
	private $cloudFederationProviderManager;

	/** @var Config */
	private $config;

	/** @var ICloudFederationFactory */
	private $factory;

	/** @var ICloudIdManager */
	private $cloudIdManager;

	public function __construct($appName,
								IRequest $request,
								ILogger $logger,
								IUserManager $userManager,
								IGroupManager $groupManager,
								IURLGenerator $urlGenerator,
								ICloudFederationProviderManager $cloudFederationProviderManager,
								Config $config,
								ICloudFederationFactory $factory,
								ICloudIdManager $cloudIdManager
	) {
		parent::__construct($appName, $request);

		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->urlGenerator = $urlGenerator;
		$this->cloudFederationProviderManager = $cloudFederationProviderManager;
		$this->config = $config;
		$this->factory = $factory;
		$this->cloudIdManager = $cloudIdManager;
	}

	/**
	 * add share
	 *
	 * @NoCSRFRequired
	 * @PublicPage
	 * @BruteForceProtection(action=receiveFederatedShare)
	 *
	 * @param string $shareWith
	 * @param string $name resource name (e.g. document.odt)
	 * @param string $description share description (optional)
	 * @param string $providerId resource UID on the provider side
	 * @param string $owner provider specific UID of the user who owns the resource
	 * @param string $ownerDisplayName display name of the user who shared the item
	 * @param string $sharedBy provider specific UID of the user who shared the resource
	 * @param string $sharedByDisplayName display name of the user who shared the resource
	 * @param array $protocol (e,.g. ['name' => 'webdav', 'options' => ['username' => 'john', 'permissions' => 31]])
	 * @param string $shareType ('group' or 'user' share)
	 * @param $resourceType ('file', 'calendar',...)
	 * @return Http\DataResponse|JSONResponse
	 *
	 * Example: curl -H "Content-Type: application/json" -X POST -d '{"shareWith":"admin1@serve1","name":"welcome server2.txt","description":"desc","providerId":"2","owner":"admin2@http://localhost/server2","ownerDisplayName":"admin2 display","shareType":"user","resourceType":"file","protocol":{"name":"webdav","options":{"sharedSecret":"secret","permissions":"webdav-property"}}}' http://localhost/server/index.php/ocm/shares
	 */
	public function addShare($shareWith, $name, $description, $providerId, $owner, $ownerDisplayName, $sharedBy, $sharedByDisplayName, $protocol, $shareType, $resourceType) {

		// check if all required parameters are set
		if ($shareWith === null ||
			$name === null ||
			$providerId === null ||
			$owner === null ||
			$resourceType === null ||
			$shareType === null ||
			!is_array($protocol) ||
			!isset($protocol['name']) ||
			!isset($protocol['options']) ||
			!is_array($protocol['options']) ||
			!isset($protocol['options']['sharedSecret'])
		) {
			return new JSONResponse(
				['message' => 'Missing arguments'],
				Http::STATUS_BAD_REQUEST
			);
		}

		$supportedShareTypes = $this->config->getSupportedShareTypes($resourceType);
		if (!in_array($shareType, $supportedShareTypes)) {
			return new JSONResponse(
				['message' => 'Share type "' . $shareType . '" not implemented'],
				Http::STATUS_NOT_IMPLEMENTED
			);
		}

		$cloudId = $this->cloudIdManager->resolveCloudId($shareWith);
		$shareWith = $cloudId->getUser();

		if ($shareType === 'user') {
			$shareWith = $this->mapUid($shareWith);

			if (!$this->userManager->userExists($shareWith)) {
				return new JSONResponse(
					['message' => 'User "' . $shareWith . '" does not exists at ' . $this->urlGenerator->getBaseUrl()],
					Http::STATUS_BAD_REQUEST
				);
			}
		}

		if ($shareType === 'group') {
			if(!$this->groupManager->groupExists($shareWith)) {
				return new JSONResponse(
					['message' => 'Group "' . $shareWith . '" does not exists at ' . $this->urlGenerator->getBaseUrl()],
					Http::STATUS_BAD_REQUEST
				);
			}
		}

		// if no explicit display name is given, we use the uid as display name
		$ownerDisplayName = $ownerDisplayName === null ? $owner : $ownerDisplayName;
		$sharedByDisplayName = $sharedByDisplayName === null ? $sharedBy : $sharedByDisplayName;

		// sharedBy* parameter is optional, if nothing is set we assume that it is the same user as the owner
		if ($sharedBy === null) {
			$sharedBy = $owner;
			$sharedByDisplayName = $ownerDisplayName;
		}

		try {
			$provider = $this->cloudFederationProviderManager->getCloudFederationProvider($resourceType);
			$share = $this->factory->getCloudFederationShare($shareWith, $name, $description, $providerId, $owner, $ownerDisplayName, $sharedBy, $sharedByDisplayName, '', $shareType, $resourceType);
			$share->setProtocol($protocol);
			$provider->shareReceived($share);
		} catch (ProviderDoesNotExistsException $e) {
			return new JSONResponse(
				['message' => $e->getMessage()],
				Http::STATUS_NOT_IMPLEMENTED
			);
		} catch (ProviderCouldNotAddShareException $e) {
			return new JSONResponse(
				['message' => $e->getMessage()],
				$e->getCode()
			);
		} catch (\Exception $e) {
			return new JSONResponse(
				['message' => 'Internal error at ' . $this->urlGenerator->getBaseUrl()],
				Http::STATUS_BAD_REQUEST
			);
		}

		$user = $this->userManager->get($shareWith);
		$recipientDisplayName = '';
		if($user) {
			$recipientDisplayName = $user->getDisplayName();
		}

		return new JSONResponse(
			['recipientDisplayName' => $recipientDisplayName],
			Http::STATUS_CREATED);

	}

	/**
	 * receive notification about existing share
	 *
	 * @NoCSRFRequired
	 * @PublicPage
	 * @BruteForceProtection(action=receiveFederatedShareNotification)
	 *
	 * @param string $notificationType (notification type, e.g. SHARE_ACCEPTED)
	 * @param string $resourceType (calendar, file, contact,...)
	 * @param string $providerId id of the share
	 * @param array $notification the actual payload of the notification
	 * @return JSONResponse
	 */
	public function receiveNotification($notificationType, $resourceType, $providerId, array $notification) {

		// check if all required parameters are set
		if ($notificationType === null ||
			$resourceType === null ||
			$providerId === null ||
			!is_array($notification)
		) {
			return new JSONResponse(
				['message' => 'Missing arguments'],
				Http::STATUS_BAD_REQUEST
			);
		}

		try {
			$provider = $this->cloudFederationProviderManager->getCloudFederationProvider($resourceType);
			$result = $provider->notificationReceived($notificationType, $providerId, $notification);
		} catch (ProviderDoesNotExistsException $e) {
			return new JSONResponse(
				['message' => $e->getMessage()],
				Http::STATUS_BAD_REQUEST
			);
		} catch (ShareNotFound $e) {
			return new JSONResponse(
				['message' => $e->getMessage()],
				Http::STATUS_BAD_REQUEST
			);
		} catch (ActionNotSupportedException $e) {
			return new JSONResponse(
				['message' => $e->getMessage()],
				Http::STATUS_NOT_IMPLEMENTED
			);
		} catch (BadRequestException $e) {
			return new JSONResponse($e->getReturnMessage(), Http::STATUS_BAD_REQUEST);
		} catch (AuthenticationFailedException $e) {
			return new JSONResponse(["message" => "RESOURCE_NOT_FOUND"], Http::STATUS_FORBIDDEN);
		}
		catch (\Exception $e) {
			return new JSONResponse(
				['message' => 'Internal error at ' . $this->urlGenerator->getBaseUrl()],
				Http::STATUS_BAD_REQUEST
			);
		}

		return new JSONResponse($result,Http::STATUS_CREATED);

	}

	/**
	 * map login name to internal LDAP UID if a LDAP backend is in use
	 *
	 * @param string $uid
	 * @return string mixed
	 */
	private function mapUid($uid) {
		// FIXME this should be a method in the user management instead
		$this->logger->debug('shareWith before, ' . $uid, ['app' => $this->appName]);
		\OCP\Util::emitHook(
			'\OCA\Files_Sharing\API\Server2Server',
			'preLoginNameUsedAsUserName',
			['uid' => &$uid]
		);
		$this->logger->debug('shareWith after, ' . $uid, ['app' => $this->appName]);

		return $uid;
	}

}
