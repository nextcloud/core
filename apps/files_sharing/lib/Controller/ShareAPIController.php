<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Gary Kim <gary@garykim.dev>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author Maxence Lange <maxence@nextcloud.com>
 * @author Michael Jobst <mjobst+github@tecratech.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Richard Steinmetz <richard@steinmetz.cloud>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
 * @author waleczny <michal@walczak.xyz>
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

namespace OCA\Files_Sharing\Controller;

use OCA\Files_Sharing\Exceptions\SharingRightsException;
use OCA\Files_Sharing\External\Storage;
use OCA\Files\Helper;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\QueryException;
use OCP\Constants;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Share;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\UserStatus\IManager as IUserStatusManager;

/**
 * Class Share20OCS
 *
 * @package OCA\Files_Sharing\API
 */
class ShareAPIController extends OCSController {

	/** @var IManager */
	private $shareManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IUserManager */
	private $userManager;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var string */
	private $currentUser;
	/** @var IL10N */
	private $l;
	/** @var \OCP\Files\Node */
	private $lockedNode;
	/** @var IConfig */
	private $config;
	/** @var IAppManager */
	private $appManager;
	/** @var IServerContainer */
	private $serverContainer;
	/** @var IUserStatusManager */
	private $userStatusManager;
	/** @var IPreview */
	private $previewManager;

	/**
	 * Share20OCS constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IManager $shareManager
	 * @param IGroupManager $groupManager
	 * @param IUserManager $userManager
	 * @param IRootFolder $rootFolder
	 * @param IURLGenerator $urlGenerator
	 * @param string $userId
	 * @param IL10N $l10n
	 * @param IConfig $config
	 * @param IAppManager $appManager
	 * @param IServerContainer $serverContainer
	 * @param IUserStatusManager $userStatusManager
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		IManager $shareManager,
		IGroupManager $groupManager,
		IUserManager $userManager,
		IRootFolder $rootFolder,
		IURLGenerator $urlGenerator,
		string $userId = null,
		IL10N $l10n,
		IConfig $config,
		IAppManager $appManager,
		IServerContainer $serverContainer,
		IUserStatusManager $userStatusManager,
		IPreview $previewManager
	) {
		parent::__construct($appName, $request);

		$this->shareManager = $shareManager;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->request = $request;
		$this->rootFolder = $rootFolder;
		$this->urlGenerator = $urlGenerator;
		$this->currentUser = $userId;
		$this->l = $l10n;
		$this->config = $config;
		$this->appManager = $appManager;
		$this->serverContainer = $serverContainer;
		$this->userStatusManager = $userStatusManager;
		$this->previewManager = $previewManager;
	}

	/**
	 * Convert an IShare to an array for OCS output
	 *
	 * @param \OCP\Share\IShare $share
	 * @param Node|null $recipientNode
	 * @return array
	 * @throws NotFoundException In case the node can't be resolved.
	 *
	 * @suppress PhanUndeclaredClassMethod
	 */
	protected function formatShare(IShare $share, Node $recipientNode = null): array {
		$sharedBy = $this->userManager->get($share->getSharedBy());
		$shareOwner = $this->userManager->get($share->getShareOwner());

		$result = [
			'id' => $share->getId(),
			'share_type' => $share->getShareType(),
			'uid_owner' => $share->getSharedBy(),
			'displayname_owner' => $sharedBy !== null ? $sharedBy->getDisplayName() : $share->getSharedBy(),
			// recipient permissions
			'permissions' => $share->getPermissions(),
			// current user permissions on this share
			'can_edit' => $this->canEditShare($share),
			'can_delete' => $this->canDeleteShare($share),
			'stime' => $share->getShareTime()->getTimestamp(),
			'parent' => null,
			'expiration' => null,
			'token' => null,
			'uid_file_owner' => $share->getShareOwner(),
			'note' => $share->getNote(),
			'label' => $share->getLabel(),
			'displayname_file_owner' => $shareOwner !== null ? $shareOwner->getDisplayName() : $share->getShareOwner(),
		];

		$userFolder = $this->rootFolder->getUserFolder($this->currentUser);
		if ($recipientNode) {
			$node = $recipientNode;
		} else {
			$nodes = $userFolder->getById($share->getNodeId());
			if (empty($nodes)) {
				// fallback to guessing the path
				$node = $userFolder->get($share->getTarget());
				if ($node === null || $share->getTarget() === '') {
					throw new NotFoundException();
				}
			} else {
				$node = reset($nodes);
			}
		}

		$result['path'] = $userFolder->getRelativePath($node->getPath());
		if ($node instanceof Folder) {
			$result['item_type'] = 'folder';
		} else {
			$result['item_type'] = 'file';
		}

		$result['mimetype'] = $node->getMimetype();
		$result['has_preview'] = $this->previewManager->isAvailable($node);
		$result['storage_id'] = $node->getStorage()->getId();
		$result['storage'] = $node->getStorage()->getCache()->getNumericStorageId();
		$result['item_source'] = $node->getId();
		$result['file_source'] = $node->getId();
		$result['file_parent'] = $node->getParent()->getId();
		$result['file_target'] = $share->getTarget();

		$expiration = $share->getExpirationDate();
		if ($expiration !== null) {
			$result['expiration'] = $expiration->format('Y-m-d 00:00:00');
		}

		if ($share->getShareType() === IShare::TYPE_USER) {
			$sharedWith = $this->userManager->get($share->getSharedWith());
			$result['share_with'] = $share->getSharedWith();
			$result['share_with_displayname'] = $sharedWith !== null ? $sharedWith->getDisplayName() : $share->getSharedWith();
			$result['share_with_displayname_unique'] = $sharedWith !== null ? (
				 $sharedWith->getEMailAddress() !== '' ? $sharedWith->getEMailAddress() : $sharedWith->getUID()
			) : $share->getSharedWith();
			$result['status'] = [];

			$userStatuses = $this->userStatusManager->getUserStatuses([$share->getSharedWith()]);
			$userStatus = array_shift($userStatuses);
			if ($userStatus) {
				$result['status'] = [
					'status' => $userStatus->getStatus(),
					'message' => $userStatus->getMessage(),
					'icon' => $userStatus->getIcon(),
					'clearAt' => $userStatus->getClearAt()
						? (int)$userStatus->getClearAt()->format('U')
						: null,
				];
			}
		} elseif ($share->getShareType() === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($share->getSharedWith());
			$result['share_with'] = $share->getSharedWith();
			$result['share_with_displayname'] = $group !== null ? $group->getDisplayName() : $share->getSharedWith();
		} elseif ($share->getShareType() === IShare::TYPE_LINK) {

			// "share_with" and "share_with_displayname" for passwords of link
			// shares was deprecated in Nextcloud 15, use "password" instead.
			$result['share_with'] = $share->getPassword();
			$result['share_with_displayname'] = '(' . $this->l->t('Shared link') . ')';

			$result['password'] = $share->getPassword();

			$result['send_password_by_talk'] = $share->getSendPasswordByTalk();

			$result['token'] = $share->getToken();
			$result['url'] = $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $share->getToken()]);
		} elseif ($share->getShareType() === IShare::TYPE_REMOTE || $share->getShareType() === IShare::TYPE_REMOTE_GROUP) {
			$result['share_with'] = $share->getSharedWith();
			$result['share_with_displayname'] = $this->getDisplayNameFromAddressBook($share->getSharedWith(), 'CLOUD');
			$result['token'] = $share->getToken();
		} elseif ($share->getShareType() === IShare::TYPE_EMAIL) {
			$result['share_with'] = $share->getSharedWith();
			$result['password'] = $share->getPassword();
			$result['send_password_by_talk'] = $share->getSendPasswordByTalk();
			$result['share_with_displayname'] = $this->getDisplayNameFromAddressBook($share->getSharedWith(), 'EMAIL');
			$result['token'] = $share->getToken();
		} elseif ($share->getShareType() === IShare::TYPE_CIRCLE) {
			// getSharedWith() returns either "name (type, owner)" or
			// "name (type, owner) [id]", depending on the Circles app version.
			$hasCircleId = (substr($share->getSharedWith(), -1) === ']');

			$result['share_with_displayname'] = $share->getSharedWithDisplayName();
			if (empty($result['share_with_displayname'])) {
				$displayNameLength = ($hasCircleId ? strrpos($share->getSharedWith(), ' ') : strlen($share->getSharedWith()));
				$result['share_with_displayname'] = substr($share->getSharedWith(), 0, $displayNameLength);
			}

			$result['share_with_avatar'] = $share->getSharedWithAvatar();

			$shareWithStart = ($hasCircleId ? strrpos($share->getSharedWith(), '[') + 1 : 0);
			$shareWithLength = ($hasCircleId ? -1 : strpos($share->getSharedWith(), ' '));
			if (is_bool($shareWithLength)) {
				$shareWithLength = -1;
			}
			$result['share_with'] = substr($share->getSharedWith(), $shareWithStart, $shareWithLength);
		} elseif ($share->getShareType() === IShare::TYPE_ROOM) {
			$result['share_with'] = $share->getSharedWith();
			$result['share_with_displayname'] = '';

			try {
				$result = array_merge($result, $this->getRoomShareHelper()->formatShare($share));
			} catch (QueryException $e) {
			}
		} elseif ($share->getShareType() === IShare::TYPE_DECK) {
			$result['share_with'] = $share->getSharedWith();
			$result['share_with_displayname'] = '';

			try {
				$result = array_merge($result, $this->getDeckShareHelper()->formatShare($share));
			} catch (QueryException $e) {
			}
		}


		$result['mail_send'] = $share->getMailSend() ? 1 : 0;
		$result['hide_download'] = $share->getHideDownload() ? 1 : 0;

		return $result;
	}

	/**
	 * Check if one of the users address books knows the exact property, if
	 * yes we return the full name.
	 *
	 * @param string $query
	 * @param string $property
	 * @return string
	 */
	private function getDisplayNameFromAddressBook(string $query, string $property): string {
		// FIXME: If we inject the contacts manager it gets initialized bofore any address books are registered
		$result = \OC::$server->getContactsManager()->search($query, [$property]);
		foreach ($result as $r) {
			foreach ($r[$property] as $value) {
				if ($value === $query && $r['FN']) {
					return $r['FN'];
				}
			}
		}

		return $query;
	}

	/**
	 * Get a specific share by id
	 *
	 * @NoAdminRequired
	 *
	 * @param string $id
	 * @return DataResponse
	 * @throws OCSNotFoundException
	 */
	public function getShare(string $id): DataResponse {
		try {
			$share = $this->getShareById($id);
		} catch (ShareNotFound $e) {
			throw new OCSNotFoundException($this->l->t('Wrong share ID, share doesn\'t exist'));
		}

		try {
			if ($this->canAccessShare($share)) {
				$share = $this->formatShare($share);
				return new DataResponse([$share]);
			}
		} catch (NotFoundException $e) {
			// Fall trough
		}

		throw new OCSNotFoundException($this->l->t('Wrong share ID, share doesn\'t exist'));
	}

	/**
	 * Delete a share
	 *
	 * @NoAdminRequired
	 *
	 * @param string $id
	 * @return DataResponse
	 * @throws OCSNotFoundException
	 */
	public function deleteShare(string $id): DataResponse {
		try {
			$share = $this->getShareById($id);
		} catch (ShareNotFound $e) {
			throw new OCSNotFoundException($this->l->t('Wrong share ID, share doesn\'t exist'));
		}

		try {
			$this->lock($share->getNode());
		} catch (LockedException $e) {
			throw new OCSNotFoundException($this->l->t('Could not delete share'));
		}

		if (!$this->canAccessShare($share)) {
			throw new OCSNotFoundException($this->l->t('Wrong share ID, share doesn\'t exist'));
		}

		// if it's a group share or a room share
		// we don't delete the share, but only the
		// mount point. Allowing it to be restored
		// from the deleted shares
		if ($this->canDeleteShareFromSelf($share)) {
			$this->shareManager->deleteFromSelf($share, $this->currentUser);
		} else {
			if (!$this->canDeleteShare($share)) {
				throw new OCSForbiddenException($this->l->t('Could not delete share'));
			}

			$this->shareManager->deleteShare($share);
		}

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $path
	 * @param int $permissions
	 * @param int $shareType
	 * @param string $shareWith
	 * @param string $publicUpload
	 * @param string $password
	 * @param string $sendPasswordByTalk
	 * @param string $expireDate
	 * @param string $label
	 *
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws OCSBadRequestException
	 * @throws OCSException
	 * @throws OCSForbiddenException
	 * @throws OCSNotFoundException
	 * @throws InvalidPathException
	 * @suppress PhanUndeclaredClassMethod
	 */
	public function createShare(
		string $path = null,
		int $permissions = null,
		int $shareType = -1,
		string $shareWith = null,
		string $publicUpload = 'false',
		string $password = '',
		string $sendPasswordByTalk = null,
		string $expireDate = '',
		string $label = ''
	): DataResponse {
		$share = $this->shareManager->newShare();

		if ($permissions === null) {
			$permissions = $this->config->getAppValue('core', 'shareapi_default_permissions', Constants::PERMISSION_ALL);
		}

		// Verify path
		if ($path === null) {
			throw new OCSNotFoundException($this->l->t('Please specify a file or folder path'));
		}

		$userFolder = $this->rootFolder->getUserFolder($this->currentUser);
		try {
			$path = $userFolder->get($path);
		} catch (NotFoundException $e) {
			throw new OCSNotFoundException($this->l->t('Wrong path, file/folder doesn\'t exist'));
		}

		$share->setNode($path);

		try {
			$this->lock($share->getNode());
		} catch (LockedException $e) {
			throw new OCSNotFoundException($this->l->t('Could not create share'));
		}

		if ($permissions < 0 || $permissions > Constants::PERMISSION_ALL) {
			throw new OCSNotFoundException($this->l->t('invalid permissions'));
		}

		// Shares always require read permissions
		$permissions |= Constants::PERMISSION_READ;

		if ($path instanceof \OCP\Files\File) {
			// Single file shares should never have delete or create permissions
			$permissions &= ~Constants::PERMISSION_DELETE;
			$permissions &= ~Constants::PERMISSION_CREATE;
		}

		/**
		 * Hack for https://github.com/owncloud/core/issues/22587
		 * We check the permissions via webdav. But the permissions of the mount point
		 * do not equal the share permissions. Here we fix that for federated mounts.
		 */
		if ($path->getStorage()->instanceOfStorage(Storage::class)) {
			$permissions &= ~($permissions & ~$path->getPermissions());
		}

		if ($shareType === IShare::TYPE_USER) {
			// Valid user is required to share
			if ($shareWith === null || !$this->userManager->userExists($shareWith)) {
				throw new OCSNotFoundException($this->l->t('Please specify a valid user'));
			}
			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
		} elseif ($shareType === IShare::TYPE_GROUP) {
			if (!$this->shareManager->allowGroupSharing()) {
				throw new OCSNotFoundException($this->l->t('Group sharing is disabled by the administrator'));
			}

			// Valid group is required to share
			if ($shareWith === null || !$this->groupManager->groupExists($shareWith)) {
				throw new OCSNotFoundException($this->l->t('Please specify a valid group'));
			}
			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
		} elseif ($shareType === IShare::TYPE_LINK
			|| $shareType === IShare::TYPE_EMAIL) {

			// Can we even share links?
			if (!$this->shareManager->shareApiAllowLinks()) {
				throw new OCSNotFoundException($this->l->t('Public link sharing is disabled by the administrator'));
			}

			if ($publicUpload === 'true') {
				// Check if public upload is allowed
				if (!$this->shareManager->shareApiLinkAllowPublicUpload()) {
					throw new OCSForbiddenException($this->l->t('Public upload disabled by the administrator'));
				}

				// Public upload can only be set for folders
				if ($path instanceof \OCP\Files\File) {
					throw new OCSNotFoundException($this->l->t('Public upload is only possible for publicly shared folders'));
				}

				$permissions = Constants::PERMISSION_READ |
					Constants::PERMISSION_CREATE |
					Constants::PERMISSION_UPDATE |
					Constants::PERMISSION_DELETE;
			} else {
				$permissions = Constants::PERMISSION_READ;
			}

			// TODO: It might make sense to have a dedicated setting to allow/deny converting link shares into federated ones
			if (($permissions & Constants::PERMISSION_READ) && $this->shareManager->outgoingServer2ServerSharesAllowed()) {
				$permissions |= Constants::PERMISSION_SHARE;
			}

			$share->setPermissions($permissions);

			// Set password
			if ($password !== '') {
				$share->setPassword($password);
			}

			// Only share by mail have a recipient
			if ($shareType === IShare::TYPE_EMAIL) {
				$share->setSharedWith($shareWith);
			} else {
				// Only link share have a label
				if (!empty($label)) {
					$share->setLabel($label);
				}
			}

			if ($sendPasswordByTalk === 'true') {
				if (!$this->appManager->isEnabledForUser('spreed')) {
					throw new OCSForbiddenException($this->l->t('Sharing %s sending the password by Nextcloud Talk failed because Nextcloud Talk is not enabled', [$path->getPath()]));
				}

				$share->setSendPasswordByTalk(true);
			}

			//Expire date
			if ($expireDate !== '') {
				try {
					$expireDate = $this->parseDate($expireDate);
					$share->setExpirationDate($expireDate);
				} catch (\Exception $e) {
					throw new OCSNotFoundException($this->l->t('Invalid date, date format must be YYYY-MM-DD'));
				}
			}
		} elseif ($shareType === IShare::TYPE_REMOTE) {
			if (!$this->shareManager->outgoingServer2ServerSharesAllowed()) {
				throw new OCSForbiddenException($this->l->t('Sharing %1$s failed because the back end does not allow shares from type %2$s', [$path->getPath(), $shareType]));
			}

			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
		} elseif ($shareType === IShare::TYPE_REMOTE_GROUP) {
			if (!$this->shareManager->outgoingServer2ServerGroupSharesAllowed()) {
				throw new OCSForbiddenException($this->l->t('Sharing %1$s failed because the back end does not allow shares from type %2$s', [$path->getPath(), $shareType]));
			}

			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
		} elseif ($shareType === IShare::TYPE_CIRCLE) {
			if (!\OC::$server->getAppManager()->isEnabledForUser('circles') || !class_exists('\OCA\Circles\ShareByCircleProvider')) {
				throw new OCSNotFoundException($this->l->t('You cannot share to a Circle if the app is not enabled'));
			}

			$circle = \OCA\Circles\Api\v1\Circles::detailsCircle($shareWith);

			// Valid circle is required to share
			if ($circle === null) {
				throw new OCSNotFoundException($this->l->t('Please specify a valid circle'));
			}
			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
		} elseif ($shareType === IShare::TYPE_ROOM) {
			try {
				$this->getRoomShareHelper()->createShare($share, $shareWith, $permissions, $expireDate);
			} catch (QueryException $e) {
				throw new OCSForbiddenException($this->l->t('Sharing %s failed because the back end does not support room shares', [$path->getPath()]));
			}
		} elseif ($shareType === IShare::TYPE_DECK) {
			try {
				$this->getDeckShareHelper()->createShare($share, $shareWith, $permissions, $expireDate);
			} catch (QueryException $e) {
				throw new OCSForbiddenException($this->l->t('Sharing %s failed because the back end does not support room shares', [$path->getPath()]));
			}
		} else {
			throw new OCSBadRequestException($this->l->t('Unknown share type'));
		}

		$share->setShareType($shareType);
		$share->setSharedBy($this->currentUser);

		try {
			$share = $this->shareManager->createShare($share);
		} catch (GenericShareException $e) {
			$code = $e->getCode() === 0 ? 403 : $e->getCode();
			throw new OCSException($e->getHint(), $code);
		} catch (\Exception $e) {
			throw new OCSForbiddenException($e->getMessage(), $e);
		}

		$output = $this->formatShare($share);

		return new DataResponse($output);
	}

	/**
	 * @param null|Node $node
	 * @param boolean $includeTags
	 *
	 * @return array
	 */
	private function getSharedWithMe($node, bool $includeTags): array {
		$userShares = $this->shareManager->getSharedWith($this->currentUser, IShare::TYPE_USER, $node, -1, 0);
		$groupShares = $this->shareManager->getSharedWith($this->currentUser, IShare::TYPE_GROUP, $node, -1, 0);
		$circleShares = $this->shareManager->getSharedWith($this->currentUser, IShare::TYPE_CIRCLE, $node, -1, 0);
		$roomShares = $this->shareManager->getSharedWith($this->currentUser, IShare::TYPE_ROOM, $node, -1, 0);
		$deckShares = $this->shareManager->getSharedWith($this->currentUser, IShare::TYPE_DECK, $node, -1, 0);

		$shares = array_merge($userShares, $groupShares, $circleShares, $roomShares, $deckShares);

		$filteredShares = array_filter($shares, function (IShare $share) {
			return $share->getShareOwner() !== $this->currentUser;
		});

		$formatted = [];
		foreach ($filteredShares as $share) {
			if ($this->canAccessShare($share)) {
				try {
					$formatted[] = $this->formatShare($share);
				} catch (NotFoundException $e) {
					// Ignore this share
				}
			}
		}

		if ($includeTags) {
			$formatted = Helper::populateTags($formatted, 'file_source', \OC::$server->getTagManager());
		}

		return $formatted;
	}

	/**
	 * @param \OCP\Files\Node $folder
	 *
	 * @return array
	 * @throws OCSBadRequestException
	 * @throws NotFoundException
	 */
	private function getSharesInDir(Node $folder): array {
		if (!($folder instanceof \OCP\Files\Folder)) {
			throw new OCSBadRequestException($this->l->t('Not a directory'));
		}

		$nodes = $folder->getDirectoryListing();

		/** @var \OCP\Share\IShare[] $shares */
		$shares = array_reduce($nodes, function ($carry, $node) {
			$carry = array_merge($carry, $this->getAllShares($node, true));
			return $carry;
		}, []);

		// filter out duplicate shares
		$known = [];


		$formatted = $miniFormatted = [];
		$resharingRight = false;
		$known = [];
		foreach ($shares as $share) {
			if (in_array($share->getId(), $known) || $share->getSharedWith() === $this->currentUser) {
				continue;
			}

			try {
				$format = $this->formatShare($share);

				$known[] = $share->getId();
				$formatted[] = $format;
				if ($share->getSharedBy() === $this->currentUser) {
					$miniFormatted[] = $format;
				}
				if (!$resharingRight && $this->shareProviderResharingRights($this->currentUser, $share, $folder)) {
					$resharingRight = true;
				}
			} catch (\Exception $e) {
				//Ignore this share
			}
		}

		if (!$resharingRight) {
			$formatted = $miniFormatted;
		}

		return $formatted;
	}

	/**
	 * The getShares function.
	 *
	 * @NoAdminRequired
	 *
	 * @param string $shared_with_me
	 * @param string $reshares
	 * @param string $subfiles
	 * @param string $path
	 *
	 * - Get shares by the current user
	 * - Get shares by the current user and reshares (?reshares=true)
	 * - Get shares with the current user (?shared_with_me=true)
	 * - Get shares for a specific path (?path=...)
	 * - Get all shares in a folder (?subfiles=true&path=..)
	 *
	 * @param string $include_tags
	 *
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws OCSBadRequestException
	 * @throws OCSNotFoundException
	 */
	public function getShares(
		string $shared_with_me = 'false',
		string $reshares = 'false',
		string $subfiles = 'false',
		string $path = '',
		string $include_tags = 'false'
	): DataResponse {
		$node = null;
		if ($path !== '') {
			$userFolder = $this->rootFolder->getUserFolder($this->currentUser);
			try {
				$node = $userFolder->get($path);
				$this->lock($node);
			} catch (NotFoundException $e) {
				throw new OCSNotFoundException(
					$this->l->t('Wrong path, file/folder doesn\'t exist')
				);
			} catch (LockedException $e) {
				throw new OCSNotFoundException($this->l->t('Could not lock node'));
			}
		}

		$shares = $this->getFormattedShares(
			$this->currentUser,
			$node,
			($shared_with_me === 'true'),
			($reshares === 'true'),
			($subfiles === 'true'),
			($include_tags === 'true')
		);

		return new DataResponse($shares);
	}


	/**
	 * @param string $viewer
	 * @param Node $node
	 * @param bool $sharedWithMe
	 * @param bool $reShares
	 * @param bool $subFiles
	 * @param bool $includeTags
	 *
	 * @return array
	 * @throws NotFoundException
	 * @throws OCSBadRequestException
	 */
	private function getFormattedShares(
		string $viewer,
		$node = null,
		bool $sharedWithMe = false,
		bool $reShares = false,
		bool $subFiles = false,
		bool $includeTags = false
	): array {
		if ($sharedWithMe) {
			return $this->getSharedWithMe($node, $includeTags);
		}

		if ($subFiles) {
			return $this->getSharesInDir($node);
		}

		$shares = $this->getSharesFromNode($viewer, $node, $reShares);

		$known = $formatted = $miniFormatted = [];
		$resharingRight = false;
		foreach ($shares as $share) {
			try {
				$share->getNode();
			} catch (NotFoundException $e) {
				/*
				 * Ignore shares where we can't get the node
				 * For example deleted shares
				 */
				continue;
			}

			if (in_array($share->getId(), $known)
				|| ($share->getSharedWith() === $this->currentUser && $share->getShareType() === IShare::TYPE_USER)) {
				continue;
			}

			$known[] = $share->getId();
			try {
				/** @var IShare $share */
				$format = $this->formatShare($share, $node);
				$formatted[] = $format;

				// let's also build a list of shares created
				// by the current user only, in case
				// there is no resharing rights
				if ($share->getSharedBy() === $this->currentUser) {
					$miniFormatted[] = $format;
				}

				// check if one of those share is shared with me
				// and if I have resharing rights on it
				if (!$resharingRight && $this->shareProviderResharingRights($this->currentUser, $share, $node)) {
					$resharingRight = true;
				}
			} catch (InvalidPathException | NotFoundException $e) {
			}
		}

		if (!$resharingRight) {
			$formatted = $miniFormatted;
		}

		if ($includeTags) {
			$formatted =
				Helper::populateTags($formatted, 'file_source', \OC::$server->getTagManager());
		}

		return $formatted;
	}


	/**
	 * The getInheritedShares function.
	 * returns all shares relative to a file, including parent folders shares rights.
	 *
	 * @NoAdminRequired
	 *
	 * @param string $path
	 *
	 * - Get shares by the current user
	 * - Get shares by the current user and reshares (?reshares=true)
	 * - Get shares with the current user (?shared_with_me=true)
	 * - Get shares for a specific path (?path=...)
	 * - Get all shares in a folder (?subfiles=true&path=..)
	 *
	 * @return DataResponse
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws OCSNotFoundException
	 * @throws OCSBadRequestException
	 * @throws SharingRightsException
	 */
	public function getInheritedShares(string $path): DataResponse {

		// get Node from (string) path.
		$userFolder = $this->rootFolder->getUserFolder($this->currentUser);
		try {
			$node = $userFolder->get($path);
			$this->lock($node);
		} catch (\OCP\Files\NotFoundException $e) {
			throw new OCSNotFoundException($this->l->t('Wrong path, file/folder doesn\'t exist'));
		} catch (LockedException $e) {
			throw new OCSNotFoundException($this->l->t('Could not lock path'));
		}

		if (!($node->getPermissions() & Constants::PERMISSION_SHARE)) {
			throw new SharingRightsException('no sharing rights on this item');
		}

		// The current top parent we have access to
		$parent = $node;

		// initiate real owner.
		$owner = $node->getOwner()
					  ->getUID();
		if (!$this->userManager->userExists($owner)) {
			return new DataResponse([]);
		}

		// get node based on the owner, fix owner in case of external storage
		$userFolder = $this->rootFolder->getUserFolder($owner);
		if ($node->getId() !== $userFolder->getId() && !$userFolder->isSubNode($node)) {
			$owner = $node->getOwner()
						  ->getUID();
			$userFolder = $this->rootFolder->getUserFolder($owner);
			$nodes = $userFolder->getById($node->getId());
			$node = array_shift($nodes);
		}
		$basePath = $userFolder->getPath();

		// generate node list for each parent folders
		/** @var Node[] $nodes */
		$nodes = [];
		while ($node->getPath() !== $basePath) {
			$node = $node->getParent();
			$nodes[] = $node;
		}

		// The user that is requesting this list
		$currentUserFolder = $this->rootFolder->getUserFolder($this->currentUser);

		// for each nodes, retrieve shares.
		$shares = [];

		foreach ($nodes as $node) {
			$getShares = $this->getFormattedShares($owner, $node, false, true);

			$currentUserNodes = $currentUserFolder->getById($node->getId());
			if (!empty($currentUserNodes)) {
				$parent = array_pop($currentUserNodes);
			}

			$subPath = $currentUserFolder->getRelativePath($parent->getPath());
			foreach ($getShares as &$share) {
				$share['via_fileid'] = $parent->getId();
				$share['via_path'] = $subPath;
			}
			$this->mergeFormattedShares($shares, $getShares);
		}

		return new DataResponse(array_values($shares));
	}


	/**
	 * @NoAdminRequired
	 *
	 * @param string $id
	 * @param int $permissions
	 * @param string $password
	 * @param string $sendPasswordByTalk
	 * @param string $publicUpload
	 * @param string $expireDate
	 * @param string $note
	 * @param string $label
	 * @param string $hideDownload
	 * @return DataResponse
	 * @throws LockedException
	 * @throws NotFoundException
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 * @throws OCSNotFoundException
	 */
	public function updateShare(
		string $id,
		int $permissions = null,
		string $password = null,
		string $sendPasswordByTalk = null,
		string $publicUpload = null,
		string $expireDate = null,
		string $note = null,
		string $label = null,
		string $hideDownload = null
	): DataResponse {
		try {
			$share = $this->getShareById($id);
		} catch (ShareNotFound $e) {
			throw new OCSNotFoundException($this->l->t('Wrong share ID, share doesn\'t exist'));
		}

		$this->lock($share->getNode());

		if (!$this->canAccessShare($share, false)) {
			throw new OCSNotFoundException($this->l->t('Wrong share ID, share doesn\'t exist'));
		}

		if (!$this->canEditShare($share)) {
			throw new OCSForbiddenException('You are not allowed to edit incoming shares');
		}

		if (
			$permissions === null &&
			$password === null &&
			$sendPasswordByTalk === null &&
			$publicUpload === null &&
			$expireDate === null &&
			$note === null &&
			$label === null &&
			$hideDownload === null
		) {
			throw new OCSBadRequestException($this->l->t('Wrong or no update parameter given'));
		}

		if ($note !== null) {
			$share->setNote($note);
		}

		/**
		 * expirationdate, password and publicUpload only make sense for link shares
		 */
		if ($share->getShareType() === IShare::TYPE_LINK
			|| $share->getShareType() === IShare::TYPE_EMAIL) {

			/**
			 * We do not allow editing link shares that the current user
			 * doesn't own. This is confusing and lead to errors when
			 * someone else edit a password or expiration date without
			 * the share owner knowing about it.
			 * We only allow deletion
			 */

			if ($share->getSharedBy() !== $this->currentUser) {
				throw new OCSForbiddenException('You are not allowed to edit link shares that you don\'t own');
			}

			// Update hide download state
			if ($hideDownload === 'true') {
				$share->setHideDownload(true);
			} elseif ($hideDownload === 'false') {
				$share->setHideDownload(false);
			}

			$newPermissions = null;
			if ($publicUpload === 'true') {
				$newPermissions = Constants::PERMISSION_READ | Constants::PERMISSION_CREATE | Constants::PERMISSION_UPDATE | Constants::PERMISSION_DELETE;
			} elseif ($publicUpload === 'false') {
				$newPermissions = Constants::PERMISSION_READ;
			}

			if ($permissions !== null) {
				$newPermissions = $permissions;
				$newPermissions = $newPermissions & ~Constants::PERMISSION_SHARE;
			}

			if ($newPermissions !== null &&
				!in_array($newPermissions, [
					Constants::PERMISSION_READ,
					Constants::PERMISSION_READ | Constants::PERMISSION_CREATE | Constants::PERMISSION_UPDATE, // legacy
					Constants::PERMISSION_READ | Constants::PERMISSION_CREATE | Constants::PERMISSION_UPDATE | Constants::PERMISSION_DELETE, // correct
					Constants::PERMISSION_CREATE, // hidden file list
					Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE, // allow to edit single files
				], true)
			) {
				throw new OCSBadRequestException($this->l->t('Can\'t change permissions for public share links'));
			}

			if (
				// legacy
				$newPermissions === (Constants::PERMISSION_READ | Constants::PERMISSION_CREATE | Constants::PERMISSION_UPDATE) ||
				// correct
				$newPermissions === (Constants::PERMISSION_READ | Constants::PERMISSION_CREATE | Constants::PERMISSION_UPDATE | Constants::PERMISSION_DELETE)
			) {
				if (!$this->shareManager->shareApiLinkAllowPublicUpload()) {
					throw new OCSForbiddenException($this->l->t('Public upload disabled by the administrator'));
				}

				if (!($share->getNode() instanceof \OCP\Files\Folder)) {
					throw new OCSBadRequestException($this->l->t('Public upload is only possible for publicly shared folders'));
				}

				// normalize to correct public upload permissions
				$newPermissions = Constants::PERMISSION_READ | Constants::PERMISSION_CREATE | Constants::PERMISSION_UPDATE | Constants::PERMISSION_DELETE;
			}

			if ($newPermissions !== null) {
				// TODO: It might make sense to have a dedicated setting to allow/deny converting link shares into federated ones
				if (($newPermissions & Constants::PERMISSION_READ) && $this->shareManager->outgoingServer2ServerSharesAllowed()) {
					$newPermissions |= Constants::PERMISSION_SHARE;
				}

				$share->setPermissions($newPermissions);
				$permissions = $newPermissions;
			}

			if ($expireDate === '') {
				$share->setExpirationDate(null);
			} elseif ($expireDate !== null) {
				try {
					$expireDate = $this->parseDate($expireDate);
				} catch (\Exception $e) {
					throw new OCSBadRequestException($e->getMessage(), $e);
				}
				$share->setExpirationDate($expireDate);
			}

			if ($password === '') {
				$share->setPassword(null);
			} elseif ($password !== null) {
				$share->setPassword($password);
			}

			// only link shares have labels
			if ($share->getShareType() === IShare::TYPE_LINK && $label !== null) {
				if (strlen($label) > 255) {
					throw new OCSBadRequestException("Maxmimum label length is 255");
				}
				$share->setLabel($label);
			}

			if ($sendPasswordByTalk === 'true') {
				if (!$this->appManager->isEnabledForUser('spreed')) {
					throw new OCSForbiddenException($this->l->t('Sharing sending the password by Nextcloud Talk failed because Nextcloud Talk is not enabled'));
				}

				$share->setSendPasswordByTalk(true);
			} elseif ($sendPasswordByTalk !== null) {
				$share->setSendPasswordByTalk(false);
			}
		}

		// NOT A LINK SHARE
		else {
			if ($permissions !== null) {
				$share->setPermissions($permissions);
			}

			if ($expireDate === '') {
				$share->setExpirationDate(null);
			} elseif ($expireDate !== null) {
				try {
					$expireDate = $this->parseDate($expireDate);
				} catch (\Exception $e) {
					throw new OCSBadRequestException($e->getMessage(), $e);
				}
				$share->setExpirationDate($expireDate);
			}
		}

		try {
			$share = $this->shareManager->updateShare($share);
		} catch (GenericShareException $e) {
			$code = $e->getCode() === 0 ? 403 : $e->getCode();
			throw new OCSException($e->getHint(), $code);
		} catch (\Exception $e) {
			throw new OCSBadRequestException($e->getMessage(), $e);
		}

		return new DataResponse($this->formatShare($share));
	}

	/**
	 * @NoAdminRequired
	 */
	public function pendingShares(): DataResponse {
		$pendingShares = [];

		$shareTypes = [
			IShare::TYPE_USER,
			IShare::TYPE_GROUP
		];

		foreach ($shareTypes as $shareType) {
			$shares = $this->shareManager->getSharedWith($this->currentUser, $shareType, null, -1, 0);

			foreach ($shares as $share) {
				if ($share->getStatus() === IShare::STATUS_PENDING || $share->getStatus() === IShare::STATUS_REJECTED) {
					$pendingShares[] = $share;
				}
			}
		}

		$result = array_filter(array_map(function (IShare $share) {
			$userFolder = $this->rootFolder->getUserFolder($share->getSharedBy());
			$nodes = $userFolder->getById($share->getNodeId());
			if (empty($nodes)) {
				// fallback to guessing the path
				$node = $userFolder->get($share->getTarget());
				if ($node === null || $share->getTarget() === '') {
					return null;
				}
			} else {
				$node = $nodes[0];
			}

			try {
				$formattedShare = $this->formatShare($share, $node);
				$formattedShare['status'] = $share->getStatus();
				$formattedShare['path'] = $share->getNode()->getName();
				$formattedShare['permissions'] = 0;
				return $formattedShare;
			} catch (NotFoundException $e) {
				return null;
			}
		}, $pendingShares), function ($entry) {
			return $entry !== null;
		});

		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $id
	 * @return DataResponse
	 * @throws OCSNotFoundException
	 * @throws OCSException
	 * @throws OCSBadRequestException
	 */
	public function acceptShare(string $id): DataResponse {
		try {
			$share = $this->getShareById($id);
		} catch (ShareNotFound $e) {
			throw new OCSNotFoundException($this->l->t('Wrong share ID, share doesn\'t exist'));
		}

		if (!$this->canAccessShare($share)) {
			throw new OCSNotFoundException($this->l->t('Wrong share ID, share doesn\'t exist'));
		}

		try {
			$this->shareManager->acceptShare($share, $this->currentUser);
		} catch (GenericShareException $e) {
			$code = $e->getCode() === 0 ? 403 : $e->getCode();
			throw new OCSException($e->getHint(), $code);
		} catch (\Exception $e) {
			throw new OCSBadRequestException($e->getMessage(), $e);
		}

		return new DataResponse();
	}

	/**
	 * Does the user have read permission on the share
	 *
	 * @param \OCP\Share\IShare $share the share to check
	 * @param boolean $checkGroups check groups as well?
	 * @return boolean
	 * @throws NotFoundException
	 *
	 * @suppress PhanUndeclaredClassMethod
	 */
	protected function canAccessShare(\OCP\Share\IShare $share, bool $checkGroups = true): bool {
		// A file with permissions 0 can't be accessed by us. So Don't show it
		if ($share->getPermissions() === 0) {
			return false;
		}

		// Owner of the file and the sharer of the file can always get share
		if ($share->getShareOwner() === $this->currentUser
			|| $share->getSharedBy() === $this->currentUser) {
			return true;
		}

		// If the share is shared with you, you can access it!
		if ($share->getShareType() === IShare::TYPE_USER
			&& $share->getSharedWith() === $this->currentUser) {
			return true;
		}

		// Have reshare rights on the shared file/folder ?
		// Does the currentUser have access to the shared file?
		$userFolder = $this->rootFolder->getUserFolder($this->currentUser);
		$files = $userFolder->getById($share->getNodeId());
		if (!empty($files) && $this->shareProviderResharingRights($this->currentUser, $share, $files[0])) {
			return true;
		}

		// If in the recipient group, you can see the share
		if ($checkGroups && $share->getShareType() === IShare::TYPE_GROUP) {
			$sharedWith = $this->groupManager->get($share->getSharedWith());
			$user = $this->userManager->get($this->currentUser);
			if ($user !== null && $sharedWith !== null && $sharedWith->inGroup($user)) {
				return true;
			}
		}

		if ($share->getShareType() === IShare::TYPE_CIRCLE) {
			// TODO: have a sanity check like above?
			return true;
		}

		if ($share->getShareType() === IShare::TYPE_ROOM) {
			try {
				return $this->getRoomShareHelper()->canAccessShare($share, $this->currentUser);
			} catch (QueryException $e) {
				return false;
			}
		}

		if ($share->getShareType() === IShare::TYPE_DECK) {
			try {
				return $this->getDeckShareHelper()->canAccessShare($share, $this->currentUser);
			} catch (QueryException $e) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Does the user have edit permission on the share
	 *
	 * @param \OCP\Share\IShare $share the share to check
	 * @return boolean
	 */
	protected function canEditShare(\OCP\Share\IShare $share): bool {
		// A file with permissions 0 can't be accessed by us. So Don't show it
		if ($share->getPermissions() === 0) {
			return false;
		}

		// The owner of the file and the creator of the share
		// can always edit the share
		if ($share->getShareOwner() === $this->currentUser ||
			$share->getSharedBy() === $this->currentUser
		) {
			return true;
		}

		//! we do NOT support some kind of `admin` in groups.
		//! You cannot edit shares shared to a group you're
		//! a member of if you're not the share owner or the file owner!

		return false;
	}

	/**
	 * Does the user have delete permission on the share
	 *
	 * @param \OCP\Share\IShare $share the share to check
	 * @return boolean
	 */
	protected function canDeleteShare(\OCP\Share\IShare $share): bool {
		// A file with permissions 0 can't be accessed by us. So Don't show it
		if ($share->getPermissions() === 0) {
			return false;
		}

		// if the user is the recipient, i can unshare
		// the share with self
		if ($share->getShareType() === IShare::TYPE_USER &&
			$share->getSharedWith() === $this->currentUser
		) {
			return true;
		}

		// The owner of the file and the creator of the share
		// can always delete the share
		if ($share->getShareOwner() === $this->currentUser ||
			$share->getSharedBy() === $this->currentUser
		) {
			return true;
		}

		return false;
	}

	/**
	 * Does the user have delete permission on the share
	 * This differs from the canDeleteShare function as it only
	 * remove the share for the current user. It does NOT
	 * completely delete the share but only the mount point.
	 * It can then be restored from the deleted shares section.
	 *
	 * @param \OCP\Share\IShare $share the share to check
	 * @return boolean
	 *
	 * @suppress PhanUndeclaredClassMethod
	 */
	protected function canDeleteShareFromSelf(\OCP\Share\IShare $share): bool {
		if ($share->getShareType() !== IShare::TYPE_GROUP &&
			$share->getShareType() !== IShare::TYPE_ROOM &&
			$share->getShareType() !== IShare::TYPE_DECK
		) {
			return false;
		}

		if ($share->getShareOwner() === $this->currentUser ||
			$share->getSharedBy() === $this->currentUser
		) {
			// Delete the whole share, not just for self
			return false;
		}

		// If in the recipient group, you can delete the share from self
		if ($share->getShareType() === IShare::TYPE_GROUP) {
			$sharedWith = $this->groupManager->get($share->getSharedWith());
			$user = $this->userManager->get($this->currentUser);
			if ($user !== null && $sharedWith !== null && $sharedWith->inGroup($user)) {
				return true;
			}
		}

		if ($share->getShareType() === IShare::TYPE_ROOM) {
			try {
				return $this->getRoomShareHelper()->canAccessShare($share, $this->currentUser);
			} catch (QueryException $e) {
				return false;
			}
		}

		if ($share->getShareType() === IShare::TYPE_DECK) {
			try {
				return $this->getDeckShareHelper()->canAccessShare($share, $this->currentUser);
			} catch (QueryException $e) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Make sure that the passed date is valid ISO 8601
	 * So YYYY-MM-DD
	 * If not throw an exception
	 *
	 * @param string $expireDate
	 *
	 * @throws \Exception
	 * @return \DateTime
	 */
	private function parseDate(string $expireDate): \DateTime {
		try {
			$date = new \DateTime($expireDate);
		} catch (\Exception $e) {
			throw new \Exception('Invalid date. Format must be YYYY-MM-DD');
		}

		if ($date === false) {
			throw new \Exception('Invalid date. Format must be YYYY-MM-DD');
		}

		$date->setTime(0, 0, 0);

		return $date;
	}

	/**
	 * Since we have multiple providers but the OCS Share API v1 does
	 * not support this we need to check all backends.
	 *
	 * @param string $id
	 * @return \OCP\Share\IShare
	 * @throws ShareNotFound
	 */
	private function getShareById(string $id): IShare {
		$share = null;

		// First check if it is an internal share.
		try {
			$share = $this->shareManager->getShareById('ocinternal:' . $id, $this->currentUser);
			return $share;
		} catch (ShareNotFound $e) {
			// Do nothing, just try the other share type
		}


		try {
			if ($this->shareManager->shareProviderExists(IShare::TYPE_CIRCLE)) {
				$share = $this->shareManager->getShareById('ocCircleShare:' . $id, $this->currentUser);
				return $share;
			}
		} catch (ShareNotFound $e) {
			// Do nothing, just try the other share type
		}

		try {
			if ($this->shareManager->shareProviderExists(IShare::TYPE_EMAIL)) {
				$share = $this->shareManager->getShareById('ocMailShare:' . $id, $this->currentUser);
				return $share;
			}
		} catch (ShareNotFound $e) {
			// Do nothing, just try the other share type
		}

		try {
			$share = $this->shareManager->getShareById('ocRoomShare:' . $id, $this->currentUser);
			return $share;
		} catch (ShareNotFound $e) {
			// Do nothing, just try the other share type
		}

		try {
			if ($this->shareManager->shareProviderExists(IShare::TYPE_DECK)) {
				$share = $this->shareManager->getShareById('deck:' . $id, $this->currentUser);
				return $share;
			}
		} catch (ShareNotFound $e) {
			// Do nothing, just try the other share type
		}

		if (!$this->shareManager->outgoingServer2ServerSharesAllowed()) {
			throw new ShareNotFound();
		}
		$share = $this->shareManager->getShareById('ocFederatedSharing:' . $id, $this->currentUser);

		return $share;
	}

	/**
	 * Lock a Node
	 *
	 * @param \OCP\Files\Node $node
	 * @throws LockedException
	 */
	private function lock(\OCP\Files\Node $node) {
		$node->lock(ILockingProvider::LOCK_SHARED);
		$this->lockedNode = $node;
	}

	/**
	 * Cleanup the remaining locks
	 * @throws LockedException
	 */
	public function cleanup() {
		if ($this->lockedNode !== null) {
			$this->lockedNode->unlock(ILockingProvider::LOCK_SHARED);
		}
	}

	/**
	 * Returns the helper of ShareAPIController for room shares.
	 *
	 * If the Talk application is not enabled or the helper is not available
	 * a QueryException is thrown instead.
	 *
	 * @return \OCA\Talk\Share\Helper\ShareAPIController
	 * @throws QueryException
	 */
	private function getRoomShareHelper() {
		if (!$this->appManager->isEnabledForUser('spreed')) {
			throw new QueryException();
		}

		return $this->serverContainer->get('\OCA\Talk\Share\Helper\ShareAPIController');
	}

	/**
	 * Returns the helper of ShareAPIHelper for deck shares.
	 *
	 * If the Deck application is not enabled or the helper is not available
	 * a QueryException is thrown instead.
	 *
	 * @return \OCA\Deck\Sharing\ShareAPIHelper
	 * @throws QueryException
	 */
	private function getDeckShareHelper() {
		if (!$this->appManager->isEnabledForUser('deck')) {
			throw new QueryException();
		}

		return $this->serverContainer->get('\OCA\Deck\Sharing\ShareAPIHelper');
	}

	/**
	 * @param string $viewer
	 * @param Node $node
	 * @param bool $reShares
	 *
	 * @return IShare[]
	 */
	private function getSharesFromNode(string $viewer, $node, bool $reShares): array {
		$providers = [
			IShare::TYPE_USER,
			IShare::TYPE_GROUP,
			IShare::TYPE_LINK,
			IShare::TYPE_EMAIL,
			IShare::TYPE_EMAIL,
			IShare::TYPE_CIRCLE,
			IShare::TYPE_ROOM,
			IShare::TYPE_DECK
		];

		// Should we assume that the (currentUser) viewer is the owner of the node !?
		$shares = [];
		foreach ($providers as $provider) {
			if (!$this->shareManager->shareProviderExists($provider)) {
				continue;
			}

			$providerShares =
				$this->shareManager->getSharesBy($viewer, $provider, $node, $reShares, -1, 0);
			$shares = array_merge($shares, $providerShares);
		}

		if ($this->shareManager->outgoingServer2ServerSharesAllowed()) {
			$federatedShares = $this->shareManager->getSharesBy(
				$this->currentUser, IShare::TYPE_REMOTE, $node, $reShares, -1, 0
			);
			$shares = array_merge($shares, $federatedShares);
		}

		if ($this->shareManager->outgoingServer2ServerGroupSharesAllowed()) {
			$federatedShares = $this->shareManager->getSharesBy(
				$this->currentUser, IShare::TYPE_REMOTE_GROUP, $node, $reShares, -1, 0
			);
			$shares = array_merge($shares, $federatedShares);
		}

		return $shares;
	}


	/**
	 * @param Node $node
	 *
	 * @throws SharingRightsException
	 */
	private function confirmSharingRights(Node $node): void {
		if (!$this->hasResharingRights($this->currentUser, $node)) {
			throw new SharingRightsException('no sharing rights on this item');
		}
	}


	/**
	 * @param string $viewer
	 * @param Node $node
	 *
	 * @return bool
	 */
	private function hasResharingRights($viewer, $node): bool {
		if ($viewer === $node->getOwner()->getUID()) {
			return true;
		}

		foreach ([$node, $node->getParent()] as $node) {
			$shares = $this->getSharesFromNode($viewer, $node, true);
			foreach ($shares as $share) {
				try {
					if ($this->shareProviderResharingRights($viewer, $share, $node)) {
						return true;
					}
				} catch (InvalidPathException | NotFoundException $e) {
				}
			}
		}

		return false;
	}


	/**
	 * Returns if we can find resharing rights in an IShare object for a specific user.
	 *
	 * @suppress PhanUndeclaredClassMethod
	 *
	 * @param string $userId
	 * @param IShare $share
	 * @param Node $node
	 *
	 * @return bool
	 * @throws NotFoundException
	 * @throws InvalidPathException
	 */
	private function shareProviderResharingRights(string $userId, IShare $share, $node): bool {
		if ($share->getShareOwner() === $userId) {
			return true;
		}

		// we check that current user have parent resharing rights on the current file
		if ($node !== null && ($node->getPermissions() & Constants::PERMISSION_SHARE) !== 0) {
			return true;
		}

		if ((\OCP\Constants::PERMISSION_SHARE & $share->getPermissions()) === 0) {
			return false;
		}

		if ($share->getShareType() === IShare::TYPE_USER && $share->getSharedWith() === $userId) {
			return true;
		}

		if ($share->getShareType() === IShare::TYPE_GROUP && $this->groupManager->isInGroup($userId, $share->getSharedWith())) {
			return true;
		}

		if ($share->getShareType() === IShare::TYPE_CIRCLE && \OC::$server->getAppManager()->isEnabledForUser('circles')
			&& class_exists('\OCA\Circles\Api\v1\Circles')) {
			$hasCircleId = (substr($share->getSharedWith(), -1) === ']');
			$shareWithStart = ($hasCircleId ? strrpos($share->getSharedWith(), '[') + 1 : 0);
			$shareWithLength = ($hasCircleId ? -1 : strpos($share->getSharedWith(), ' '));
			if ($shareWithLength === false) {
				$sharedWith = substr($share->getSharedWith(), $shareWithStart);
			} else {
				$sharedWith = substr($share->getSharedWith(), $shareWithStart, $shareWithLength);
			}
			try {
				$member = \OCA\Circles\Api\v1\Circles::getMember($sharedWith, $userId, 1);
				if ($member->getLevel() >= 4) {
					return true;
				}
				return false;
			} catch (QueryException $e) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Get all the shares for the current user
	 *
	 * @param Node|null $path
	 * @param boolean $reshares
	 * @return IShare[]
	 */
	private function getAllShares(?Node $path = null, bool $reshares = false) {
		// Get all shares
		$userShares = $this->shareManager->getSharesBy($this->currentUser, IShare::TYPE_USER, $path, $reshares, -1, 0);
		$groupShares = $this->shareManager->getSharesBy($this->currentUser, IShare::TYPE_GROUP, $path, $reshares, -1, 0);
		$linkShares = $this->shareManager->getSharesBy($this->currentUser, IShare::TYPE_LINK, $path, $reshares, -1, 0);

		// EMAIL SHARES
		$mailShares = $this->shareManager->getSharesBy($this->currentUser, IShare::TYPE_EMAIL, $path, $reshares, -1, 0);

		// CIRCLE SHARES
		$circleShares = $this->shareManager->getSharesBy($this->currentUser, IShare::TYPE_CIRCLE, $path, $reshares, -1, 0);

		// TALK SHARES
		$roomShares = $this->shareManager->getSharesBy($this->currentUser, IShare::TYPE_ROOM, $path, $reshares, -1, 0);

		$deckShares = $this->shareManager->getSharesBy($this->currentUser, IShare::TYPE_DECK, $path, $reshares, -1, 0);

		// FEDERATION
		if ($this->shareManager->outgoingServer2ServerSharesAllowed()) {
			$federatedShares = $this->shareManager->getSharesBy($this->currentUser, IShare::TYPE_REMOTE, $path, $reshares, -1, 0);
		} else {
			$federatedShares = [];
		}
		if ($this->shareManager->outgoingServer2ServerGroupSharesAllowed()) {
			$federatedGroupShares = $this->shareManager->getSharesBy($this->currentUser, IShare::TYPE_REMOTE_GROUP, $path, $reshares, -1, 0);
		} else {
			$federatedGroupShares = [];
		}

		return array_merge($userShares, $groupShares, $linkShares, $mailShares, $circleShares, $roomShares, $deckShares, $federatedShares, $federatedGroupShares);
	}


	/**
	 * merging already formatted shares.
	 * We'll make an associative array to easily detect duplicate Ids.
	 * Keys _needs_ to be removed after all shares are retrieved and merged.
	 *
	 * @param array $shares
	 * @param array $newShares
	 */
	private function mergeFormattedShares(array &$shares, array $newShares) {
		foreach ($newShares as $newShare) {
			if (!array_key_exists($newShare['id'], $shares)) {
				$shares[$newShare['id']] = $newShare;
			}
		}
	}
}
