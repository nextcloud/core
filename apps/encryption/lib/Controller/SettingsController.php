<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Encryption\Controller;

use OCA\Encryption\Crypto\Crypt;
use OCA\Encryption\KeyManager;
use OCA\Encryption\Session;
use OCA\Encryption\Util;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserManager;
use OCP\IUserSession;

class SettingsController extends Controller {

	/** @var IL10N */
	private $l;

	/** @var IUserManager */
	private $userManager;

	/** @var IUserSession */
	private $userSession;

	/** @var KeyManager */
	private $keyManager;

	/** @var Crypt */
	private $crypt;

	/** @var Session */
	private $session;

	/** @var ISession  */
	private $ocSession;

	/** @var  Util */
	private $util;

	/**
	 * @param string $AppName
	 * @param IRequest $request
	 * @param IL10N $l10n
	 * @param IUserManager $userManager
	 * @param IUserSession $userSession
	 * @param KeyManager $keyManager
	 * @param Crypt $crypt
	 * @param Session $session
	 * @param ISession $ocSession
	 * @param Util $util
	 */
	public function __construct($AppName,
								IRequest $request,
								IL10N $l10n,
								IUserManager $userManager,
								IUserSession $userSession,
								KeyManager $keyManager,
								Crypt $crypt,
								Session $session,
								ISession $ocSession,
								Util $util
) {
		parent::__construct($AppName, $request);
		$this->l = $l10n;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->keyManager = $keyManager;
		$this->crypt = $crypt;
		$this->session = $session;
		$this->ocSession = $ocSession;
		$this->util = $util;
	}


	/**
	 * @NoAdminRequired
	 * @UseSession
	 *
	 * @param string $oldPassword
	 * @param string $newPassword
	 * @return DataResponse
	 */
	public function updatePrivateKeyPassword($oldPassword, $newPassword) {
		$result = false;
		$uid = $this->userSession->getUser()->getUID();
		$errorMessage = $this->l->t('Could not update the private key password.');

		//check if password is correct
		$passwordCorrect = $this->userManager->checkPassword($uid, $newPassword);
		if ($passwordCorrect === false) {
			// if check with uid fails we need to check the password with the login name
			// e.g. in the ldap case. For local user we need to check the password with
			// the uid because in this case the login name is case insensitive
			$loginName = $this->ocSession->get('loginname');
			$passwordCorrect = $this->userManager->checkPassword($loginName, $newPassword);
		}

		if ($passwordCorrect !== false) {
			$encryptedKey = $this->keyManager->getPrivateKey($uid);
			$decryptedKey = $this->crypt->decryptPrivateKey($encryptedKey, $oldPassword, $uid);

			if ($decryptedKey) {
				$encryptedKey = $this->crypt->encryptPrivateKey($decryptedKey, $newPassword, $uid);
				$header = $this->crypt->generateHeader();
				if ($encryptedKey) {
					$this->keyManager->setPrivateKey($uid, $header . $encryptedKey);
					$this->session->setPrivateKey($decryptedKey);
					$result = true;
				}
			} else {
				$errorMessage = $this->l->t('The old password was not correct, please try again.');
			}
		} else {
			$errorMessage = $this->l->t('The current log-in password was not correct, please try again.');
		}

		if ($result === true) {
			$this->session->setStatus(Session::INIT_SUCCESSFUL);
			return new DataResponse(
				['message' => (string) $this->l->t('Private key password successfully updated.')]
			);
		} else {
			return new DataResponse(
				['message' => (string) $errorMessage],
				Http::STATUS_BAD_REQUEST
			);
		}

	}

	/**
	 * @UseSession
	 *
	 * @param bool $encryptHomeStorage
	 * @return DataResponse
	 */
	public function setEncryptHomeStorage($encryptHomeStorage) {
		$this->util->setEncryptHomeStorage($encryptHomeStorage);
		return new DataResponse();
	}
}
