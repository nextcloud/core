<?php
/**
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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
use OCA\Files_External\Lib\Auth\IUserProvided;
use OCA\Files_External\Lib\DefinitionParameter;
use OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Service\BackendService;
use OCP\IL10N;
use OCP\IUser;
use OCP\Security\ICredentialsManager;

/**
 * User provided Username and Password
 */
class UserProvided extends AuthMechanism implements IUserProvided {
	public const CREDENTIALS_IDENTIFIER_PREFIX = 'password::userprovided/';

	/** @var ICredentialsManager */
	protected $credentialsManager;

	public function __construct(IL10N $l, ICredentialsManager $credentialsManager) {
		$this->credentialsManager = $credentialsManager;

		$this
			->setIdentifier('password::userprovided')
			->setVisibility(BackendService::VISIBILITY_ADMIN)
			->setScheme(self::SCHEME_PASSWORD)
			->setText($l->t('User entered, store in database'))
			->addParameters([
				(new DefinitionParameter('user', $l->t('Username')))
					->setFlag(DefinitionParameter::FLAG_USER_PROVIDED),
				(new DefinitionParameter('password', $l->t('Password')))
					->setType(DefinitionParameter::VALUE_PASSWORD)
					->setFlag(DefinitionParameter::FLAG_USER_PROVIDED),
			]);
	}

	private function getCredentialsIdentifier($storageId) {
		return self::CREDENTIALS_IDENTIFIER_PREFIX . $storageId;
	}

	public function saveBackendOptions(IUser $user, $mountId, array $options) {
		$this->credentialsManager->store($user->getUID(), $this->getCredentialsIdentifier($mountId), [
			'user' => $options['user'], // explicitly copy the fields we want instead of just passing the entire $options array
			'password' => $options['password'] // this way we prevent users from being able to modify any other field
		]);
	}

	public function manipulateStorageConfig(StorageConfig &$storage, IUser $user = null) {
		if (!isset($user)) {
			throw new InsufficientDataForMeaningfulAnswerException('No credentials saved');
		}
		$uid = $user->getUID();
		$credentials = $this->credentialsManager->retrieve($uid, $this->getCredentialsIdentifier($storage->getId()));

		if (!isset($credentials)) {
			throw new InsufficientDataForMeaningfulAnswerException('No credentials saved');
		}

		$storage->setBackendOption('user', $credentials['user']);
		$storage->setBackendOption('password', $credentials['password']);
	}
}
