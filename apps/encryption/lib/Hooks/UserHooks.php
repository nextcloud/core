<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Encryption\Hooks;

use OC\Files\Filesystem;
use OCA\Encryption\Crypto\Crypt;
use OCA\Encryption\Hooks\Contracts\IHook;
use OCA\Encryption\KeyManager;
use OCA\Encryption\Recovery;
use OCA\Encryption\Session;
use OCA\Encryption\Users\Setup;
use OCA\Encryption\Util;
use OCP\Encryption\Exceptions\GenericEncryptionException;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Util as OCUtil;

class UserHooks implements IHook {

	/**
	 * list of user for which we perform a password reset
	 * @var array
	 */
	protected static $passwordResetUsers = [];

	/**
	 * @var KeyManager
	 */
	private $keyManager;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var ILogger
	 */
	private $logger;
	/**
	 * @var Setup
	 */
	private $userSetup;
	/**
	 * @var IUserSession
	 */
	private $userSession;
	/**
	 * @var Util
	 */
	private $util;
	/**
	 * @var Session
	 */
	private $session;
	/**
	 * @var Recovery
	 */
	private $recovery;
	/**
	 * @var Crypt
	 */
	private $crypt;

	/**
	 * UserHooks constructor.
	 *
	 * @param KeyManager $keyManager
	 * @param IUserManager $userManager
	 * @param ILogger $logger
	 * @param Setup $userSetup
	 * @param IUserSession $userSession
	 * @param Util $util
	 * @param Session $session
	 * @param Crypt $crypt
	 * @param Recovery $recovery
	 */
	public function __construct(KeyManager $keyManager,
								IUserManager $userManager,
								ILogger $logger,
								Setup $userSetup,
								IUserSession $userSession,
								Util $util,
								Session $session,
								Crypt $crypt,
								Recovery $recovery) {

		$this->keyManager = $keyManager;
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->userSetup = $userSetup;
		$this->userSession = $userSession;
		$this->util = $util;
		$this->session = $session;
		$this->recovery = $recovery;
		$this->crypt = $crypt;
	}

	/**
	 * Connects Hooks
	 *
	 * @return null
	 */
	public function addHooks() {
		OCUtil::connectHook('OC_User', 'post_login', $this, 'login');
		OCUtil::connectHook('OC_User', 'logout', $this, 'logout');

		// this hooks only make sense if no master key is used
		if ($this->util->isMasterKeyEnabled() === false) {
			OCUtil::connectHook('OC_User',
				'post_setPassword',
				$this,
				'setPassphrase');

			OCUtil::connectHook('OC_User',
				'pre_setPassword',
				$this,
				'preSetPassphrase');

			OCUtil::connectHook('\OC\Core\LostPassword\Controller\LostController',
				'post_passwordReset',
				$this,
				'postPasswordReset');

			OCUtil::connectHook('\OC\Core\LostPassword\Controller\LostController',
				'pre_passwordReset',
				$this,
				'prePasswordReset');

			OCUtil::connectHook('OC_User',
				'post_createUser',
				$this,
				'postCreateUser');

			OCUtil::connectHook('OC_User',
				'post_deleteUser',
				$this,
				'postDeleteUser');
		}
	}


	/**
	 * Startup encryption backend upon user login
	 *
	 * @note This method should never be called for users using client side encryption
	 * @param array $params
	 * @return boolean|null
	 */
	public function login($params) {
		// ensure filesystem is loaded
		if (!\OC\Files\Filesystem::$loaded) {
			$this->setupFS($params['uid']);
		}
		if ($this->util->isMasterKeyEnabled() === false) {
			$this->userSetup->setupUser($params['uid'], $params['password']);
		}

		$this->keyManager->init($params['uid'], $params['password']);
	}

	/**
	 * remove keys from session during logout
	 */
	public function logout() {
		$this->session->clear();
	}

	/**
	 * setup encryption backend upon user created
	 *
	 * @note This method should never be called for users using client side encryption
	 * @param array $params
	 */
	public function postCreateUser($params) {
		$this->userSetup->setupUser($params['uid'], $params['password']);
	}

	/**
	 * cleanup encryption backend upon user deleted
	 *
	 * @param array $params : uid, password
	 * @note This method should never be called for users using client side encryption
	 */
	public function postDeleteUser($params) {
		$this->keyManager->deletePublicKey($params['uid']);
	}

	public function prePasswordReset($params) {
		$user = $params['uid'];
		self::$passwordResetUsers[$user] = true;
	}

	public function postPasswordReset($params) {
		$uid = $params['uid'];
		$password = $params['password'];
		$this->keyManager->backupUserKeys('passwordReset', $uid);
		$this->keyManager->deleteUserKeys($uid);
		$this->userSetup->setupUser($uid, $password);
		unset(self::$passwordResetUsers[$uid]);
	}

	/**
	 * If the password can't be changed within Nextcloud, than update the key password in advance.
	 *
	 * @param array $params : uid, password
	 * @return boolean|null
	 */
	public function preSetPassphrase($params) {
		$user = $this->userManager->get($params['uid']);

		if ($user && !$user->canChangePassword()) {
			$this->setPassphrase($params);
		}
	}

	/**
	 * Change a user's encryption passphrase
	 *
	 * @param array $params keys: uid, password
	 * @return boolean|null
	 */
	public function setPassphrase($params) {

		// if we are in the process to resetting a user password, we have nothing
		// to do here
		if (isset(self::$passwordResetUsers[$params['uid']])) {
			return true;
		}

		// Get existing decrypted private key
		$user = $this->userSession->getUser();

		// current logged in user changes his own password
		if ($user && $params['uid'] === $user->getUID()) {

			$privateKey = $this->session->getPrivateKey();

			// Encrypt private key with new user pwd as passphrase
			$encryptedPrivateKey = $this->crypt->encryptPrivateKey($privateKey, $params['password'], $params['uid']);

			// Save private key
			if ($encryptedPrivateKey) {
				$this->keyManager->setPrivateKey($user->getUID(),
					$this->crypt->generateHeader() . $encryptedPrivateKey);
			} else {
				$this->logger->error('Encryption could not update users encryption password');
			}

			// NOTE: Session does not need to be updated as the
			// private key has not changed, only the passphrase
			// used to decrypt it has changed
		} else { // admin changed the password for a different user, create new keys and re-encrypt file keys
			$userId = $params['uid'];
			$this->initMountPoints($userId);
			$recoveryPassword = isset($params['recoveryPassword']) ? $params['recoveryPassword'] : null;

			$recoveryKeyId = $this->keyManager->getRecoveryKeyId();
			$recoveryKey = $this->keyManager->getSystemPrivateKey($recoveryKeyId);
			try {
				$decryptedRecoveryKey = $this->crypt->decryptPrivateKey($recoveryKey, $recoveryPassword);
			} catch (\Exception $e) {
				$decryptedRecoveryKey = false;
			}
			if ($decryptedRecoveryKey === false) {
				$message = 'Can not decrypt the recovery key. Maybe you provided the wrong password. Try again.';
				throw new GenericEncryptionException($message, $message);
			}

			// we generate new keys if...
			// ...we have a recovery password and the user enabled the recovery key
			// ...encryption was activated for the first time (no keys exists)
			// ...the user doesn't have any files
			if (
				($this->recovery->isRecoveryEnabledForUser($userId) && $recoveryPassword)
				|| !$this->keyManager->userHasKeys($userId)
				|| !$this->util->userHasFiles($userId)
			) {

				// backup old keys
				//$this->backupAllKeys('recovery');

				$newUserPassword = $params['password'];

				$keyPair = $this->crypt->createKeyPair();

				// Save public key
				$this->keyManager->setPublicKey($userId, $keyPair['publicKey']);

				// Encrypt private key with new password
				$encryptedKey = $this->crypt->encryptPrivateKey($keyPair['privateKey'], $newUserPassword, $userId);

				if ($encryptedKey) {
					$this->keyManager->setPrivateKey($userId, $this->crypt->generateHeader() . $encryptedKey);

					if ($recoveryPassword) { // if recovery key is set we can re-encrypt the key files
						$this->recovery->recoverUsersFiles($recoveryPassword, $userId);
					}
				} else {
					$this->logger->error('Encryption Could not update users encryption password');
				}
			}
		}
	}

	/**
	 * init mount points for given user
	 *
	 * @param string $user
	 * @throws \OC\User\NoUserException
	 */
	protected function initMountPoints($user) {
		Filesystem::initMountPoints($user);
	}

	/**
	 * setup file system for user
	 *
	 * @param string $uid user id
	 */
	protected function setupFS($uid) {
		\OC_Util::setupFS($uid);
	}
}
