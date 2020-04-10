<?php
/**
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Service\BackendService;
use OCP\IL10N;
use OCP\IUser;
use OCP\Security\ICredentialsManager;

/**
 * Global Username and Password
 */
class GlobalAuth extends AuthMechanism {

	const CREDENTIALS_IDENTIFIER = 'password::global';

	/** @var ICredentialsManager */
	protected $credentialsManager;

	public function __construct(IL10N $l, ICredentialsManager $credentialsManager) {
		$this->credentialsManager = $credentialsManager;

		$this
			->setIdentifier('password::global')
			->setVisibility(BackendService::VISIBILITY_DEFAULT)
			->setScheme(self::SCHEME_PASSWORD)
			->setText($l->t('Global credentials'));
	}

	public function getAuth($uid) {
		$auth = $this->credentialsManager->retrieve($uid, self::CREDENTIALS_IDENTIFIER);
		if (!is_array($auth)) {
			return [
				'user' => '',
				'password' => ''
			];
		} else {
			return $auth;
		}
	}

	public function saveAuth($uid, $user, $password) {
		$this->credentialsManager->store($uid, self::CREDENTIALS_IDENTIFIER, [
			'user' => $user,
			'password' => $password
		]);
	}

	public function manipulateStorageConfig(StorageConfig &$storage, IUser $user = null) {
		if ($storage->getType() === StorageConfig::MOUNT_TYPE_ADMIN) {
			$uid = '';
		} elseif (is_null($user)) {
			throw new InsufficientDataForMeaningfulAnswerException('No credentials saved');
		} else {
			$uid = $user->getUID();
		}
		$credentials = $this->credentialsManager->retrieve($uid, self::CREDENTIALS_IDENTIFIER);

		if (is_array($credentials)) {
			$storage->setBackendOption('user', $credentials['user']);
			$storage->setBackendOption('password', $credentials['password']);
		}
	}

}
