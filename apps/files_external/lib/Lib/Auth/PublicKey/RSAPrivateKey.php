<?php
/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCA\Files_External\Lib\Auth\PublicKey;

use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\DefinitionParameter;
use OCA\Files_External\Lib\StorageConfig;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use phpseclib\Crypt\RSA as RSACrypt;

/**
 * RSA public key authentication
 */
class RSAPrivateKey extends AuthMechanism {

	/** @var IConfig */
	private $config;

	public function __construct(IL10N $l, IConfig $config) {
		$this->config = $config;

		$this
			->setIdentifier('publickey::rsa_private')
			->setScheme(self::SCHEME_PUBLICKEY)
			->setText($l->t('RSA private key'))
			->addParameters([
				new DefinitionParameter('user', $l->t('Username')),
				(new DefinitionParameter('password', $l->t('Password')))
					->setFlag(DefinitionParameter::FLAG_OPTIONAL)
					->setType(DefinitionParameter::VALUE_PASSWORD),
				new DefinitionParameter('private_key', $l->t('Private key')),
			]);
	}

	public function manipulateStorageConfig(StorageConfig &$storage, IUser $user = null) {
		$auth = new RSACrypt();
		$auth->setPassword($this->config->getSystemValue('secret', ''));
		if (!$auth->loadKey($storage->getBackendOption('private_key'))) {
			throw new \RuntimeException('unable to load private key');
		}
		$storage->setBackendOption('public_key_auth', $auth);
	}
}
