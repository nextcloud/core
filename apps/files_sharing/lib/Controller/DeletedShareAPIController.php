<?php

declare(strict_types=1);

/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Files_Sharing\Controller;

use OCP\App\IAppManager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\QueryException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\IUserManager;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;

class DeletedShareAPIController extends OCSController {

	/** @var ShareManager */
	private $shareManager;

	/** @var string */
	private $userId;

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IAppManager */
	private $appManager;

	/** @var IServerContainer */
	private $serverContainer;

	public function __construct(string $appName,
								IRequest $request,
								ShareManager $shareManager,
								string $UserId,
								IUserManager $userManager,
								IGroupManager $groupManager,
								IRootFolder $rootFolder,
								IAppManager $appManager,
								IServerContainer $serverContainer) {
		parent::__construct($appName, $request);

		$this->shareManager = $shareManager;
		$this->userId = $UserId;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->rootFolder = $rootFolder;
		$this->appManager = $appManager;
		$this->serverContainer = $serverContainer;
	}

	/**
	 * @suppress PhanUndeclaredClassMethod
	 */
	private function formatShare(IShare $share): array {
		$result = [
			'id' => $share->getFullId(),
			'share_type' => $share->getShareType(),
			'uid_owner' => $share->getSharedBy(),
			'displayname_owner' => $this->userManager->get($share->getSharedBy())->getDisplayName(),
			'permissions' => 0,
			'stime' => $share->getShareTime()->getTimestamp(),
			'parent' => null,
			'expiration' => null,
			'token' => null,
			'uid_file_owner' => $share->getShareOwner(),
			'displayname_file_owner' => $this->userManager->get($share->getShareOwner())->getDisplayName(),
			'path' => $share->getTarget(),
		];
		$userFolder = $this->rootFolder->getUserFolder($share->getSharedBy());
		$nodes = $userFolder->getById($share->getNodeId());
		if (empty($nodes)) {
			// fallback to guessing the path
			$node = $userFolder->get($share->getTarget());
			if ($node === null || $share->getTarget() === '') {
				throw new NotFoundException();
			}
		} else {
			$node = $nodes[0];
		}

		$result['path'] = $userFolder->getRelativePath($node->getPath());
		if ($node instanceof \OCP\Files\Folder) {
			$result['item_type'] = 'folder';
		} else {
			$result['item_type'] = 'file';
		}
		$result['mimetype'] = $node->getMimetype();
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

		if ($share->getShareType() === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($share->getSharedWith());
			$result['share_with'] = $share->getSharedWith();
			$result['share_with_displayname'] = $group !== null ? $group->getDisplayName() : $share->getSharedWith();
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

		return $result;
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(): DataResponse {
		$groupShares = $this->shareManager->getDeletedSharedWith($this->userId, IShare::TYPE_GROUP, null, -1, 0);
		$roomShares = $this->shareManager->getDeletedSharedWith($this->userId, IShare::TYPE_ROOM, null, -1, 0);
		$deckShares = $this->shareManager->getDeletedSharedWith($this->userId, IShare::TYPE_DECK, null, -1, 0);

		$shares = array_merge($groupShares, $roomShares, $deckShares);

		$shares = array_map(function (IShare $share) {
			return $this->formatShare($share);
		}, $shares);

		return new DataResponse($shares);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @throws OCSException
	 */
	public function undelete(string $id): DataResponse {
		try {
			$share = $this->shareManager->getShareById($id, $this->userId);
		} catch (ShareNotFound $e) {
			throw new OCSNotFoundException('Share not found');
		}

		if ($share->getPermissions() !== 0) {
			throw new OCSNotFoundException('No deleted share found');
		}

		try {
			$this->shareManager->restoreShare($share, $this->userId);
		} catch (GenericShareException $e) {
			throw new OCSException('Something went wrong');
		}

		return new DataResponse([]);
	}

	/**
	 * Returns the helper of DeletedShareAPIController for room shares.
	 *
	 * If the Talk application is not enabled or the helper is not available
	 * a QueryException is thrown instead.
	 *
	 * @return \OCA\Talk\Share\Helper\DeletedShareAPIController
	 * @throws QueryException
	 */
	private function getRoomShareHelper() {
		if (!$this->appManager->isEnabledForUser('spreed')) {
			throw new QueryException();
		}

		return $this->serverContainer->get('\OCA\Talk\Share\Helper\DeletedShareAPIController');
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
}
