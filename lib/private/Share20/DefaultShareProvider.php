<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Jan-Philipp Litza <jplitza@users.noreply.github.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author phisch <git@philippschaffrath.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

use OC\Files\Cache\Cache;
use OC\Share20\Exception\BackendError;
use OC\Share20\Exception\InvalidShare;
use OC\Share20\Exception\ProviderException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Defaults;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;

/**
 * Class DefaultShareProvider
 *
 * @package OC\Share20
 */
class DefaultShareProvider implements IShareProvider {

	// Special share type for user modified group shares
	public const SHARE_TYPE_USERGROUP = 2;

	/** @var IDBConnection */
	private $dbConn;

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IMailer */
	private $mailer;

	/** @var Defaults */
	private $defaults;

	/** @var IFactory */
	private $l10nFactory;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IConfig */
	private $config;

	public function __construct(
			IDBConnection $connection,
			IUserManager $userManager,
			IGroupManager $groupManager,
			IRootFolder $rootFolder,
			IMailer $mailer,
			Defaults $defaults,
			IFactory $l10nFactory,
			IURLGenerator $urlGenerator,
			IConfig $config) {
		$this->dbConn = $connection;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->rootFolder = $rootFolder;
		$this->mailer = $mailer;
		$this->defaults = $defaults;
		$this->l10nFactory = $l10nFactory;
		$this->urlGenerator = $urlGenerator;
		$this->config = $config;
	}

	/**
	 * Return the identifier of this provider.
	 *
	 * @return string Containing only [a-zA-Z0-9]
	 */
	public function identifier() {
		return 'ocinternal';
	}

	/**
	 * Share a path
	 *
	 * @param \OCP\Share\IShare $share
	 * @return \OCP\Share\IShare The share object
	 * @throws ShareNotFound
	 * @throws \Exception
	 */
	public function create(\OCP\Share\IShare $share) {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share');
		$qb->setValue('share_type', $qb->createNamedParameter($share->getShareType()));

		if ($share->getShareType() === IShare::TYPE_USER) {
			//Set the UID of the user we share with
			$qb->setValue('share_with', $qb->createNamedParameter($share->getSharedWith()));
			$qb->setValue('accepted', $qb->createNamedParameter(IShare::STATUS_PENDING));

			//If an expiration date is set store it
			if ($share->getExpirationDate() !== null) {
				$qb->setValue('expiration', $qb->createNamedParameter($share->getExpirationDate(), 'datetime'));
			}
		} elseif ($share->getShareType() === IShare::TYPE_GROUP) {
			//Set the GID of the group we share with
			$qb->setValue('share_with', $qb->createNamedParameter($share->getSharedWith()));

			//If an expiration date is set store it
			if ($share->getExpirationDate() !== null) {
				$qb->setValue('expiration', $qb->createNamedParameter($share->getExpirationDate(), 'datetime'));
			}
		} elseif ($share->getShareType() === IShare::TYPE_LINK) {
			//set label for public link
			$qb->setValue('label', $qb->createNamedParameter($share->getLabel()));
			//Set the token of the share
			$qb->setValue('token', $qb->createNamedParameter($share->getToken()));

			//If a password is set store it
			if ($share->getPassword() !== null) {
				$qb->setValue('password', $qb->createNamedParameter($share->getPassword()));
			}

			$qb->setValue('password_by_talk', $qb->createNamedParameter($share->getSendPasswordByTalk(), IQueryBuilder::PARAM_BOOL));

			//If an expiration date is set store it
			if ($share->getExpirationDate() !== null) {
				$qb->setValue('expiration', $qb->createNamedParameter($share->getExpirationDate(), 'datetime'));
			}

			if (method_exists($share, 'getParent')) {
				$qb->setValue('parent', $qb->createNamedParameter($share->getParent()));
			}
		} else {
			throw new \Exception('invalid share type!');
		}

		// Set what is shares
		$qb->setValue('item_type', $qb->createParameter('itemType'));
		if ($share->getNode() instanceof \OCP\Files\File) {
			$qb->setParameter('itemType', 'file');
		} else {
			$qb->setParameter('itemType', 'folder');
		}

		// Set the file id
		$qb->setValue('item_source', $qb->createNamedParameter($share->getNode()->getId()));
		$qb->setValue('file_source', $qb->createNamedParameter($share->getNode()->getId()));

		// set the permissions
		$qb->setValue('permissions', $qb->createNamedParameter($share->getPermissions()));

		// Set who created this share
		$qb->setValue('uid_initiator', $qb->createNamedParameter($share->getSharedBy()));

		// Set who is the owner of this file/folder (and this the owner of the share)
		$qb->setValue('uid_owner', $qb->createNamedParameter($share->getShareOwner()));

		// Set the file target
		$qb->setValue('file_target', $qb->createNamedParameter($share->getTarget()));

		// Set the time this share was created
		$qb->setValue('stime', $qb->createNamedParameter(time()));

		// insert the data and fetch the id of the share
		$this->dbConn->beginTransaction();
		$qb->execute();
		$id = $this->dbConn->lastInsertId('*PREFIX*share');

		// Now fetch the inserted share and create a complete share object
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$this->dbConn->commit();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound();
		}

		$mailSendValue = $share->getMailSend();
		$data['mail_send'] = ($mailSendValue === null) ? true : $mailSendValue;

		$share = $this->createShare($data);
		return $share;
	}

	/**
	 * Update a share
	 *
	 * @param \OCP\Share\IShare $share
	 * @return \OCP\Share\IShare The share object
	 * @throws ShareNotFound
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function update(\OCP\Share\IShare $share) {
		$originalShare = $this->getShareById($share->getId());

		if ($share->getShareType() === IShare::TYPE_USER) {
			/*
			 * We allow updating the recipient on user shares.
			 */
			$qb = $this->dbConn->getQueryBuilder();
			$qb->update('share')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))
				->set('share_with', $qb->createNamedParameter($share->getSharedWith()))
				->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
				->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
				->set('permissions', $qb->createNamedParameter($share->getPermissions()))
				->set('item_source', $qb->createNamedParameter($share->getNode()->getId()))
				->set('file_source', $qb->createNamedParameter($share->getNode()->getId()))
				->set('expiration', $qb->createNamedParameter($share->getExpirationDate(), IQueryBuilder::PARAM_DATE))
				->set('note', $qb->createNamedParameter($share->getNote()))
				->set('accepted', $qb->createNamedParameter($share->getStatus()))
				->execute();
		} elseif ($share->getShareType() === IShare::TYPE_GROUP) {
			$qb = $this->dbConn->getQueryBuilder();
			$qb->update('share')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))
				->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
				->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
				->set('permissions', $qb->createNamedParameter($share->getPermissions()))
				->set('item_source', $qb->createNamedParameter($share->getNode()->getId()))
				->set('file_source', $qb->createNamedParameter($share->getNode()->getId()))
				->set('expiration', $qb->createNamedParameter($share->getExpirationDate(), IQueryBuilder::PARAM_DATE))
				->set('note', $qb->createNamedParameter($share->getNote()))
				->execute();

			/*
			 * Update all user defined group shares
			 */
			$qb = $this->dbConn->getQueryBuilder();
			$qb->update('share')
				->where($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
				->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USERGROUP)))
				->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
				->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
				->set('item_source', $qb->createNamedParameter($share->getNode()->getId()))
				->set('file_source', $qb->createNamedParameter($share->getNode()->getId()))
				->set('expiration', $qb->createNamedParameter($share->getExpirationDate(), IQueryBuilder::PARAM_DATE))
				->set('note', $qb->createNamedParameter($share->getNote()))
				->execute();

			/*
			 * Now update the permissions for all children that have not set it to 0
			 */
			$qb = $this->dbConn->getQueryBuilder();
			$qb->update('share')
				->where($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
				->andWhere($qb->expr()->neq('permissions', $qb->createNamedParameter(0)))
				->set('permissions', $qb->createNamedParameter($share->getPermissions()))
				->execute();
		} elseif ($share->getShareType() === IShare::TYPE_LINK) {
			$qb = $this->dbConn->getQueryBuilder();
			$qb->update('share')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))
				->set('password', $qb->createNamedParameter($share->getPassword()))
				->set('password_by_talk', $qb->createNamedParameter($share->getSendPasswordByTalk(), IQueryBuilder::PARAM_BOOL))
				->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
				->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
				->set('permissions', $qb->createNamedParameter($share->getPermissions()))
				->set('item_source', $qb->createNamedParameter($share->getNode()->getId()))
				->set('file_source', $qb->createNamedParameter($share->getNode()->getId()))
				->set('token', $qb->createNamedParameter($share->getToken()))
				->set('expiration', $qb->createNamedParameter($share->getExpirationDate(), IQueryBuilder::PARAM_DATE))
				->set('note', $qb->createNamedParameter($share->getNote()))
				->set('label', $qb->createNamedParameter($share->getLabel()))
				->set('hide_download', $qb->createNamedParameter($share->getHideDownload() ? 1 : 0), IQueryBuilder::PARAM_INT)
				->execute();
		}

		if ($originalShare->getNote() !== $share->getNote() && $share->getNote() !== '') {
			$this->propagateNote($share);
		}


		return $share;
	}

	/**
	 * Accept a share.
	 *
	 * @param IShare $share
	 * @param string $recipient
	 * @return IShare The share object
	 * @since 9.0.0
	 */
	public function acceptShare(IShare $share, string $recipient): IShare {
		if ($share->getShareType() === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($share->getSharedWith());
			$user = $this->userManager->get($recipient);

			if (is_null($group)) {
				throw new ProviderException('Group "' . $share->getSharedWith() . '" does not exist');
			}

			if (!$group->inGroup($user)) {
				throw new ProviderException('Recipient not in receiving group');
			}

			// Try to fetch user specific share
			$qb = $this->dbConn->getQueryBuilder();
			$stmt = $qb->select('*')
				->from('share')
				->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USERGROUP)))
				->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($recipient)))
				->andWhere($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
					$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
				))
				->execute();

			$data = $stmt->fetch();
			$stmt->closeCursor();

			/*
			 * Check if there already is a user specific group share.
			 * If there is update it (if required).
			 */
			if ($data === false) {
				$id = $this->createUserSpecificGroupShare($share, $recipient);
			} else {
				$id = $data['id'];
			}
		} elseif ($share->getShareType() === IShare::TYPE_USER) {
			if ($share->getSharedWith() !== $recipient) {
				throw new ProviderException('Recipient does not match');
			}

			$id = $share->getId();
		} else {
			throw new ProviderException('Invalid shareType');
		}

		$qb = $this->dbConn->getQueryBuilder();
		$qb->update('share')
			->set('accepted', $qb->createNamedParameter(IShare::STATUS_ACCEPTED))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->execute();

		return $share;
	}

	/**
	 * Get all children of this share
	 * FIXME: remove once https://github.com/owncloud/core/pull/21660 is in
	 *
	 * @param \OCP\Share\IShare $parent
	 * @return \OCP\Share\IShare[]
	 */
	public function getChildren(\OCP\Share\IShare $parent) {
		$children = [];

		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('parent', $qb->createNamedParameter($parent->getId())))
			->andWhere(
				$qb->expr()->in(
					'share_type',
					$qb->createNamedParameter([
						IShare::TYPE_USER,
						IShare::TYPE_GROUP,
						IShare::TYPE_LINK,
					], IQueryBuilder::PARAM_INT_ARRAY)
				)
			)
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->orderBy('id');

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$children[] = $this->createShare($data);
		}
		$cursor->closeCursor();

		return $children;
	}

	/**
	 * Delete a share
	 *
	 * @param \OCP\Share\IShare $share
	 */
	public function delete(\OCP\Share\IShare $share) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->delete('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())));

		/*
		 * If the share is a group share delete all possible
		 * user defined groups shares.
		 */
		if ($share->getShareType() === IShare::TYPE_GROUP) {
			$qb->orWhere($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())));
		}

		$qb->execute();
	}

	/**
	 * Unshare a share from the recipient. If this is a group share
	 * this means we need a special entry in the share db.
	 *
	 * @param IShare $share
	 * @param string $recipient UserId of recipient
	 * @throws BackendError
	 * @throws ProviderException
	 */
	public function deleteFromSelf(IShare $share, $recipient) {
		if ($share->getShareType() === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($share->getSharedWith());
			$user = $this->userManager->get($recipient);

			if (is_null($group)) {
				throw new ProviderException('Group "' . $share->getSharedWith() . '" does not exist');
			}

			if (!$group->inGroup($user)) {
				throw new ProviderException('Recipient not in receiving group');
			}

			// Try to fetch user specific share
			$qb = $this->dbConn->getQueryBuilder();
			$stmt = $qb->select('*')
				->from('share')
				->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USERGROUP)))
				->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($recipient)))
				->andWhere($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
					$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
				))
				->execute();

			$data = $stmt->fetch();

			/*
			 * Check if there already is a user specific group share.
			 * If there is update it (if required).
			 */
			if ($data === false) {
				$id = $this->createUserSpecificGroupShare($share, $recipient);
				$permissions = $share->getPermissions();
			} else {
				$permissions = $data['permissions'];
				$id = $data['id'];
			}

			if ($permissions !== 0) {
				// Update existing usergroup share
				$qb = $this->dbConn->getQueryBuilder();
				$qb->update('share')
					->set('permissions', $qb->createNamedParameter(0))
					->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
					->execute();
			}
		} elseif ($share->getShareType() === IShare::TYPE_USER) {
			if ($share->getSharedWith() !== $recipient) {
				throw new ProviderException('Recipient does not match');
			}

			// We can just delete user and link shares
			$this->delete($share);
		} else {
			throw new ProviderException('Invalid shareType');
		}
	}

	protected function createUserSpecificGroupShare(IShare $share, string $recipient): int {
		$type = $share->getNodeType();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share')
			->values([
				'share_type' => $qb->createNamedParameter(IShare::TYPE_USERGROUP),
				'share_with' => $qb->createNamedParameter($recipient),
				'uid_owner' => $qb->createNamedParameter($share->getShareOwner()),
				'uid_initiator' => $qb->createNamedParameter($share->getSharedBy()),
				'parent' => $qb->createNamedParameter($share->getId()),
				'item_type' => $qb->createNamedParameter($type),
				'item_source' => $qb->createNamedParameter($share->getNodeId()),
				'file_source' => $qb->createNamedParameter($share->getNodeId()),
				'file_target' => $qb->createNamedParameter($share->getTarget()),
				'permissions' => $qb->createNamedParameter($share->getPermissions()),
				'stime' => $qb->createNamedParameter($share->getShareTime()->getTimestamp()),
			])->execute();

		return $qb->getLastInsertId();
	}

	/**
	 * @inheritdoc
	 *
	 * For now this only works for group shares
	 * If this gets implemented for normal shares we have to extend it
	 */
	public function restore(IShare $share, string $recipient): IShare {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('permissions')
			->from('share')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($share->getId()))
			);
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		$originalPermission = $data['permissions'];

		$qb = $this->dbConn->getQueryBuilder();
		$qb->update('share')
			->set('permissions', $qb->createNamedParameter($originalPermission))
			->where(
				$qb->expr()->eq('parent', $qb->createNamedParameter($share->getParent()))
			)->andWhere(
				$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USERGROUP))
			)->andWhere(
				$qb->expr()->eq('share_with', $qb->createNamedParameter($recipient))
			);

		$qb->execute();

		return $this->getShareById($share->getId(), $recipient);
	}

	/**
	 * @inheritdoc
	 */
	public function move(\OCP\Share\IShare $share, $recipient) {
		if ($share->getShareType() === IShare::TYPE_USER) {
			// Just update the target
			$qb = $this->dbConn->getQueryBuilder();
			$qb->update('share')
				->set('file_target', $qb->createNamedParameter($share->getTarget()))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))
				->execute();
		} elseif ($share->getShareType() === IShare::TYPE_GROUP) {

			// Check if there is a usergroup share
			$qb = $this->dbConn->getQueryBuilder();
			$stmt = $qb->select('id')
				->from('share')
				->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USERGROUP)))
				->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($recipient)))
				->andWhere($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
					$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
				))
				->setMaxResults(1)
				->execute();

			$data = $stmt->fetch();
			$stmt->closeCursor();

			if ($data === false) {
				// No usergroup share yet. Create one.
				$qb = $this->dbConn->getQueryBuilder();
				$qb->insert('share')
					->values([
						'share_type' => $qb->createNamedParameter(IShare::TYPE_USERGROUP),
						'share_with' => $qb->createNamedParameter($recipient),
						'uid_owner' => $qb->createNamedParameter($share->getShareOwner()),
						'uid_initiator' => $qb->createNamedParameter($share->getSharedBy()),
						'parent' => $qb->createNamedParameter($share->getId()),
						'item_type' => $qb->createNamedParameter($share->getNodeType()),
						'item_source' => $qb->createNamedParameter($share->getNodeId()),
						'file_source' => $qb->createNamedParameter($share->getNodeId()),
						'file_target' => $qb->createNamedParameter($share->getTarget()),
						'permissions' => $qb->createNamedParameter($share->getPermissions()),
						'stime' => $qb->createNamedParameter($share->getShareTime()->getTimestamp()),
					])->execute();
			} else {
				// Already a usergroup share. Update it.
				$qb = $this->dbConn->getQueryBuilder();
				$qb->update('share')
					->set('file_target', $qb->createNamedParameter($share->getTarget()))
					->where($qb->expr()->eq('id', $qb->createNamedParameter($data['id'])))
					->execute();
			}
		}

		return $share;
	}

	public function getSharesInFolder($userId, Folder $node, $reshares) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share', 's')
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			));

		$qb->andWhere($qb->expr()->orX(
			$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USER)),
			$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_GROUP)),
			$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_LINK))
		));

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			$qb->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)));
		} else {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId))
				)
			);
		}

		$qb->innerJoin('s', 'filecache' ,'f', $qb->expr()->eq('s.file_source', 'f.fileid'));
		$qb->andWhere($qb->expr()->eq('f.parent', $qb->createNamedParameter($node->getId())));

		$qb->orderBy('id');

		$cursor = $qb->execute();
		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[$data['fileid']][] = $this->createShare($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * @inheritdoc
	 */
	public function getSharesBy($userId, $shareType, $node, $reshares, $limit, $offset) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			));

		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter($shareType)));

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			$qb->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)));
		} else {
			if ($node === null) {
				$qb->andWhere(
					$qb->expr()->orX(
						$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
						$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId))
					)
				);
			}
		}

		if ($node !== null) {
			$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
		}

		if ($limit !== -1) {
			$qb->setMaxResults($limit);
		}

		$qb->setFirstResult($offset);
		$qb->orderBy('id');

		$cursor = $qb->execute();
		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShare($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * @inheritdoc
	 */
	public function getShareById($id, $recipientId = null) {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere(
				$qb->expr()->in(
					'share_type',
					$qb->createNamedParameter([
						IShare::TYPE_USER,
						IShare::TYPE_GROUP,
						IShare::TYPE_LINK,
					], IQueryBuilder::PARAM_INT_ARRAY)
				)
			)
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound();
		}

		try {
			$share = $this->createShare($data);
		} catch (InvalidShare $e) {
			throw new ShareNotFound();
		}

		// If the recipient is set for a group share resolve to that user
		if ($recipientId !== null && $share->getShareType() === IShare::TYPE_GROUP) {
			$share = $this->resolveGroupShares([$share], $recipientId)[0];
		}

		return $share;
	}

	/**
	 * Get shares for a given path
	 *
	 * @param \OCP\Files\Node $path
	 * @return \OCP\Share\IShare[]
	 */
	public function getSharesByPath(Node $path) {
		$qb = $this->dbConn->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($path->getId())))
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USER)),
					$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_GROUP))
				)
			)
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->execute();

		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShare($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * Returns whether the given database result can be interpreted as
	 * a share with accessible file (not trashed, not deleted)
	 */
	private function isAccessibleResult($data) {
		// exclude shares leading to deleted file entries
		if ($data['fileid'] === null || $data['path'] === null) {
			return false;
		}

		// exclude shares leading to trashbin on home storages
		$pathSections = explode('/', $data['path'], 2);
		// FIXME: would not detect rare md5'd home storage case properly
		if ($pathSections[0] !== 'files'
			&& (strpos($data['storage_string_id'], 'home::') === 0 || strpos($data['storage_string_id'], 'object::user') === 0)) {
			return false;
		}
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function getSharedWith($userId, $shareType, $node, $limit, $offset) {
		/** @var Share[] $shares */
		$shares = [];

		if ($shareType === IShare::TYPE_USER) {
			//Get shares directly with this user
			$qb = $this->dbConn->getQueryBuilder();
			$qb->select('s.*',
				'f.fileid', 'f.path', 'f.permissions AS f_permissions', 'f.storage', 'f.path_hash',
				'f.parent AS f_parent', 'f.name', 'f.mimetype', 'f.mimepart', 'f.size', 'f.mtime', 'f.storage_mtime',
				'f.encrypted', 'f.unencrypted_size', 'f.etag', 'f.checksum'
			)
				->selectAlias('st.id', 'storage_string_id')
				->from('share', 's')
				->leftJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'))
				->leftJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'));

			// Order by id
			$qb->orderBy('s.id');

			// Set limit and offset
			if ($limit !== -1) {
				$qb->setMaxResults($limit);
			}
			$qb->setFirstResult($offset);

			$qb->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USER)))
				->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($userId)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
					$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
				));

			// Filter by node if provided
			if ($node !== null) {
				$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
			}

			$cursor = $qb->execute();

			while ($data = $cursor->fetch()) {
				if ($data['fileid'] && $data['path'] === null) {
					$data['path'] = (string) $data['path'];
					$data['name'] = (string) $data['name'];
					$data['checksum'] = (string) $data['checksum'];
				}
				if ($this->isAccessibleResult($data)) {
					$shares[] = $this->createShare($data);
				}
			}
			$cursor->closeCursor();
		} elseif ($shareType === IShare::TYPE_GROUP) {
			$user = $this->userManager->get($userId);
			$allGroups = ($user instanceof IUser) ? $this->groupManager->getUserGroupIds($user) : [];

			/** @var Share[] $shares2 */
			$shares2 = [];

			$start = 0;
			while (true) {
				$groups = array_slice($allGroups, $start, 100);
				$start += 100;

				if ($groups === []) {
					break;
				}

				$qb = $this->dbConn->getQueryBuilder();
				$qb->select('s.*',
					'f.fileid', 'f.path', 'f.permissions AS f_permissions', 'f.storage', 'f.path_hash',
					'f.parent AS f_parent', 'f.name', 'f.mimetype', 'f.mimepart', 'f.size', 'f.mtime', 'f.storage_mtime',
					'f.encrypted', 'f.unencrypted_size', 'f.etag', 'f.checksum'
				)
					->selectAlias('st.id', 'storage_string_id')
					->from('share', 's')
					->leftJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'))
					->leftJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'))
					->orderBy('s.id')
					->setFirstResult(0);

				if ($limit !== -1) {
					$qb->setMaxResults($limit - count($shares));
				}

				// Filter by node if provided
				if ($node !== null) {
					$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
				}


				$groups = array_filter($groups);

				$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_GROUP)))
					->andWhere($qb->expr()->in('share_with', $qb->createNamedParameter(
						$groups,
						IQueryBuilder::PARAM_STR_ARRAY
					)))
					->andWhere($qb->expr()->orX(
						$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
						$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
					));

				$cursor = $qb->execute();
				while ($data = $cursor->fetch()) {
					if ($offset > 0) {
						$offset--;
						continue;
					}

					if ($this->isAccessibleResult($data)) {
						$shares2[] = $this->createShare($data);
					}
				}
				$cursor->closeCursor();
			}

			/*
			 * Resolve all group shares to user specific shares
			 */
			$shares = $this->resolveGroupShares($shares2, $userId);
		} else {
			throw new BackendError('Invalid backend');
		}


		return $shares;
	}

	/**
	 * Get a share by token
	 *
	 * @param string $token
	 * @return \OCP\Share\IShare
	 * @throws ShareNotFound
	 */
	public function getShareByToken($token) {
		$qb = $this->dbConn->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_LINK)))
			->andWhere($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->execute();

		$data = $cursor->fetch();

		if ($data === false) {
			throw new ShareNotFound();
		}

		try {
			$share = $this->createShare($data);
		} catch (InvalidShare $e) {
			throw new ShareNotFound();
		}

		return $share;
	}

	/**
	 * Create a share object from an database row
	 *
	 * @param mixed[] $data
	 * @return \OCP\Share\IShare
	 * @throws InvalidShare
	 */
	private function createShare($data) {
		$share = new Share($this->rootFolder, $this->userManager);
		$share->setId((int)$data['id'])
			->setShareType((int)$data['share_type'])
			->setPermissions((int)$data['permissions'])
			->setTarget($data['file_target'])
			->setNote((string)$data['note'])
			->setMailSend((bool)$data['mail_send'])
			->setStatus((int)$data['accepted'])
			->setLabel($data['label']);

		$shareTime = new \DateTime();
		$shareTime->setTimestamp((int)$data['stime']);
		$share->setShareTime($shareTime);

		if ($share->getShareType() === IShare::TYPE_USER) {
			$share->setSharedWith($data['share_with']);
			$user = $this->userManager->get($data['share_with']);
			if ($user !== null) {
				$share->setSharedWithDisplayName($user->getDisplayName());
			}
		} elseif ($share->getShareType() === IShare::TYPE_GROUP) {
			$share->setSharedWith($data['share_with']);
		} elseif ($share->getShareType() === IShare::TYPE_LINK) {
			$share->setPassword($data['password']);
			$share->setSendPasswordByTalk((bool)$data['password_by_talk']);
			$share->setToken($data['token']);
		}

		$share->setSharedBy($data['uid_initiator']);
		$share->setShareOwner($data['uid_owner']);

		$share->setNodeId((int)$data['file_source']);
		$share->setNodeType($data['item_type']);

		if ($data['expiration'] !== null) {
			$expiration = \DateTime::createFromFormat('Y-m-d H:i:s', $data['expiration']);
			$share->setExpirationDate($expiration);
		}

		if (isset($data['f_permissions'])) {
			$entryData = $data;
			$entryData['permissions'] = $entryData['f_permissions'];
			$entryData['parent'] = $entryData['f_parent'];
			$share->setNodeCacheEntry(Cache::cacheEntryFromData($entryData,
				\OC::$server->getMimeTypeLoader()));
		}

		$share->setProviderId($this->identifier());
		$share->setHideDownload((int)$data['hide_download'] === 1);

		return $share;
	}

	/**
	 * @param Share[] $shares
	 * @param $userId
	 * @return Share[] The updates shares if no update is found for a share return the original
	 */
	private function resolveGroupShares($shares, $userId) {
		$result = [];

		$start = 0;
		while (true) {
			/** @var Share[] $shareSlice */
			$shareSlice = array_slice($shares, $start, 100);
			$start += 100;

			if ($shareSlice === []) {
				break;
			}

			/** @var int[] $ids */
			$ids = [];
			/** @var Share[] $shareMap */
			$shareMap = [];

			foreach ($shareSlice as $share) {
				$ids[] = (int)$share->getId();
				$shareMap[$share->getId()] = $share;
			}

			$qb = $this->dbConn->getQueryBuilder();

			$query = $qb->select('*')
				->from('share')
				->where($qb->expr()->in('parent', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($userId)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
					$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
				));

			$stmt = $query->execute();

			while ($data = $stmt->fetch()) {
				$shareMap[$data['parent']]->setPermissions((int)$data['permissions']);
				$shareMap[$data['parent']]->setStatus((int)$data['accepted']);
				$shareMap[$data['parent']]->setTarget($data['file_target']);
				$shareMap[$data['parent']]->setParent($data['parent']);
			}

			$stmt->closeCursor();

			foreach ($shareMap as $share) {
				$result[] = $share;
			}
		}

		return $result;
	}

	/**
	 * A user is deleted from the system
	 * So clean up the relevant shares.
	 *
	 * @param string $uid
	 * @param int $shareType
	 */
	public function userDeleted($uid, $shareType) {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->delete('share');

		if ($shareType === IShare::TYPE_USER) {
			/*
			 * Delete all user shares that are owned by this user
			 * or that are received by this user
			 */

			$qb->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USER)));

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($uid)),
					$qb->expr()->eq('share_with', $qb->createNamedParameter($uid))
				)
			);
		} elseif ($shareType === IShare::TYPE_GROUP) {
			/*
			 * Delete all group shares that are owned by this user
			 * Or special user group shares that are received by this user
			 */
			$qb->where(
				$qb->expr()->andX(
					$qb->expr()->orX(
						$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_GROUP)),
						$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USERGROUP))
					),
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($uid))
				)
			);

			$qb->orWhere(
				$qb->expr()->andX(
					$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USERGROUP)),
					$qb->expr()->eq('share_with', $qb->createNamedParameter($uid))
				)
			);
		} elseif ($shareType === IShare::TYPE_LINK) {
			/*
			 * Delete all link shares owned by this user.
			 * And all link shares initiated by this user (until #22327 is in)
			 */
			$qb->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_LINK)));

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($uid)),
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($uid))
				)
			);
		} else {
			\OC::$server->getLogger()->logException(new \InvalidArgumentException('Default share provider tried to delete all shares for type: ' . $shareType));
			return;
		}

		$qb->execute();
	}

	/**
	 * Delete all shares received by this group. As well as any custom group
	 * shares for group members.
	 *
	 * @param string $gid
	 */
	public function groupDeleted($gid) {
		/*
		 * First delete all custom group shares for group members
		 */
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('id')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_GROUP)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($gid)));

		$cursor = $qb->execute();
		$ids = [];
		while ($row = $cursor->fetch()) {
			$ids[] = (int)$row['id'];
		}
		$cursor->closeCursor();

		if (!empty($ids)) {
			$chunks = array_chunk($ids, 100);
			foreach ($chunks as $chunk) {
				$qb->delete('share')
					->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USERGROUP)))
					->andWhere($qb->expr()->in('parent', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
				$qb->execute();
			}
		}

		/*
		 * Now delete all the group shares
		 */
		$qb = $this->dbConn->getQueryBuilder();
		$qb->delete('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_GROUP)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($gid)));
		$qb->execute();
	}

	/**
	 * Delete custom group shares to this group for this user
	 *
	 * @param string $uid
	 * @param string $gid
	 */
	public function userDeletedFromGroup($uid, $gid) {
		/*
		 * Get all group shares
		 */
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('id')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_GROUP)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($gid)));

		$cursor = $qb->execute();
		$ids = [];
		while ($row = $cursor->fetch()) {
			$ids[] = (int)$row['id'];
		}
		$cursor->closeCursor();

		if (!empty($ids)) {
			$chunks = array_chunk($ids, 100);
			foreach ($chunks as $chunk) {
				/*
				 * Delete all special shares wit this users for the found group shares
				 */
				$qb->delete('share')
					->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USERGROUP)))
					->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($uid)))
					->andWhere($qb->expr()->in('parent', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
				$qb->execute();
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getAccessList($nodes, $currentAccess) {
		$ids = [];
		foreach ($nodes as $node) {
			$ids[] = $node->getId();
		}

		$qb = $this->dbConn->getQueryBuilder();

		$or = $qb->expr()->orX(
			$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USER)),
			$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_GROUP)),
			$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_LINK))
		);

		if ($currentAccess) {
			$or->add($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_USERGROUP)));
		}

		$qb->select('id', 'parent', 'share_type', 'share_with', 'file_source', 'file_target', 'permissions')
			->from('share')
			->where(
				$or
			)
			->andWhere($qb->expr()->in('file_source', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			));
		$cursor = $qb->execute();

		$users = [];
		$link = false;
		while ($row = $cursor->fetch()) {
			$type = (int)$row['share_type'];
			if ($type === IShare::TYPE_USER) {
				$uid = $row['share_with'];
				$users[$uid] = isset($users[$uid]) ? $users[$uid] : [];
				$users[$uid][$row['id']] = $row;
			} elseif ($type === IShare::TYPE_GROUP) {
				$gid = $row['share_with'];
				$group = $this->groupManager->get($gid);

				if ($group === null) {
					continue;
				}

				$userList = $group->getUsers();
				foreach ($userList as $user) {
					$uid = $user->getUID();
					$users[$uid] = isset($users[$uid]) ? $users[$uid] : [];
					$users[$uid][$row['id']] = $row;
				}
			} elseif ($type === IShare::TYPE_LINK) {
				$link = true;
			} elseif ($type === IShare::TYPE_USERGROUP && $currentAccess === true) {
				$uid = $row['share_with'];
				$users[$uid] = isset($users[$uid]) ? $users[$uid] : [];
				$users[$uid][$row['id']] = $row;
			}
		}
		$cursor->closeCursor();

		if ($currentAccess === true) {
			$users = array_map([$this, 'filterSharesOfUser'], $users);
			$users = array_filter($users);
		} else {
			$users = array_keys($users);
		}

		return ['users' => $users, 'public' => $link];
	}

	/**
	 * For each user the path with the fewest slashes is returned
	 * @param array $shares
	 * @return array
	 */
	protected function filterSharesOfUser(array $shares) {
		// Group shares when the user has a share exception
		foreach ($shares as $id => $share) {
			$type = (int) $share['share_type'];
			$permissions = (int) $share['permissions'];

			if ($type === IShare::TYPE_USERGROUP) {
				unset($shares[$share['parent']]);

				if ($permissions === 0) {
					unset($shares[$id]);
				}
			}
		}

		$best = [];
		$bestDepth = 0;
		foreach ($shares as $id => $share) {
			$depth = substr_count($share['file_target'], '/');
			if (empty($best) || $depth < $bestDepth) {
				$bestDepth = $depth;
				$best = [
					'node_id' => $share['file_source'],
					'node_path' => $share['file_target'],
				];
			}
		}

		return $best;
	}

	/**
	 * propagate notes to the recipients
	 *
	 * @param IShare $share
	 * @throws \OCP\Files\NotFoundException
	 */
	private function propagateNote(IShare $share) {
		if ($share->getShareType() === IShare::TYPE_USER) {
			$user = $this->userManager->get($share->getSharedWith());
			$this->sendNote([$user], $share);
		} elseif ($share->getShareType() === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($share->getSharedWith());
			$groupMembers = $group->getUsers();
			$this->sendNote($groupMembers, $share);
		}
	}

	/**
	 * send note by mail
	 *
	 * @param array $recipients
	 * @param IShare $share
	 * @throws \OCP\Files\NotFoundException
	 */
	private function sendNote(array $recipients, IShare $share) {
		$toListByLanguage = [];

		foreach ($recipients as $recipient) {
			/** @var IUser $recipient */
			$email = $recipient->getEMailAddress();
			if ($email) {
				$language = $this->l10nFactory->getUserLanguage($recipient);
				if (!isset($toListByLanguage[$language])) {
					$toListByLanguage[$language] = [];
				}
				$toListByLanguage[$language][$email] = $recipient->getDisplayName();
			}
		}

		if (empty($toListByLanguage)) {
			return;
		}

		foreach ($toListByLanguage as $l10n => $toList) {
			$filename = $share->getNode()->getName();
			$initiator = $share->getSharedBy();
			$note = $share->getNote();

			$l = $this->l10nFactory->get('lib', $l10n);

			$initiatorUser = $this->userManager->get($initiator);
			$initiatorDisplayName = ($initiatorUser instanceof IUser) ? $initiatorUser->getDisplayName() : $initiator;
			$initiatorEmailAddress = ($initiatorUser instanceof IUser) ? $initiatorUser->getEMailAddress() : null;
			$plainHeading = $l->t('%1$s shared »%2$s« with you and wants to add:', [$initiatorDisplayName, $filename]);
			$htmlHeading = $l->t('%1$s shared »%2$s« with you and wants to add', [$initiatorDisplayName, $filename]);
			$message = $this->mailer->createMessage();

			$emailTemplate = $this->mailer->createEMailTemplate('defaultShareProvider.sendNote');

			$emailTemplate->setSubject($l->t('»%s« added a note to a file shared with you', [$initiatorDisplayName]));
			$emailTemplate->addHeader();
			$emailTemplate->addHeading($htmlHeading, $plainHeading);
			$emailTemplate->addBodyText(htmlspecialchars($note), $note);

			$link = $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $share->getNode()->getId()]);
			$emailTemplate->addBodyButton(
				$l->t('Open »%s«', [$filename]),
				$link
			);


			// The "From" contains the sharers name
			$instanceName = $this->defaults->getName();
			$senderName = $l->t(
				'%1$s via %2$s',
				[
					$initiatorDisplayName,
					$instanceName
				]
			);
			$message->setFrom([\OCP\Util::getDefaultEmailAddress($instanceName) => $senderName]);
			if ($initiatorEmailAddress !== null) {
				$message->setReplyTo([$initiatorEmailAddress => $initiatorDisplayName]);
				$emailTemplate->addFooter($instanceName . ' - ' . $this->defaults->getSlogan());
			} else {
				$emailTemplate->addFooter();
			}

			if (count($toList) === 1) {
				$message->setTo($toList);
			} else {
				$message->setTo([]);
				$message->setBcc($toList);
			}
			$message->useTemplate($emailTemplate);
			$this->mailer->send($message);
		}
	}

	public function getAllShares(): iterable {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share\IShare::TYPE_USER)),
					$qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share\IShare::TYPE_GROUP)),
					$qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share\IShare::TYPE_LINK))
				)
			);

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			try {
				$share = $this->createShare($data);
			} catch (InvalidShare $e) {
				continue;
			}

			yield $share;
		}
		$cursor->closeCursor();
	}
}
