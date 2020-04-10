<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\Encryption\Users;

use OCA\Encryption\Crypto\Crypt;
use OCA\Encryption\KeyManager;
use OCP\ILogger;
use OCP\IUserSession;

class Setup {
	/**
	 * @var Crypt
	 */
	private $crypt;
	/**
	 * @var KeyManager
	 */
	private $keyManager;
	/**
	 * @var ILogger
	 */
	private $logger;
	/**
	 * @var bool|string
	 */
	private $user;


	/**
	 * @param ILogger $logger
	 * @param IUserSession $userSession
	 * @param Crypt $crypt
	 * @param KeyManager $keyManager
	 */
	public function __construct(ILogger $logger,
								IUserSession $userSession,
								Crypt $crypt,
								KeyManager $keyManager) {
		$this->logger = $logger;
		$this->user = $userSession && $userSession->isLoggedIn() ? $userSession->getUser()->getUID() : false;
		$this->crypt = $crypt;
		$this->keyManager = $keyManager;
	}

	/**
	 * @param string $uid user id
	 * @param string $password user password
	 * @return bool
	 */
	public function setupUser($uid, $password) {
		if (!$this->keyManager->userHasKeys($uid)) {
			return $this->keyManager->storeKeyPair($uid, $password,
				$this->crypt->createKeyPair());
		}
		return true;
	}

	/**
	 * make sure that all system keys exists
	 */
	public function setupSystem() {
		$this->keyManager->validateShareKey();
		$this->keyManager->validateMasterKey();
	}
}
