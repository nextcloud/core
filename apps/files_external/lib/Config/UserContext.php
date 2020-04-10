<?php
/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Files_External\Config;

use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;

class UserContext {

	/** @var IUserSession */
	private $session;

	/** @var ShareManager */
	private $shareManager;

	/** @var IRequest */
	private $request;

	/** @var string */
	private $userId;

	/** @var IUserManager */
	private $userManager;

	public function __construct(IUserSession $session, ShareManager $manager, IRequest $request, IUserManager $userManager) {
		$this->session = $session;
		$this->shareManager = $manager;
		$this->request = $request;
		$this->userManager = $userManager;
	}

	public function getSession(): IUserSession {
		return $this->session;
	}

	public function setUserId(string $userId): void {
		$this->userId = $userId;
	}

	protected function getUserId(): ?string {
		if ($this->userId !== null) {
			return $this->userId;
		}
		if($this->session && $this->session->getUser() !== null) {
			return $this->session->getUser()->getUID();
		}
		try {
			$shareToken = $this->request->getParam('token');
			$share = $this->shareManager->getShareByToken($shareToken);
			return $share->getShareOwner();
		} catch (ShareNotFound $e) {}

		return null;
	}

	protected function getUser(): ?IUser {
		$userId = $this->getUserId();
		if($userId !== null) {
			return $this->userManager->get($userId);
		}
		return null;
	}

}
