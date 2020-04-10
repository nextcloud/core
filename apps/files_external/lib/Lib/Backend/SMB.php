<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCA\Files_External\Lib\Backend;

use Icewind\SMB\BasicAuth;
use Icewind\SMB\KerberosAuth;
use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\Auth\Password\Password;
use OCA\Files_External\Lib\DefinitionParameter;
use OCA\Files_External\Lib\LegacyDependencyCheckPolyfill;
use OCA\Files_External\Lib\StorageConfig;

use OCP\IL10N;
use OCP\IUser;

class SMB extends Backend {

	use LegacyDependencyCheckPolyfill;

	public function __construct(IL10N $l, Password $legacyAuth) {
		$this
			->setIdentifier('smb')
			->addIdentifierAlias('\OC\Files\Storage\SMB')// legacy compat
			->setStorageClass('\OCA\Files_External\Lib\Storage\SMB')
			->setText($l->t('SMB / CIFS'))
			->addParameters([
				new DefinitionParameter('host', $l->t('Host')),
				new DefinitionParameter('share', $l->t('Share')),
				(new DefinitionParameter('root', $l->t('Remote subfolder')))
					->setFlag(DefinitionParameter::FLAG_OPTIONAL),
				(new DefinitionParameter('domain', $l->t('Domain')))
					->setFlag(DefinitionParameter::FLAG_OPTIONAL),
				(new DefinitionParameter('show_hidden', $l->t('Show hidden files')))
					->setType(DefinitionParameter::VALUE_BOOLEAN)
					->setFlag(DefinitionParameter::FLAG_OPTIONAL),
				(new DefinitionParameter('timeout', $l->t('Timeout')))
					->setType(DefinitionParameter::VALUE_HIDDEN)
					->setFlag(DefinitionParameter::FLAG_OPTIONAL),
			])
			->addAuthScheme(AuthMechanism::SCHEME_PASSWORD)
			->addAuthScheme(AuthMechanism::SCHEME_SMB)
			->setLegacyAuthMechanism($legacyAuth);
	}

	/**
	 * @param StorageConfig $storage
	 * @param IUser $user
	 */
	public function manipulateStorageConfig(StorageConfig &$storage, IUser $user = null) {
		$auth = $storage->getAuthMechanism();
		if ($auth->getScheme() === AuthMechanism::SCHEME_PASSWORD) {
			$smbAuth = new BasicAuth(
				$storage->getBackendOption('user'),
				$storage->getBackendOption('domain'),
				$storage->getBackendOption('password')
			);
		} else {
			switch ($auth->getIdentifier()) {
				case 'smb::kerberos':
					$smbAuth = new KerberosAuth();
					break;
				default:
					throw new \InvalidArgumentException('unknown authentication backend');
			}
		}

		$storage->setBackendOption('auth', $smbAuth);
	}

}
