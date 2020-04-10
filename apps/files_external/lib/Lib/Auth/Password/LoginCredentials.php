<?php
/**
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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
use OCP\IL10N;
use OCP\ISession;
use OCP\IUser;
use OCP\Security\ICredentialsManager;

/**
 * Username and password from login credentials, saved in DB
 */
class LoginCredentials extends AuthMechanism {

	const CREDENTIALS_IDENTIFIER = 'password::logincredentials/credentials';

	/** @var ISession */
	protected $session;

	/** @var ICredentialsManager */
	protected $credentialsManager;

	public function __construct(IL10N $l, ISession $session, ICredentialsManager $credentialsManager) {
		$this->session = $session;
		$this->credentialsManager = $credentialsManager;

		$this
			->setIdentifier('password::logincredentials')
			->setScheme(self::SCHEME_PASSWORD)
			->setText($l->t('Log-in credentials, save in database'))
			->addParameters([
			])
		;

		\OCP\Util::connectHook('OC_User', 'post_login', $this, 'authenticate');
	}

	/**
	 * Hook listener on post login
	 *
	 * @param array $params
	 */
	public function authenticate(array $params) {
		$userId = $params['uid'];
		$credentials = [
			'user' => $this->session->get('loginname'),
			'password' => $params['password']
		];
		$this->credentialsManager->store($userId, self::CREDENTIALS_IDENTIFIER, $credentials);
	}

	public function manipulateStorageConfig(StorageConfig &$storage, IUser $user = null) {
		if (!isset($user)) {
			throw new InsufficientDataForMeaningfulAnswerException('No login credentials saved');
		}
		$uid = $user->getUID();
		$credentials = $this->credentialsManager->retrieve($uid, self::CREDENTIALS_IDENTIFIER);

		if (!isset($credentials)) {
			throw new InsufficientDataForMeaningfulAnswerException('No login credentials saved');
		}

		$storage->setBackendOption('user', $credentials['user']);
		$storage->setBackendOption('password', $credentials['password']);
	}

}
