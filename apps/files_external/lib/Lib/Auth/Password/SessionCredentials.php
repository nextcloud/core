<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\Files_External\Lib\Auth\Password;

use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException;
use OCA\Files_External\Lib\SessionStorageWrapper;
use OCA\Files_External\Lib\StorageConfig;
use OCP\Authentication\Exceptions\CredentialsUnavailableException;
use OCP\Authentication\LoginCredentials\IStore as CredentialsStore;
use OCP\Files\Storage;
use OCP\IL10N;
use OCP\IUser;

/**
 * Username and password from login credentials, saved in session
 */
class SessionCredentials extends AuthMechanism {

	/** @var CredentialsStore */
	private $credentialsStore;

	public function __construct(IL10N $l, CredentialsStore $credentialsStore) {
		$this->credentialsStore = $credentialsStore;

		$this->setIdentifier('password::sessioncredentials')
			->setScheme(self::SCHEME_PASSWORD)
			->setText($l->t('Log-in credentials, save in session'))
			->addParameters([]);
	}

	public function manipulateStorageConfig(StorageConfig &$storage, IUser $user = null) {
		try {
			$credentials = $this->credentialsStore->getLoginCredentials();
		} catch (CredentialsUnavailableException $e) {
			throw new InsufficientDataForMeaningfulAnswerException('No session credentials saved');
		}

		$storage->setBackendOption('user', $credentials->getLoginName());
		$storage->setBackendOption('password', $credentials->getPassword());
	}

	public function wrapStorage(Storage $storage) {
		return new SessionStorageWrapper(['storage' => $storage]);
	}

}
