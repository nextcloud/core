<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author Maxence Lange <maxence@nextcloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Samuel <faust64@gmail.com>
 * @author szaimen <szaimen@e.mail.de>
 * @author Valdnet <47037905+Valdnet@users.noreply.github.com>
 * @author Vincent Petry <vincent@nextcloud.com>
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
namespace OC\Share20;

use OC\Cache\CappedMemoryCache;
use OC\Files\Mount\MoveableMount;
use OC\Share20\Exception\ProviderException;
use OCA\Files_Sharing\ISharedStorage;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Node;
use OCP\HintException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Security\Events\ValidatePasswordPolicyEvent;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use OCP\Share;
use OCP\Share\Exceptions\AlreadySharedException;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IProviderFactory;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * This class is the communication hub for all sharing related operations.
 */
class Manager implements IManager {

	/** @var IProviderFactory */
	private $factory;
	/** @var ILogger */
	private $logger;
	/** @var IConfig */
	private $config;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var IHasher */
	private $hasher;
	/** @var IMountManager */
	private $mountManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IL10N */
	private $l;
	/** @var IFactory */
	private $l10nFactory;
	/** @var IUserManager */
	private $userManager;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var CappedMemoryCache */
	private $sharingDisabledForUsersCache;
	/** @var EventDispatcherInterface */
	private $legacyDispatcher;
	/** @var LegacyHooks */
	private $legacyHooks;
	/** @var IMailer */
	private $mailer;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var \OC_Defaults */
	private $defaults;
	/** @var IEventDispatcher */
	private $dispatcher;
	private $userSession;

	public function __construct(
		ILogger $logger,
		IConfig $config,
		ISecureRandom $secureRandom,
		IHasher $hasher,
		IMountManager $mountManager,
		IGroupManager $groupManager,
		IL10N $l,
		IFactory $l10nFactory,
		IProviderFactory $factory,
		IUserManager $userManager,
		IRootFolder $rootFolder,
		EventDispatcherInterface $legacyDispatcher,
		IMailer $mailer,
		IURLGenerator $urlGenerator,
		\OC_Defaults $defaults,
		IEventDispatcher $dispatcher,
		IUserSession $userSession
	) {
		$this->logger = $logger;
		$this->config = $config;
		$this->secureRandom = $secureRandom;
		$this->hasher = $hasher;
		$this->mountManager = $mountManager;
		$this->groupManager = $groupManager;
		$this->l = $l;
		$this->l10nFactory = $l10nFactory;
		$this->factory = $factory;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->legacyDispatcher = $legacyDispatcher;
		$this->sharingDisabledForUsersCache = new CappedMemoryCache();
		// The constructor of LegacyHooks registers the listeners of share events
		// do not remove if those are not properly migrated
		$this->legacyHooks = new LegacyHooks($this->legacyDispatcher);
		$this->mailer = $mailer;
		$this->urlGenerator = $urlGenerator;
		$this->defaults = $defaults;
		$this->dispatcher = $dispatcher;
		$this->userSession = $userSession;
	}

	/**
	 * Convert from a full share id to a tuple (providerId, shareId)
	 *
	 * @param string $id
	 * @return string[]
	 */
	private function splitFullId($id) {
		return explode(':', $id, 2);
	}

	/**
	 * Verify if a password meets all requirements
	 *
	 * @param string $password
	 * @throws \Exception
	 */
	protected function verifyPassword($password) {
		if ($password === null) {
			// No password is set, check if this is allowed.
			if ($this->shareApiLinkEnforcePassword()) {
				throw new \InvalidArgumentException('Passwords are enforced for link and mail shares');
			}

			return;
		}

		// Let others verify the password
		try {
			$this->legacyDispatcher->dispatch(new ValidatePasswordPolicyEvent($password));
		} catch (HintException $e) {
			throw new \Exception($e->getHint());
		}
	}

	/**
	 * Check for generic requirements before creating a share
	 *
	 * @param IShare $share
	 * @throws \InvalidArgumentException
	 * @throws GenericShareException
	 *
	 * @suppress PhanUndeclaredClassMethod
	 */
	protected function generalCreateChecks(IShare $share) {
		if ($share->getShareType() === IShare::TYPE_USER) {
			// We expect a valid user as sharedWith for user shares
			if (!$this->userManager->userExists($share->getSharedWith())) {
				throw new \InvalidArgumentException('SharedWith is not a valid user');
			}
		} elseif ($share->getShareType() === IShare::TYPE_GROUP) {
			// We expect a valid group as sharedWith for group shares
			if (!$this->groupManager->groupExists($share->getSharedWith())) {
				throw new \InvalidArgumentException('SharedWith is not a valid group');
			}
		} elseif ($share->getShareType() === IShare::TYPE_LINK) {
			// No check for TYPE_EMAIL here as we have a recipient for them
			if ($share->getSharedWith() !== null) {
				throw new \InvalidArgumentException('SharedWith should be empty');
			}
		} elseif ($share->getShareType() === IShare::TYPE_EMAIL) {
			if ($share->getSharedWith() === null) {
				throw new \InvalidArgumentException('SharedWith should not be empty');
			}
		} elseif ($share->getShareType() === IShare::TYPE_REMOTE) {
			if ($share->getSharedWith() === null) {
				throw new \InvalidArgumentException('SharedWith should not be empty');
			}
		} elseif ($share->getShareType() === IShare::TYPE_REMOTE_GROUP) {
			if ($share->getSharedWith() === null) {
				throw new \InvalidArgumentException('SharedWith should not be empty');
			}
		} elseif ($share->getShareType() === IShare::TYPE_CIRCLE) {
			$circle = \OCA\Circles\Api\v1\Circles::detailsCircle($share->getSharedWith());
			if ($circle === null) {
				throw new \InvalidArgumentException('SharedWith is not a valid circle');
			}
		} elseif ($share->getShareType() === IShare::TYPE_ROOM) {
		} elseif ($share->getShareType() === IShare::TYPE_DECK) {
		} else {
			// We cannot handle other types yet
			throw new \InvalidArgumentException('unknown share type');
		}

		// Verify the initiator of the share is set
		if ($share->getSharedBy() === null) {
			throw new \InvalidArgumentException('SharedBy should be set');
		}

		// Cannot share with yourself
		if ($share->getShareType() === IShare::TYPE_USER &&
			$share->getSharedWith() === $share->getSharedBy()) {
			throw new \InvalidArgumentException('Cannot share with yourself');
		}

		// The path should be set
		if ($share->getNode() === null) {
			throw new \InvalidArgumentException('Path should be set');
		}

		// And it should be a file or a folder
		if (!($share->getNode() instanceof \OCP\Files\File) &&
			!($share->getNode() instanceof \OCP\Files\Folder)) {
			throw new \InvalidArgumentException('Path should be either a file or a folder');
		}

		// And you cannot share your rootfolder
		if ($this->userManager->userExists($share->getSharedBy())) {
			$userFolder = $this->rootFolder->getUserFolder($share->getSharedBy());
		} else {
			$userFolder = $this->rootFolder->getUserFolder($share->getShareOwner());
		}
		if ($userFolder->getId() === $share->getNode()->getId()) {
			throw new \InvalidArgumentException('You cannot share your root folder');
		}

		// Check if we actually have share permissions
		if (!$share->getNode()->isShareable()) {
			$message_t = $this->l->t('You are not allowed to share %s', [$share->getNode()->getName()]);
			throw new GenericShareException($message_t, $message_t, 404);
		}

		// Permissions should be set
		if ($share->getPermissions() === null) {
			throw new \InvalidArgumentException('A share requires permissions');
		}

		$isFederatedShare = $share->getNode()->getStorage()->instanceOfStorage('\OCA\Files_Sharing\External\Storage');
		$permissions = 0;

		if (!$isFederatedShare && $share->getNode()->getOwner() && $share->getNode()->getOwner()->getUID() !== $share->getSharedBy()) {
			$userMounts = array_filter($userFolder->getById($share->getNode()->getId()), function ($mount) use ($share) {
				// We need to filter since there might be other mountpoints that contain the file
				// e.g. if the user has access to the same external storage that the file is originating from
				return $mount->getStorage()->instanceOfStorage(ISharedStorage::class) && $mount->getPath() === $share->getNode()->getPath();
			});
			$userMount = array_shift($userMounts);
			if ($userMount === null) {
				throw new GenericShareException('Could not get proper share mount for ' . $share->getNode()->getId() . '. Failing since else the next calls are called with null');
			}
			$mount = $userMount->getMountPoint();
			// When it's a reshare use the parent share permissions as maximum
			$userMountPointId = $mount->getStorageRootId();
			$userMountPoints = $userFolder->getById($userMountPointId);
			$userMountPoint = array_shift($userMountPoints);

			if ($userMountPoint === null) {
				throw new GenericShareException('Could not get proper user mount for ' . $userMountPointId . '. Failing since else the next calls are called with null');
			}

			/* Check if this is an incoming share */
			$incomingShares = $this->getSharedWith($share->getSharedBy(), IShare::TYPE_USER, $userMountPoint, -1, 0);
			$incomingShares = array_merge($incomingShares, $this->getSharedWith($share->getSharedBy(), IShare::TYPE_GROUP, $userMountPoint, -1, 0));
			$incomingShares = array_merge($incomingShares, $this->getSharedWith($share->getSharedBy(), IShare::TYPE_CIRCLE, $userMountPoint, -1, 0));
			$incomingShares = array_merge($incomingShares, $this->getSharedWith($share->getSharedBy(), IShare::TYPE_ROOM, $userMountPoint, -1, 0));

			/** @var IShare[] $incomingShares */
			if (!empty($incomingShares)) {
				foreach ($incomingShares as $incomingShare) {
					$permissions |= $incomingShare->getPermissions();
				}
			}
		} else {
			/*
			 * Quick fix for #23536
			 * Non moveable mount points do not have update and delete permissions
			 * while we 'most likely' do have that on the storage.
			 */
			$permissions = $share->getNode()->getPermissions();
			if (!($share->getNode()->getMountPoint() instanceof MoveableMount)) {
				$permissions |= \OCP\Constants::PERMISSION_DELETE | \OCP\Constants::PERMISSION_UPDATE;
			}
		}

		// Check that we do not share with more permissions than we have
		if ($share->getPermissions() & ~$permissions) {
			$path = $userFolder->getRelativePath($share->getNode()->getPath());
			$message_t = $this->l->t('Cannot increase permissions of %s', [$path]);
			throw new GenericShareException($message_t, $message_t, 404);
		}


		// Check that read permissions are always set
		// Link shares are allowed to have no read permissions to allow upload to hidden folders
		$noReadPermissionRequired = $share->getShareType() === IShare::TYPE_LINK
			|| $share->getShareType() === IShare::TYPE_EMAIL;
		if (!$noReadPermissionRequired &&
			($share->getPermissions() & \OCP\Constants::PERMISSION_READ) === 0) {
			throw new \InvalidArgumentException('Shares need at least read permissions');
		}

		if ($share->getNode() instanceof \OCP\Files\File) {
			if ($share->getPermissions() & \OCP\Constants::PERMISSION_DELETE) {
				$message_t = $this->l->t('Files cannot be shared with delete permissions');
				throw new GenericShareException($message_t);
			}
			if ($share->getPermissions() & \OCP\Constants::PERMISSION_CREATE) {
				$message_t = $this->l->t('Files cannot be shared with create permissions');
				throw new GenericShareException($message_t);
			}
		}
	}

	/**
	 * Validate if the expiration date fits the system settings
	 *
	 * @param IShare $share The share to validate the expiration date of
	 * @return IShare The modified share object
	 * @throws GenericShareException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	protected function validateExpirationDateInternal(IShare $share) {
		$isRemote = $share->getShareType() === IShare::TYPE_REMOTE || $share->getShareType() === IShare::TYPE_REMOTE_GROUP;

		$expirationDate = $share->getExpirationDate();

		if ($expirationDate !== null) {
			//Make sure the expiration date is a date
			$expirationDate->setTime(0, 0, 0);

			$date = new \DateTime();
			$date->setTime(0, 0, 0);
			if ($date >= $expirationDate) {
				$message = $this->l->t('Expiration date is in the past');
				throw new GenericShareException($message, $message, 404);
			}
		}

		// If expiredate is empty set a default one if there is a default
		$fullId = null;
		try {
			$fullId = $share->getFullId();
		} catch (\UnexpectedValueException $e) {
			// This is a new share
		}

		if ($isRemote) {
			$defaultExpireDate = $this->shareApiRemoteDefaultExpireDate();
			$defaultExpireDays = $this->shareApiRemoteDefaultExpireDays();
			$configProp = 'remote_defaultExpDays';
			$isEnforced = $this->shareApiRemoteDefaultExpireDateEnforced();
		} else {
			$defaultExpireDate = $this->shareApiInternalDefaultExpireDate();
			$defaultExpireDays = $this->shareApiInternalDefaultExpireDays();
			$configProp = 'internal_defaultExpDays';
			$isEnforced = $this->shareApiInternalDefaultExpireDateEnforced();
		}
		if ($fullId === null && $expirationDate === null && $defaultExpireDate) {
			$expirationDate = new \DateTime();
			$expirationDate->setTime(0, 0, 0);

			$days = (int)$this->config->getAppValue('core', $configProp, (string)$defaultExpireDays);
			if ($days > $defaultExpireDays) {
				$days = $defaultExpireDays;
			}
			$expirationDate->add(new \DateInterval('P' . $days . 'D'));
		}

		// If we enforce the expiration date check that is does not exceed
		if ($isEnforced) {
			if ($expirationDate === null) {
				throw new \InvalidArgumentException('Expiration date is enforced');
			}

			$date = new \DateTime();
			$date->setTime(0, 0, 0);
			$date->add(new \DateInterval('P' . $defaultExpireDays . 'D'));
			if ($date < $expirationDate) {
				$message = $this->l->n('Cannot set expiration date more than %n day in the future', 'Cannot set expiration date more than %n days in the future', $defaultExpireDays);
				throw new GenericShareException($message, $message, 404);
			}
		}

		$accepted = true;
		$message = '';
		\OCP\Util::emitHook('\OC\Share', 'verifyExpirationDate', [
			'expirationDate' => &$expirationDate,
			'accepted' => &$accepted,
			'message' => &$message,
			'passwordSet' => $share->getPassword() !== null,
		]);

		if (!$accepted) {
			throw new \Exception($message);
		}

		$share->setExpirationDate($expirationDate);

		return $share;
	}

	/**
	 * Validate if the expiration date fits the system settings
	 *
	 * @param IShare $share The share to validate the expiration date of
	 * @return IShare The modified share object
	 * @throws GenericShareException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	protected function validateExpirationDateLink(IShare $share) {
		$expirationDate = $share->getExpirationDate();

		if ($expirationDate !== null) {
			//Make sure the expiration date is a date
			$expirationDate->setTime(0, 0, 0);

			$date = new \DateTime();
			$date->setTime(0, 0, 0);
			if ($date >= $expirationDate) {
				$message = $this->l->t('Expiration date is in the past');
				throw new GenericShareException($message, $message, 404);
			}
		}

		// If expiredate is empty set a default one if there is a default
		$fullId = null;
		try {
			$fullId = $share->getFullId();
		} catch (\UnexpectedValueException $e) {
			// This is a new share
		}

		if ($fullId === null && $expirationDate === null && $this->shareApiLinkDefaultExpireDate()) {
			$expirationDate = new \DateTime();
			$expirationDate->setTime(0, 0, 0);

			$days = (int)$this->config->getAppValue('core', 'link_defaultExpDays', $this->shareApiLinkDefaultExpireDays());
			if ($days > $this->shareApiLinkDefaultExpireDays()) {
				$days = $this->shareApiLinkDefaultExpireDays();
			}
			$expirationDate->add(new \DateInterval('P' . $days . 'D'));
		}

		// If we enforce the expiration date check that is does not exceed
		if ($this->shareApiLinkDefaultExpireDateEnforced()) {
			if ($expirationDate === null) {
				throw new \InvalidArgumentException('Expiration date is enforced');
			}

			$date = new \DateTime();
			$date->setTime(0, 0, 0);
			$date->add(new \DateInterval('P' . $this->shareApiLinkDefaultExpireDays() . 'D'));
			if ($date < $expirationDate) {
				$message = $this->l->n('Cannot set expiration date more than %n day in the future', 'Cannot set expiration date more than %n days in the future', $this->shareApiLinkDefaultExpireDays());
				throw new GenericShareException($message, $message, 404);
			}
		}

		$accepted = true;
		$message = '';
		\OCP\Util::emitHook('\OC\Share', 'verifyExpirationDate', [
			'expirationDate' => &$expirationDate,
			'accepted' => &$accepted,
			'message' => &$message,
			'passwordSet' => $share->getPassword() !== null,
		]);

		if (!$accepted) {
			throw new \Exception($message);
		}

		$share->setExpirationDate($expirationDate);

		return $share;
	}

	/**
	 * Check for pre share requirements for user shares
	 *
	 * @param IShare $share
	 * @throws \Exception
	 */
	protected function userCreateChecks(IShare $share) {
		// Check if we can share with group members only
		if ($this->shareWithGroupMembersOnly()) {
			$sharedBy = $this->userManager->get($share->getSharedBy());
			$sharedWith = $this->userManager->get($share->getSharedWith());
			// Verify we can share with this user
			$groups = array_intersect(
				$this->groupManager->getUserGroupIds($sharedBy),
				$this->groupManager->getUserGroupIds($sharedWith)
			);
			if (empty($groups)) {
				$message_t = $this->l->t('Sharing is only allowed with group members');
				throw new \Exception($message_t);
			}
		}

		/*
		 * TODO: Could be costly, fix
		 *
		 * Also this is not what we want in the future.. then we want to squash identical shares.
		 */
		$provider = $this->factory->getProviderForType(IShare::TYPE_USER);
		$existingShares = $provider->getSharesByPath($share->getNode());
		foreach ($existingShares as $existingShare) {
			// Ignore if it is the same share
			try {
				if ($existingShare->getFullId() === $share->getFullId()) {
					continue;
				}
			} catch (\UnexpectedValueException $e) {
				//Shares are not identical
			}

			// Identical share already exists
			if ($existingShare->getSharedWith() === $share->getSharedWith() && $existingShare->getShareType() === $share->getShareType()) {
				$message = $this->l->t('Sharing %s failed, because this item is already shared with user %s', [$share->getNode()->getName(), $share->getSharedWithDisplayName()]);
				throw new AlreadySharedException($message, $existingShare);
			}

			// The share is already shared with this user via a group share
			if ($existingShare->getShareType() === IShare::TYPE_GROUP) {
				$group = $this->groupManager->get($existingShare->getSharedWith());
				if (!is_null($group)) {
					$user = $this->userManager->get($share->getSharedWith());

					if ($group->inGroup($user) && $existingShare->getShareOwner() !== $share->getShareOwner()) {
						$message = $this->l->t('Sharing %s failed, because this item is already shared with user %s', [$share->getNode()->getName(), $share->getSharedWithDisplayName()]);
						throw new AlreadySharedException($message, $existingShare);
					}
				}
			}
		}
	}

	/**
	 * Check for pre share requirements for group shares
	 *
	 * @param IShare $share
	 * @throws \Exception
	 */
	protected function groupCreateChecks(IShare $share) {
		// Verify group shares are allowed
		if (!$this->allowGroupSharing()) {
			throw new \Exception('Group sharing is now allowed');
		}

		// Verify if the user can share with this group
		if ($this->shareWithGroupMembersOnly()) {
			$sharedBy = $this->userManager->get($share->getSharedBy());
			$sharedWith = $this->groupManager->get($share->getSharedWith());
			if (is_null($sharedWith) || !$sharedWith->inGroup($sharedBy)) {
				throw new \Exception('Sharing is only allowed within your own groups');
			}
		}

		/*
		 * TODO: Could be costly, fix
		 *
		 * Also this is not what we want in the future.. then we want to squash identical shares.
		 */
		$provider = $this->factory->getProviderForType(IShare::TYPE_GROUP);
		$existingShares = $provider->getSharesByPath($share->getNode());
		foreach ($existingShares as $existingShare) {
			try {
				if ($existingShare->getFullId() === $share->getFullId()) {
					continue;
				}
			} catch (\UnexpectedValueException $e) {
				//It is a new share so just continue
			}

			if ($existingShare->getSharedWith() === $share->getSharedWith() && $existingShare->getShareType() === $share->getShareType()) {
				throw new AlreadySharedException('Path is already shared with this group', $existingShare);
			}
		}
	}

	/**
	 * Check for pre share requirements for link shares
	 *
	 * @param IShare $share
	 * @throws \Exception
	 */
	protected function linkCreateChecks(IShare $share) {
		// Are link shares allowed?
		if (!$this->shareApiAllowLinks()) {
			throw new \Exception('Link sharing is not allowed');
		}

		// Check if public upload is allowed
		if (!$this->shareApiLinkAllowPublicUpload() &&
			($share->getPermissions() & (\OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE | \OCP\Constants::PERMISSION_DELETE))) {
			throw new \InvalidArgumentException('Public upload is not allowed');
		}
	}

	/**
	 * To make sure we don't get invisible link shares we set the parent
	 * of a link if it is a reshare. This is a quick word around
	 * until we can properly display multiple link shares in the UI
	 *
	 * See: https://github.com/owncloud/core/issues/22295
	 *
	 * FIXME: Remove once multiple link shares can be properly displayed
	 *
	 * @param IShare $share
	 */
	protected function setLinkParent(IShare $share) {

		// No sense in checking if the method is not there.
		if (method_exists($share, 'setParent')) {
			$storage = $share->getNode()->getStorage();
			if ($storage->instanceOfStorage('\OCA\Files_Sharing\ISharedStorage')) {
				/** @var \OCA\Files_Sharing\SharedStorage $storage */
				$share->setParent($storage->getShareId());
			}
		}
	}

	/**
	 * @param File|Folder $path
	 */
	protected function pathCreateChecks($path) {
		// Make sure that we do not share a path that contains a shared mountpoint
		if ($path instanceof \OCP\Files\Folder) {
			$mounts = $this->mountManager->findIn($path->getPath());
			foreach ($mounts as $mount) {
				if ($mount->getStorage()->instanceOfStorage('\OCA\Files_Sharing\ISharedStorage')) {
					throw new \InvalidArgumentException('Path contains files shared with you');
				}
			}
		}
	}

	/**
	 * Check if the user that is sharing can actually share
	 *
	 * @param IShare $share
	 * @throws \Exception
	 */
	protected function canShare(IShare $share) {
		if (!$this->shareApiEnabled()) {
			throw new \Exception('Sharing is disabled');
		}

		if ($this->sharingDisabledForUser($share->getSharedBy())) {
			throw new \Exception('Sharing is disabled for you');
		}
	}

	/**
	 * Share a path
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 * @throws \Exception
	 *
	 * TODO: handle link share permissions or check them
	 */
	public function createShare(IShare $share) {
		$this->canShare($share);

		$this->generalCreateChecks($share);

		// Verify if there are any issues with the path
		$this->pathCreateChecks($share->getNode());

		/*
		 * On creation of a share the owner is always the owner of the path
		 * Except for mounted federated shares.
		 */
		$storage = $share->getNode()->getStorage();
		if ($storage->instanceOfStorage('OCA\Files_Sharing\External\Storage')) {
			$parent = $share->getNode()->getParent();
			while ($parent->getStorage()->instanceOfStorage('OCA\Files_Sharing\External\Storage')) {
				$parent = $parent->getParent();
			}
			$share->setShareOwner($parent->getOwner()->getUID());
		} else {
			if ($share->getNode()->getOwner()) {
				$share->setShareOwner($share->getNode()->getOwner()->getUID());
			} else {
				$share->setShareOwner($share->getSharedBy());
			}
		}

		try {
			// Verify share type
			if ($share->getShareType() === IShare::TYPE_USER) {
				$this->userCreateChecks($share);

				// Verify the expiration date
				$share = $this->validateExpirationDateInternal($share);
			} elseif ($share->getShareType() === IShare::TYPE_GROUP) {
				$this->groupCreateChecks($share);

				// Verify the expiration date
				$share = $this->validateExpirationDateInternal($share);
			} elseif ($share->getShareType() === IShare::TYPE_REMOTE || $share->getShareType() === IShare::TYPE_REMOTE_GROUP) {
				//Verify the expiration date
				$share = $this->validateExpirationDateInternal($share);
			} elseif ($share->getShareType() === IShare::TYPE_LINK
				|| $share->getShareType() === IShare::TYPE_EMAIL) {
				$this->linkCreateChecks($share);
				$this->setLinkParent($share);

				// For now ignore a set token.
				$share->setToken(
					$this->secureRandom->generate(
						\OC\Share\Constants::TOKEN_LENGTH,
						\OCP\Security\ISecureRandom::CHAR_HUMAN_READABLE
					)
				);

				// Verify the expiration date
				$share = $this->validateExpirationDateLink($share);

				// Verify the password
				$this->verifyPassword($share->getPassword());

				// If a password is set. Hash it!
				if ($share->getShareType() === IShare::TYPE_LINK
					&& $share->getPassword() !== null) {
					$share->setPassword($this->hasher->hash($share->getPassword()));
				}
			}

			// Cannot share with the owner
			if ($share->getShareType() === IShare::TYPE_USER &&
				$share->getSharedWith() === $share->getShareOwner()) {
				throw new \InvalidArgumentException('Cannot share with the share owner');
			}

			// Generate the target
			$target = $this->config->getSystemValue('share_folder', '/') . '/' . $share->getNode()->getName();
			$target = \OC\Files\Filesystem::normalizePath($target);
			$share->setTarget($target);

			// Pre share event
			$event = new GenericEvent($share);
			$this->legacyDispatcher->dispatch('OCP\Share::preShare', $event);
			if ($event->isPropagationStopped() && $event->hasArgument('error')) {
				throw new \Exception($event->getArgument('error'));
			}

			$oldShare = $share;
			$provider = $this->factory->getProviderForType($share->getShareType());
			$share = $provider->create($share);

			// Reuse the node we already have
			$share->setNode($oldShare->getNode());

			// Reset the target if it is null for the new share
			if ($share->getTarget() === '') {
				$share->setTarget($target);
			}
		} catch (AlreadySharedException $e) {
			// if a share for the same target already exists, dont create a new one, but do trigger the hooks and notifications again
			$share = $e->getExistingShare();
		}

		// Post share event
		$event = new GenericEvent($share);
		$this->legacyDispatcher->dispatch('OCP\Share::postShare', $event);

		$this->dispatcher->dispatchTyped(new Share\Events\ShareCreatedEvent($share));

		if ($this->config->getSystemValueBool('sharing.enable_share_mail', true)
			&& $share->getShareType() === IShare::TYPE_USER) {
			$mailSend = $share->getMailSend();
			if ($mailSend === true) {
				$user = $this->userManager->get($share->getSharedWith());
				if ($user !== null) {
					$emailAddress = $user->getEMailAddress();
					if ($emailAddress !== null && $emailAddress !== '') {
						$userLang = $this->l10nFactory->getUserLanguage($user);
						$l = $this->l10nFactory->get('lib', $userLang);
						$this->sendMailNotification(
							$l,
							$share->getNode()->getName(),
							$this->urlGenerator->linkToRouteAbsolute('files_sharing.Accept.accept', ['shareId' => $share->getFullId()]),
							$share->getSharedBy(),
							$emailAddress,
							$share->getExpirationDate()
						);
						$this->logger->debug('Sent share notification to ' . $emailAddress . ' for share with ID ' . $share->getId(), ['app' => 'share']);
					} else {
						$this->logger->debug('Share notification not sent to ' . $share->getSharedWith() . ' because email address is not set.', ['app' => 'share']);
					}
				} else {
					$this->logger->debug('Share notification not sent to ' . $share->getSharedWith() . ' because user could not be found.', ['app' => 'share']);
				}
			} else {
				$this->logger->debug('Share notification not sent because mailsend is false.', ['app' => 'share']);
			}
		}

		return $share;
	}

	/**
	 * Send mail notifications
	 *
	 * This method will catch and log mail transmission errors
	 *
	 * @param IL10N $l Language of the recipient
	 * @param string $filename file/folder name
	 * @param string $link link to the file/folder
	 * @param string $initiator user ID of share sender
	 * @param string $shareWith email address of share receiver
	 * @param \DateTime|null $expiration
	 */
	protected function sendMailNotification(IL10N $l,
											$filename,
											$link,
											$initiator,
											$shareWith,
											\DateTime $expiration = null) {
		$initiatorUser = $this->userManager->get($initiator);
		$initiatorDisplayName = ($initiatorUser instanceof IUser) ? $initiatorUser->getDisplayName() : $initiator;

		$message = $this->mailer->createMessage();

		$emailTemplate = $this->mailer->createEMailTemplate('files_sharing.RecipientNotification', [
			'filename' => $filename,
			'link' => $link,
			'initiator' => $initiatorDisplayName,
			'expiration' => $expiration,
			'shareWith' => $shareWith,
		]);

		$emailTemplate->setSubject($l->t('%1$s shared »%2$s« with you', [$initiatorDisplayName, $filename]));
		$emailTemplate->addHeader();
		$emailTemplate->addHeading($l->t('%1$s shared »%2$s« with you', [$initiatorDisplayName, $filename]), false);
		$text = $l->t('%1$s shared »%2$s« with you.', [$initiatorDisplayName, $filename]);

		$emailTemplate->addBodyText(
			htmlspecialchars($text . ' ' . $l->t('Click the button below to open it.')),
			$text
		);
		$emailTemplate->addBodyButton(
			$l->t('Open »%s«', [$filename]),
			$link
		);

		$message->setTo([$shareWith]);

		// The "From" contains the sharers name
		$instanceName = $this->defaults->getName();
		$senderName = $l->t(
			'%1$s via %2$s',
			[
				$initiatorDisplayName,
				$instanceName,
			]
		);
		$message->setFrom([\OCP\Util::getDefaultEmailAddress('noreply') => $senderName]);

		// The "Reply-To" is set to the sharer if an mail address is configured
		// also the default footer contains a "Do not reply" which needs to be adjusted.
		$initiatorEmail = $initiatorUser->getEMailAddress();
		if ($initiatorEmail !== null) {
			$message->setReplyTo([$initiatorEmail => $initiatorDisplayName]);
			$emailTemplate->addFooter($instanceName . ($this->defaults->getSlogan($l->getLanguageCode()) !== '' ? ' - ' . $this->defaults->getSlogan($l->getLanguageCode()) : ''));
		} else {
			$emailTemplate->addFooter('', $l->getLanguageCode());
		}

		$message->useTemplate($emailTemplate);
		try {
			$failedRecipients = $this->mailer->send($message);
			if (!empty($failedRecipients)) {
				$this->logger->error('Share notification mail could not be sent to: ' . implode(', ', $failedRecipients));
				return;
			}
		} catch (\Exception $e) {
			$this->logger->logException($e, ['message' => 'Share notification mail could not be sent']);
		}
	}

	/**
	 * Update a share
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 * @throws \InvalidArgumentException
	 */
	public function updateShare(IShare $share) {
		$expirationDateUpdated = false;

		$this->canShare($share);

		try {
			$originalShare = $this->getShareById($share->getFullId());
		} catch (\UnexpectedValueException $e) {
			throw new \InvalidArgumentException('Share does not have a full id');
		}

		// We cannot change the share type!
		if ($share->getShareType() !== $originalShare->getShareType()) {
			throw new \InvalidArgumentException('Cannot change share type');
		}

		// We can only change the recipient on user shares
		if ($share->getSharedWith() !== $originalShare->getSharedWith() &&
			$share->getShareType() !== IShare::TYPE_USER) {
			throw new \InvalidArgumentException('Can only update recipient on user shares');
		}

		// Cannot share with the owner
		if ($share->getShareType() === IShare::TYPE_USER &&
			$share->getSharedWith() === $share->getShareOwner()) {
			throw new \InvalidArgumentException('Cannot share with the share owner');
		}

		$this->generalCreateChecks($share);

		if ($share->getShareType() === IShare::TYPE_USER) {
			$this->userCreateChecks($share);

			if ($share->getExpirationDate() != $originalShare->getExpirationDate()) {
				//Verify the expiration date
				$this->validateExpirationDateInternal($share);
				$expirationDateUpdated = true;
			}
		} elseif ($share->getShareType() === IShare::TYPE_GROUP) {
			$this->groupCreateChecks($share);

			if ($share->getExpirationDate() != $originalShare->getExpirationDate()) {
				//Verify the expiration date
				$this->validateExpirationDateInternal($share);
				$expirationDateUpdated = true;
			}
		} elseif ($share->getShareType() === IShare::TYPE_LINK
			|| $share->getShareType() === IShare::TYPE_EMAIL) {
			$this->linkCreateChecks($share);

			// The new password is not set again if it is the same as the old
			// one, unless when switching from sending by Talk to sending by
			// mail.
			$plainTextPassword = $share->getPassword();
			$updatedPassword = $this->updateSharePasswordIfNeeded($share, $originalShare);

			/**
			 * Cannot enable the getSendPasswordByTalk if there is no password set
			 */
			if (empty($plainTextPassword) && $share->getSendPasswordByTalk()) {
				throw new \InvalidArgumentException('Cannot enable sending the password by Talk with an empty password');
			}

			/**
			 * If we're in a mail share, we need to force a password change
			 * as either the user is not aware of the password or is already (received by mail)
			 * Thus the SendPasswordByTalk feature would not make sense
			 */
			if (!$updatedPassword && $share->getShareType() === IShare::TYPE_EMAIL) {
				if (!$originalShare->getSendPasswordByTalk() && $share->getSendPasswordByTalk()) {
					throw new \InvalidArgumentException('Cannot enable sending the password by Talk without setting a new password');
				}
				if ($originalShare->getSendPasswordByTalk() && !$share->getSendPasswordByTalk()) {
					throw new \InvalidArgumentException('Cannot disable sending the password by Talk without setting a new password');
				}
			}

			if ($share->getExpirationDate() != $originalShare->getExpirationDate()) {
				// Verify the expiration date
				$this->validateExpirationDateLink($share);
				$expirationDateUpdated = true;
			}
		} elseif ($share->getShareType() === IShare::TYPE_REMOTE || $share->getShareType() === IShare::TYPE_REMOTE_GROUP) {
			if ($share->getExpirationDate() != $originalShare->getExpirationDate()) {
				//Verify the expiration date
				$this->validateExpirationDateInternal($share);
				$expirationDateUpdated = true;
			}
		}

		$this->pathCreateChecks($share->getNode());

		// Now update the share!
		$provider = $this->factory->getProviderForType($share->getShareType());
		if ($share->getShareType() === IShare::TYPE_EMAIL) {
			$share = $provider->update($share, $plainTextPassword);
		} else {
			$share = $provider->update($share);
		}

		if ($expirationDateUpdated === true) {
			\OC_Hook::emit(Share::class, 'post_set_expiration_date', [
				'itemType' => $share->getNode() instanceof \OCP\Files\File ? 'file' : 'folder',
				'itemSource' => $share->getNode()->getId(),
				'date' => $share->getExpirationDate(),
				'uidOwner' => $share->getSharedBy(),
			]);
		}

		if ($share->getPassword() !== $originalShare->getPassword()) {
			\OC_Hook::emit(Share::class, 'post_update_password', [
				'itemType' => $share->getNode() instanceof \OCP\Files\File ? 'file' : 'folder',
				'itemSource' => $share->getNode()->getId(),
				'uidOwner' => $share->getSharedBy(),
				'token' => $share->getToken(),
				'disabled' => is_null($share->getPassword()),
			]);
		}

		if ($share->getPermissions() !== $originalShare->getPermissions()) {
			if ($this->userManager->userExists($share->getShareOwner())) {
				$userFolder = $this->rootFolder->getUserFolder($share->getShareOwner());
			} else {
				$userFolder = $this->rootFolder->getUserFolder($share->getSharedBy());
			}
			\OC_Hook::emit(Share::class, 'post_update_permissions', [
				'itemType' => $share->getNode() instanceof \OCP\Files\File ? 'file' : 'folder',
				'itemSource' => $share->getNode()->getId(),
				'shareType' => $share->getShareType(),
				'shareWith' => $share->getSharedWith(),
				'uidOwner' => $share->getSharedBy(),
				'permissions' => $share->getPermissions(),
				'path' => $userFolder->getRelativePath($share->getNode()->getPath()),
			]);
		}

		return $share;
	}

	/**
	 * Accept a share.
	 *
	 * @param IShare $share
	 * @param string $recipientId
	 * @return IShare The share object
	 * @throws \InvalidArgumentException
	 * @since 9.0.0
	 */
	public function acceptShare(IShare $share, string $recipientId): IShare {
		[$providerId,] = $this->splitFullId($share->getFullId());
		$provider = $this->factory->getProvider($providerId);

		if (!method_exists($provider, 'acceptShare')) {
			// TODO FIX ME
			throw new \InvalidArgumentException('Share provider does not support accepting');
		}
		$provider->acceptShare($share, $recipientId);
		$event = new GenericEvent($share);
		$this->legacyDispatcher->dispatch('OCP\Share::postAcceptShare', $event);

		return $share;
	}

	/**
	 * Updates the password of the given share if it is not the same as the
	 * password of the original share.
	 *
	 * @param IShare $share the share to update its password.
	 * @param IShare $originalShare the original share to compare its
	 *        password with.
	 * @return boolean whether the password was updated or not.
	 */
	private function updateSharePasswordIfNeeded(IShare $share, IShare $originalShare) {
		$passwordsAreDifferent = ($share->getPassword() !== $originalShare->getPassword()) &&
			(($share->getPassword() !== null && $originalShare->getPassword() === null) ||
				($share->getPassword() === null && $originalShare->getPassword() !== null) ||
				($share->getPassword() !== null && $originalShare->getPassword() !== null &&
					!$this->hasher->verify($share->getPassword(), $originalShare->getPassword())));

		// Password updated.
		if ($passwordsAreDifferent) {
			//Verify the password
			$this->verifyPassword($share->getPassword());

			// If a password is set. Hash it!
			if (!empty($share->getPassword())) {
				$share->setPassword($this->hasher->hash($share->getPassword()));

				return true;
			} else {
				// Empty string and null are seen as NOT password protected
				$share->setPassword(null);
				return true;
			}
		} else {
			// Reset the password to the original one, as it is either the same
			// as the "new" password or a hashed version of it.
			$share->setPassword($originalShare->getPassword());
		}

		return false;
	}

	/**
	 * Delete all the children of this share
	 * FIXME: remove once https://github.com/owncloud/core/pull/21660 is in
	 *
	 * @param IShare $share
	 * @return IShare[] List of deleted shares
	 */
	protected function deleteChildren(IShare $share) {
		$deletedShares = [];

		$provider = $this->factory->getProviderForType($share->getShareType());

		foreach ($provider->getChildren($share) as $child) {
			$deletedChildren = $this->deleteChildren($child);
			$deletedShares = array_merge($deletedShares, $deletedChildren);

			$provider->delete($child);
			$this->dispatcher->dispatchTyped(new Share\Events\ShareDeletedEvent($child));
			$deletedShares[] = $child;
		}

		return $deletedShares;
	}

	/**
	 * Delete a share
	 *
	 * @param IShare $share
	 * @throws ShareNotFound
	 * @throws \InvalidArgumentException
	 */
	public function deleteShare(IShare $share) {
		try {
			$share->getFullId();
		} catch (\UnexpectedValueException $e) {
			throw new \InvalidArgumentException('Share does not have a full id');
		}

		$event = new GenericEvent($share);
		$this->legacyDispatcher->dispatch('OCP\Share::preUnshare', $event);

		// Get all children and delete them as well
		$deletedShares = $this->deleteChildren($share);

		// Do the actual delete
		$provider = $this->factory->getProviderForType($share->getShareType());
		$provider->delete($share);

		$this->dispatcher->dispatchTyped(new Share\Events\ShareDeletedEvent($share));

		// All the deleted shares caused by this delete
		$deletedShares[] = $share;

		// Emit post hook
		$event->setArgument('deletedShares', $deletedShares);
		$this->legacyDispatcher->dispatch('OCP\Share::postUnshare', $event);
	}


	/**
	 * Unshare a file as the recipient.
	 * This can be different from a regular delete for example when one of
	 * the users in a groups deletes that share. But the provider should
	 * handle this.
	 *
	 * @param IShare $share
	 * @param string $recipientId
	 */
	public function deleteFromSelf(IShare $share, $recipientId) {
		[$providerId,] = $this->splitFullId($share->getFullId());
		$provider = $this->factory->getProvider($providerId);

		$provider->deleteFromSelf($share, $recipientId);
		$event = new GenericEvent($share);
		$this->legacyDispatcher->dispatch('OCP\Share::postUnshareFromSelf', $event);
	}

	public function restoreShare(IShare $share, string $recipientId): IShare {
		[$providerId,] = $this->splitFullId($share->getFullId());
		$provider = $this->factory->getProvider($providerId);

		return $provider->restore($share, $recipientId);
	}

	/**
	 * @inheritdoc
	 */
	public function moveShare(IShare $share, $recipientId) {
		if ($share->getShareType() === IShare::TYPE_LINK
			|| $share->getShareType() === IShare::TYPE_EMAIL) {
			throw new \InvalidArgumentException('Cannot change target of link share');
		}

		if ($share->getShareType() === IShare::TYPE_USER && $share->getSharedWith() !== $recipientId) {
			throw new \InvalidArgumentException('Invalid recipient');
		}

		if ($share->getShareType() === IShare::TYPE_GROUP) {
			$sharedWith = $this->groupManager->get($share->getSharedWith());
			if (is_null($sharedWith)) {
				throw new \InvalidArgumentException('Group "' . $share->getSharedWith() . '" does not exist');
			}
			$recipient = $this->userManager->get($recipientId);
			if (!$sharedWith->inGroup($recipient)) {
				throw new \InvalidArgumentException('Invalid recipient');
			}
		}

		[$providerId,] = $this->splitFullId($share->getFullId());
		$provider = $this->factory->getProvider($providerId);

		return $provider->move($share, $recipientId);
	}

	public function getSharesInFolder($userId, Folder $node, $reshares = false) {
		$providers = $this->factory->getAllProviders();

		return array_reduce($providers, function ($shares, IShareProvider $provider) use ($userId, $node, $reshares) {
			$newShares = $provider->getSharesInFolder($userId, $node, $reshares);
			foreach ($newShares as $fid => $data) {
				if (!isset($shares[$fid])) {
					$shares[$fid] = [];
				}

				$shares[$fid] = array_merge($shares[$fid], $data);
			}
			return $shares;
		}, []);
	}

	/**
	 * @inheritdoc
	 */
	public function getSharesBy($userId, $shareType, $path = null, $reshares = false, $limit = 50, $offset = 0) {
		if ($path !== null &&
			!($path instanceof \OCP\Files\File) &&
			!($path instanceof \OCP\Files\Folder)) {
			throw new \InvalidArgumentException('invalid path');
		}

		try {
			$provider = $this->factory->getProviderForType($shareType);
		} catch (ProviderException $e) {
			return [];
		}

		$shares = $provider->getSharesBy($userId, $shareType, $path, $reshares, $limit, $offset);

		/*
		 * Work around so we don't return expired shares but still follow
		 * proper pagination.
		 */

		$shares2 = [];

		while (true) {
			$added = 0;
			foreach ($shares as $share) {
				try {
					$this->checkExpireDate($share);
				} catch (ShareNotFound $e) {
					//Ignore since this basically means the share is deleted
					continue;
				}

				$added++;
				$shares2[] = $share;

				if (count($shares2) === $limit) {
					break;
				}
			}

			// If we did not fetch more shares than the limit then there are no more shares
			if (count($shares) < $limit) {
				break;
			}

			if (count($shares2) === $limit) {
				break;
			}

			// If there was no limit on the select we are done
			if ($limit === -1) {
				break;
			}

			$offset += $added;

			// Fetch again $limit shares
			$shares = $provider->getSharesBy($userId, $shareType, $path, $reshares, $limit, $offset);

			// No more shares means we are done
			if (empty($shares)) {
				break;
			}
		}

		$shares = $shares2;

		return $shares;
	}

	/**
	 * @inheritdoc
	 */
	public function getSharedWith($userId, $shareType, $node = null, $limit = 50, $offset = 0) {
		try {
			$provider = $this->factory->getProviderForType($shareType);
		} catch (ProviderException $e) {
			return [];
		}

		$shares = $provider->getSharedWith($userId, $shareType, $node, $limit, $offset);

		// remove all shares which are already expired
		foreach ($shares as $key => $share) {
			try {
				$this->checkExpireDate($share);
			} catch (ShareNotFound $e) {
				unset($shares[$key]);
			}
		}

		return $shares;
	}

	/**
	 * @inheritdoc
	 */
	public function getDeletedSharedWith($userId, $shareType, $node = null, $limit = 50, $offset = 0) {
		$shares = $this->getSharedWith($userId, $shareType, $node, $limit, $offset);

		// Only get deleted shares
		$shares = array_filter($shares, function (IShare $share) {
			return $share->getPermissions() === 0;
		});

		// Only get shares where the owner still exists
		$shares = array_filter($shares, function (IShare $share) {
			return $this->userManager->userExists($share->getShareOwner());
		});

		return $shares;
	}

	/**
	 * @inheritdoc
	 */
	public function getShareById($id, $recipient = null) {
		if ($id === null) {
			throw new ShareNotFound();
		}

		[$providerId, $id] = $this->splitFullId($id);

		try {
			$provider = $this->factory->getProvider($providerId);
		} catch (ProviderException $e) {
			throw new ShareNotFound();
		}

		$share = $provider->getShareById($id, $recipient);

		$this->checkExpireDate($share);

		return $share;
	}

	/**
	 * Get all the shares for a given path
	 *
	 * @param \OCP\Files\Node $path
	 * @param int $page
	 * @param int $perPage
	 *
	 * @return Share[]
	 */
	public function getSharesByPath(\OCP\Files\Node $path, $page = 0, $perPage = 50) {
		return [];
	}

	/**
	 * Get the share by token possible with password
	 *
	 * @param string $token
	 * @return IShare
	 *
	 * @throws ShareNotFound
	 */
	public function getShareByToken($token) {
		// tokens cannot be valid local user names
		if ($this->userManager->userExists($token)) {
			throw new ShareNotFound();
		}
		$share = null;
		try {
			if ($this->shareApiAllowLinks()) {
				$provider = $this->factory->getProviderForType(IShare::TYPE_LINK);
				$share = $provider->getShareByToken($token);
			}
		} catch (ProviderException $e) {
		} catch (ShareNotFound $e) {
		}


		// If it is not a link share try to fetch a federated share by token
		if ($share === null) {
			try {
				$provider = $this->factory->getProviderForType(IShare::TYPE_REMOTE);
				$share = $provider->getShareByToken($token);
			} catch (ProviderException $e) {
			} catch (ShareNotFound $e) {
			}
		}

		// If it is not a link share try to fetch a mail share by token
		if ($share === null && $this->shareProviderExists(IShare::TYPE_EMAIL)) {
			try {
				$provider = $this->factory->getProviderForType(IShare::TYPE_EMAIL);
				$share = $provider->getShareByToken($token);
			} catch (ProviderException $e) {
			} catch (ShareNotFound $e) {
			}
		}

		if ($share === null && $this->shareProviderExists(IShare::TYPE_CIRCLE)) {
			try {
				$provider = $this->factory->getProviderForType(IShare::TYPE_CIRCLE);
				$share = $provider->getShareByToken($token);
			} catch (ProviderException $e) {
			} catch (ShareNotFound $e) {
			}
		}

		if ($share === null && $this->shareProviderExists(IShare::TYPE_ROOM)) {
			try {
				$provider = $this->factory->getProviderForType(IShare::TYPE_ROOM);
				$share = $provider->getShareByToken($token);
			} catch (ProviderException $e) {
			} catch (ShareNotFound $e) {
			}
		}

		if ($share === null) {
			throw new ShareNotFound($this->l->t('The requested share does not exist anymore'));
		}

		$this->checkExpireDate($share);

		/*
		 * Reduce the permissions for link or email shares if public upload is not enabled
		 */
		if (($share->getShareType() === IShare::TYPE_LINK || $share->getShareType() === IShare::TYPE_EMAIL)
			&& !$this->shareApiLinkAllowPublicUpload()) {
			$share->setPermissions($share->getPermissions() & ~(\OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE));
		}

		return $share;
	}

	protected function checkExpireDate($share) {
		if ($share->isExpired()) {
			$this->deleteShare($share);
			throw new ShareNotFound($this->l->t('The requested share does not exist anymore'));
		}
	}

	/**
	 * Verify the password of a public share
	 *
	 * @param IShare $share
	 * @param string $password
	 * @return bool
	 */
	public function checkPassword(IShare $share, $password) {
		$passwordProtected = $share->getShareType() !== IShare::TYPE_LINK
			|| $share->getShareType() !== IShare::TYPE_EMAIL
			|| $share->getShareType() !== IShare::TYPE_CIRCLE;
		if (!$passwordProtected) {
			//TODO maybe exception?
			return false;
		}

		if ($password === null || $share->getPassword() === null) {
			return false;
		}

		$newHash = '';
		if (!$this->hasher->verify($password, $share->getPassword(), $newHash)) {
			return false;
		}

		if (!empty($newHash)) {
			$share->setPassword($newHash);
			$provider = $this->factory->getProviderForType($share->getShareType());
			$provider->update($share);
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function userDeleted($uid) {
		$types = [IShare::TYPE_USER, IShare::TYPE_GROUP, IShare::TYPE_LINK, IShare::TYPE_REMOTE, IShare::TYPE_EMAIL];

		foreach ($types as $type) {
			try {
				$provider = $this->factory->getProviderForType($type);
			} catch (ProviderException $e) {
				continue;
			}
			$provider->userDeleted($uid, $type);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function groupDeleted($gid) {
		$provider = $this->factory->getProviderForType(IShare::TYPE_GROUP);
		$provider->groupDeleted($gid);

		$excludedGroups = $this->config->getAppValue('core', 'shareapi_exclude_groups_list', '');
		if ($excludedGroups === '') {
			return;
		}

		$excludedGroups = json_decode($excludedGroups, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			return;
		}

		$excludedGroups = array_diff($excludedGroups, [$gid]);
		$this->config->setAppValue('core', 'shareapi_exclude_groups_list', json_encode($excludedGroups));
	}

	/**
	 * @inheritdoc
	 */
	public function userDeletedFromGroup($uid, $gid) {
		$provider = $this->factory->getProviderForType(IShare::TYPE_GROUP);
		$provider->userDeletedFromGroup($uid, $gid);
	}

	/**
	 * Get access list to a path. This means
	 * all the users that can access a given path.
	 *
	 * Consider:
	 * -root
	 * |-folder1 (23)
	 *  |-folder2 (32)
	 *   |-fileA (42)
	 *
	 * fileA is shared with user1 and user1@server1
	 * folder2 is shared with group2 (user4 is a member of group2)
	 * folder1 is shared with user2 (renamed to "folder (1)") and user2@server2
	 *
	 * Then the access list to '/folder1/folder2/fileA' with $currentAccess is:
	 * [
	 *  users  => [
	 *      'user1' => ['node_id' => 42, 'node_path' => '/fileA'],
	 *      'user4' => ['node_id' => 32, 'node_path' => '/folder2'],
	 *      'user2' => ['node_id' => 23, 'node_path' => '/folder (1)'],
	 *  ],
	 *  remote => [
	 *      'user1@server1' => ['node_id' => 42, 'token' => 'SeCr3t'],
	 *      'user2@server2' => ['node_id' => 23, 'token' => 'FooBaR'],
	 *  ],
	 *  public => bool
	 *  mail => bool
	 * ]
	 *
	 * The access list to '/folder1/folder2/fileA' **without** $currentAccess is:
	 * [
	 *  users  => ['user1', 'user2', 'user4'],
	 *  remote => bool,
	 *  public => bool
	 *  mail => bool
	 * ]
	 *
	 * This is required for encryption/activity
	 *
	 * @param \OCP\Files\Node $path
	 * @param bool $recursive Should we check all parent folders as well
	 * @param bool $currentAccess Ensure the recipient has access to the file (e.g. did not unshare it)
	 * @return array
	 */
	public function getAccessList(\OCP\Files\Node $path, $recursive = true, $currentAccess = false) {
		$owner = $path->getOwner();

		if ($owner === null) {
			return [];
		}

		$owner = $owner->getUID();

		if ($currentAccess) {
			$al = ['users' => [], 'remote' => [], 'public' => false];
		} else {
			$al = ['users' => [], 'remote' => false, 'public' => false];
		}
		if (!$this->userManager->userExists($owner)) {
			return $al;
		}

		//Get node for the owner and correct the owner in case of external storage
		$userFolder = $this->rootFolder->getUserFolder($owner);
		if ($path->getId() !== $userFolder->getId() && !$userFolder->isSubNode($path)) {
			$nodes = $userFolder->getById($path->getId());
			$path = array_shift($nodes);
			if ($path->getOwner() === null) {
				return [];
			}
			$owner = $path->getOwner()->getUID();
		}

		$providers = $this->factory->getAllProviders();

		/** @var Node[] $nodes */
		$nodes = [];


		if ($currentAccess) {
			$ownerPath = $path->getPath();
			$ownerPath = explode('/', $ownerPath, 4);
			if (count($ownerPath) < 4) {
				$ownerPath = '';
			} else {
				$ownerPath = $ownerPath[3];
			}
			$al['users'][$owner] = [
				'node_id' => $path->getId(),
				'node_path' => '/' . $ownerPath,
			];
		} else {
			$al['users'][] = $owner;
		}

		// Collect all the shares
		while ($path->getPath() !== $userFolder->getPath()) {
			$nodes[] = $path;
			if (!$recursive) {
				break;
			}
			$path = $path->getParent();
		}

		foreach ($providers as $provider) {
			$tmp = $provider->getAccessList($nodes, $currentAccess);

			foreach ($tmp as $k => $v) {
				if (isset($al[$k])) {
					if (is_array($al[$k])) {
						if ($currentAccess) {
							$al[$k] += $v;
						} else {
							$al[$k] = array_merge($al[$k], $v);
							$al[$k] = array_unique($al[$k]);
							$al[$k] = array_values($al[$k]);
						}
					} else {
						$al[$k] = $al[$k] || $v;
					}
				} else {
					$al[$k] = $v;
				}
			}
		}

		return $al;
	}

	/**
	 * Create a new share
	 *
	 * @return IShare
	 */
	public function newShare() {
		return new \OC\Share20\Share($this->rootFolder, $this->userManager);
	}

	/**
	 * Is the share API enabled
	 *
	 * @return bool
	 */
	public function shareApiEnabled() {
		return $this->config->getAppValue('core', 'shareapi_enabled', 'yes') === 'yes';
	}

	/**
	 * Is public link sharing enabled
	 *
	 * @return bool
	 */
	public function shareApiAllowLinks() {
		if ($this->config->getAppValue('core', 'shareapi_allow_links', 'yes') !== 'yes') {
			return false;
		}

		$user = $this->userSession->getUser();
		if ($user) {
			$excludedGroups = json_decode($this->config->getAppValue('core', 'shareapi_allow_links_exclude_groups', '[]'));
			if ($excludedGroups) {
				$userGroups = $this->groupManager->getUserGroupIds($user);
				return !(bool)array_intersect($excludedGroups, $userGroups);
			}
		}

		return true;
	}

	/**
	 * Is password on public link requires
	 *
	 * @return bool
	 */
	public function shareApiLinkEnforcePassword() {
		return $this->config->getAppValue('core', 'shareapi_enforce_links_password', 'no') === 'yes';
	}

	/**
	 * Is default link expire date enabled
	 *
	 * @return bool
	 */
	public function shareApiLinkDefaultExpireDate() {
		return $this->config->getAppValue('core', 'shareapi_default_expire_date', 'no') === 'yes';
	}

	/**
	 * Is default link expire date enforced
	 *`
	 *
	 * @return bool
	 */
	public function shareApiLinkDefaultExpireDateEnforced() {
		return $this->shareApiLinkDefaultExpireDate() &&
			$this->config->getAppValue('core', 'shareapi_enforce_expire_date', 'no') === 'yes';
	}


	/**
	 * Number of default link expire days
	 *
	 * @return int
	 */
	public function shareApiLinkDefaultExpireDays() {
		return (int)$this->config->getAppValue('core', 'shareapi_expire_after_n_days', '7');
	}

	/**
	 * Is default internal expire date enabled
	 *
	 * @return bool
	 */
	public function shareApiInternalDefaultExpireDate(): bool {
		return $this->config->getAppValue('core', 'shareapi_default_internal_expire_date', 'no') === 'yes';
	}

	/**
	 * Is default remote expire date enabled
	 *
	 * @return bool
	 */
	public function shareApiRemoteDefaultExpireDate(): bool {
		return $this->config->getAppValue('core', 'shareapi_default_remote_expire_date', 'no') === 'yes';
	}

	/**
	 * Is default expire date enforced
	 *
	 * @return bool
	 */
	public function shareApiInternalDefaultExpireDateEnforced(): bool {
		return $this->shareApiInternalDefaultExpireDate() &&
			$this->config->getAppValue('core', 'shareapi_enforce_internal_expire_date', 'no') === 'yes';
	}

	/**
	 * Is default expire date enforced for remote shares
	 *
	 * @return bool
	 */
	public function shareApiRemoteDefaultExpireDateEnforced(): bool {
		return $this->shareApiRemoteDefaultExpireDate() &&
			$this->config->getAppValue('core', 'shareapi_enforce_remote_expire_date', 'no') === 'yes';
	}

	/**
	 * Number of default expire days
	 *
	 * @return int
	 */
	public function shareApiInternalDefaultExpireDays(): int {
		return (int)$this->config->getAppValue('core', 'shareapi_internal_expire_after_n_days', '7');
	}

	/**
	 * Number of default expire days for remote shares
	 *
	 * @return int
	 */
	public function shareApiRemoteDefaultExpireDays(): int {
		return (int)$this->config->getAppValue('core', 'shareapi_remote_expire_after_n_days', '7');
	}

	/**
	 * Allow public upload on link shares
	 *
	 * @return bool
	 */
	public function shareApiLinkAllowPublicUpload() {
		return $this->config->getAppValue('core', 'shareapi_allow_public_upload', 'yes') === 'yes';
	}

	/**
	 * check if user can only share with group members
	 *
	 * @return bool
	 */
	public function shareWithGroupMembersOnly() {
		return $this->config->getAppValue('core', 'shareapi_only_share_with_group_members', 'no') === 'yes';
	}

	/**
	 * Check if users can share with groups
	 *
	 * @return bool
	 */
	public function allowGroupSharing() {
		return $this->config->getAppValue('core', 'shareapi_allow_group_sharing', 'yes') === 'yes';
	}

	public function allowEnumeration(): bool {
		return $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
	}

	public function limitEnumerationToGroups(): bool {
		return $this->allowEnumeration() &&
			$this->config->getAppValue('core', 'shareapi_restrict_user_enumeration_to_group', 'no') === 'yes';
	}

	public function limitEnumerationToPhone(): bool {
		return $this->allowEnumeration() &&
			$this->config->getAppValue('core', 'shareapi_restrict_user_enumeration_to_phone', 'no') === 'yes';
	}

	public function allowEnumerationFullMatch(): bool {
		return $this->config->getAppValue('core', 'shareapi_restrict_user_enumeration_full_match', 'yes') === 'yes';
	}

	/**
	 * Copied from \OC_Util::isSharingDisabledForUser
	 *
	 * TODO: Deprecate fuction from OC_Util
	 *
	 * @param string $userId
	 * @return bool
	 */
	public function sharingDisabledForUser($userId) {
		if ($userId === null) {
			return false;
		}

		if (isset($this->sharingDisabledForUsersCache[$userId])) {
			return $this->sharingDisabledForUsersCache[$userId];
		}

		if ($this->config->getAppValue('core', 'shareapi_exclude_groups', 'no') === 'yes') {
			$groupsList = $this->config->getAppValue('core', 'shareapi_exclude_groups_list', '');
			$excludedGroups = json_decode($groupsList);
			if (is_null($excludedGroups)) {
				$excludedGroups = explode(',', $groupsList);
				$newValue = json_encode($excludedGroups);
				$this->config->setAppValue('core', 'shareapi_exclude_groups_list', $newValue);
			}
			$user = $this->userManager->get($userId);
			$usersGroups = $this->groupManager->getUserGroupIds($user);
			if (!empty($usersGroups)) {
				$remainingGroups = array_diff($usersGroups, $excludedGroups);
				// if the user is only in groups which are disabled for sharing then
				// sharing is also disabled for the user
				if (empty($remainingGroups)) {
					$this->sharingDisabledForUsersCache[$userId] = true;
					return true;
				}
			}
		}

		$this->sharingDisabledForUsersCache[$userId] = false;
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public function outgoingServer2ServerSharesAllowed() {
		return $this->config->getAppValue('files_sharing', 'outgoing_server2server_share_enabled', 'yes') === 'yes';
	}

	/**
	 * @inheritdoc
	 */
	public function outgoingServer2ServerGroupSharesAllowed() {
		return $this->config->getAppValue('files_sharing', 'outgoing_server2server_group_share_enabled', 'no') === 'yes';
	}

	/**
	 * @inheritdoc
	 */
	public function shareProviderExists($shareType) {
		try {
			$this->factory->getProviderForType($shareType);
		} catch (ProviderException $e) {
			return false;
		}

		return true;
	}

	public function registerShareProvider(string $shareProviderClass): void {
		$this->factory->registerProvider($shareProviderClass);
	}

	public function getAllShares(): iterable {
		$providers = $this->factory->getAllProviders();

		foreach ($providers as $provider) {
			yield from $provider->getAllShares();
		}
	}
}
