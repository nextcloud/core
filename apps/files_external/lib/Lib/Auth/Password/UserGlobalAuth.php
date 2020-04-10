<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_External\Lib\Auth\Password;

use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Service\BackendService;
use OCP\IL10N;
use OCP\IUser;
use OCP\Security\ICredentialsManager;

/**
 * User provided Global Username and Password
 */
class UserGlobalAuth extends AuthMechanism {

	private const CREDENTIALS_IDENTIFIER = 'password::global';

	/** @var ICredentialsManager */
	protected $credentialsManager;

	public function __construct(IL10N $l, ICredentialsManager $credentialsManager) {
		$this->credentialsManager = $credentialsManager;

		$this
			->setIdentifier('password::global::user')
			->setVisibility(BackendService::VISIBILITY_DEFAULT)
			->setScheme(self::SCHEME_PASSWORD)
			->setText($l->t('Global credentials, user entered'));
	}

	public function saveBackendOptions(IUser $user, $id, $backendOptions) {
		// backendOptions are set when invoked via Files app
		// but they are not set when invoked via ext storage settings
		if(!isset($backendOptions['user']) && !isset($backendOptions['password'])) {
			return;
		}
		// make sure we're not setting any unexpected keys
		$credentials = [
			'user' => $backendOptions['user'],
			'password' => $backendOptions['password'],
		];
		$this->credentialsManager->store($user->getUID(), self::CREDENTIALS_IDENTIFIER, $credentials);
	}

	public function manipulateStorageConfig(StorageConfig &$storage, IUser $user = null) {
		if ($user === null) {
			throw new InsufficientDataForMeaningfulAnswerException('No credentials saved');
		}

		$uid = $user->getUID();
		$credentials = $this->credentialsManager->retrieve($uid, self::CREDENTIALS_IDENTIFIER);

		if (is_array($credentials)) {
			$storage->setBackendOption('user', $credentials['user']);
			$storage->setBackendOption('password', $credentials['password']);
		}
	}

}
