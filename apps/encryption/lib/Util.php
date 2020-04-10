<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Phil Davis <phil.davis@inf.org>
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

namespace OCA\Encryption;

use OC\Files\View;
use OCA\Encryption\Crypto\Crypt;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\PreConditionNotMetException;

class Util {
	/**
	 * @var View
	 */
	private $files;
	/**
	 * @var Crypt
	 */
	private $crypt;
	/**
	 * @var ILogger
	 */
	private $logger;
	/**
	 * @var bool|IUser
	 */
	private $user;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * Util constructor.
	 *
	 * @param View $files
	 * @param Crypt $crypt
	 * @param ILogger $logger
	 * @param IUserSession $userSession
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 */
	public function __construct(View $files,
								Crypt $crypt,
								ILogger $logger,
								IUserSession $userSession,
								IConfig $config,
								IUserManager $userManager
	) {
		$this->files = $files;
		$this->crypt = $crypt;
		$this->logger = $logger;
		$this->user = $userSession && $userSession->isLoggedIn() ? $userSession->getUser() : false;
		$this->config = $config;
		$this->userManager = $userManager;
	}

	/**
	 * check if recovery key is enabled for user
	 *
	 * @param string $uid
	 * @return bool
	 */
	public function isRecoveryEnabledForUser($uid) {
		$recoveryMode = $this->config->getUserValue($uid,
			'encryption',
			'recoveryEnabled',
			'0');

		return ($recoveryMode === '1');
	}

	/**
	 * check if the home storage should be encrypted
	 *
	 * @return bool
	 */
	public function shouldEncryptHomeStorage() {
		$encryptHomeStorage = $this->config->getAppValue(
			'encryption',
			'encryptHomeStorage',
			'1'
		);

		return ($encryptHomeStorage === '1');
	}

	/**
	 * set the home storage encryption on/off
	 *
	 * @param bool $encryptHomeStorage
	 */
	public function setEncryptHomeStorage($encryptHomeStorage) {
		$value = $encryptHomeStorage ? '1' : '0';
		$this->config->setAppValue(
			'encryption',
			'encryptHomeStorage',
			$value
		);
	}

	/**
	 * check if master key is enabled
	 *
	 * @return bool
	 */
	public function isMasterKeyEnabled() {
		$userMasterKey = $this->config->getAppValue('encryption', 'useMasterKey', '1');
		return ($userMasterKey === '1');
	}

	/**
	 * @param $enabled
	 * @return bool
	 */
	public function setRecoveryForUser($enabled) {
		$value = $enabled ? '1' : '0';

		try {
			$this->config->setUserValue($this->user->getUID(),
				'encryption',
				'recoveryEnabled',
				$value);
			return true;
		} catch (PreConditionNotMetException $e) {
			return false;
		}
	}

	/**
	 * @param string $uid
	 * @return bool
	 */
	public function userHasFiles($uid) {
		return $this->files->file_exists($uid . '/files');
	}

	/**
	 * get owner from give path, path relative to data/ expected
	 *
	 * @param string $path relative to data/
	 * @return string
	 * @throws \BadMethodCallException
	 */
	public function getOwner($path) {
		$owner = '';
		$parts = explode('/', $path, 3);
		if (count($parts) > 1) {
			$owner = $parts[1];
			if ($this->userManager->userExists($owner) === false) {
				throw new \BadMethodCallException('Unknown user: ' .
				'method expects path to a user folder relative to the data folder');
			}

		}

		return $owner;
	}

	/**
	 * get storage of path
	 *
	 * @param string $path
	 * @return \OC\Files\Storage\Storage
	 */
	public function getStorage($path) {
		return $this->files->getMount($path)->getStorage();
	}

}
