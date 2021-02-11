<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Ross Nicoll <jrn@jrn.me.uk>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\Files_External\AppInfo;

use OCA\Files_External\Config\UserPlaceholderHandler;
use OCA\Files_External\Service\DBConfigService;
use OCA\Files_External\Lib\Auth\AmazonS3\AccessKey;
use OCA\Files_External\Lib\Auth\Builtin;
use OCA\Files_External\Lib\Auth\NullMechanism;
use OCA\Files_External\Lib\Auth\OAuth1\OAuth1;
use OCA\Files_External\Lib\Auth\OAuth2\OAuth2;
use OCA\Files_External\Lib\Auth\OpenStack\OpenStackV2;
use OCA\Files_External\Lib\Auth\OpenStack\OpenStackV3;
use OCA\Files_External\Lib\Auth\OpenStack\Rackspace;
use OCA\Files_External\Lib\Auth\Password\GlobalAuth;
use OCA\Files_External\Lib\Auth\Password\LoginCredentials;
use OCA\Files_External\Lib\Auth\Password\Password;
use OCA\Files_External\Lib\Auth\Password\SessionCredentials;
use OCA\Files_External\Lib\Auth\Password\UserGlobalAuth;
use OCA\Files_External\Lib\Auth\Password\UserProvided;
use OCA\Files_External\Lib\Auth\PublicKey\RSA;
use OCA\Files_External\Lib\Auth\PublicKey\RSAPrivateKey;
use OCA\Files_External\Lib\Auth\SMB\KerberosAuth;
use OCA\Files_External\Lib\Backend\AmazonS3;
use OCA\Files_External\Lib\Backend\DAV;
use OCA\Files_External\Lib\Backend\FTP;
use OCA\Files_External\Lib\Backend\Local;
use OCA\Files_External\Lib\Backend\OwnCloud;
use OCA\Files_External\Lib\Backend\SFTP;
use OCA\Files_External\Lib\Backend\SFTP_Key;
use OCA\Files_External\Lib\Backend\SMB;
use OCA\Files_External\Lib\Backend\SMB_OC;
use OCA\Files_External\Lib\Backend\Swift;
use OCA\Files_External\Lib\Config\IAuthMechanismProvider;
use OCA\Files_External\Lib\Config\IBackendProvider;
use OCA\Files_External\Service\BackendService;
use OCP\AppFramework\App;
use OCP\IGroup;
use OCP\IUser;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * @package OCA\Files_External\AppInfo
 */
class Application extends App implements IBackendProvider, IAuthMechanismProvider {

	/**
	 * Application constructor.
	 *
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct('files_external', $urlParams);

		$container = $this->getContainer();

		/** @var BackendService $backendService */
		$backendService = $container->query(BackendService::class);
		$backendService->registerBackendProvider($this);
		$backendService->registerAuthMechanismProvider($this);
		$backendService->registerConfigHandler('user', function () use ($container) {
			return $container->query(UserPlaceholderHandler::class);
		});

		// force-load auth mechanisms since some will register hooks
		// TODO: obsolete these and use the TokenProvider to get the user's password from the session
		$this->getAuthMechanisms();
	}

	public function registerListeners() {
		$dispatcher = $this->getContainer()->getServer()->getEventDispatcher();
		$dispatcher->addListener(
			IUser::class . '::postDelete',
			function (GenericEvent $event) {
				/** @var IUser $user */
				$user = $event->getSubject();
				/** @var DBConfigService $config */
				$config = $this->getContainer()->query(DBConfigService::class);
				$config->modifyMountsOnUserDelete($user->getUID());
			}
		);
		$dispatcher->addListener(
			IGroup::class . '::postDelete',
			function (GenericEvent $event) {
				/** @var IGroup $group */
				$group = $event->getSubject();
				/** @var DBConfigService $config */
				$config = $this->getContainer()->query(DBConfigService::class);
				$config->modifyMountsOnGroupDelete($group->getGID());
			}
		);
	}

	/**
	 * @{inheritdoc}
	 */
	public function getBackends() {
		$container = $this->getContainer();

		$backends = [
			$container->query(Local::class),
			$container->query(FTP::class),
			$container->query(DAV::class),
			$container->query(OwnCloud::class),
			$container->query(SFTP::class),
			$container->query(AmazonS3::class),
			$container->query(Swift::class),
			$container->query(SFTP_Key::class),
			$container->query(SMB::class),
			$container->query(SMB_OC::class),
		];

		return $backends;
	}

	/**
	 * @{inheritdoc}
	 */
	public function getAuthMechanisms() {
		$container = $this->getContainer();

		return [
			// AuthMechanism::SCHEME_NULL mechanism
			$container->query(NullMechanism::class),

			// AuthMechanism::SCHEME_BUILTIN mechanism
			$container->query(Builtin::class),

			// AuthMechanism::SCHEME_PASSWORD mechanisms
			$container->query(Password::class),
			$container->query(SessionCredentials::class),
			$container->query(LoginCredentials::class),
			$container->query(UserProvided::class),
			$container->query(GlobalAuth::class),
			$container->query(UserGlobalAuth::class),

			// AuthMechanism::SCHEME_OAUTH1 mechanisms
			$container->query(OAuth1::class),

			// AuthMechanism::SCHEME_OAUTH2 mechanisms
			$container->query(OAuth2::class),

			// AuthMechanism::SCHEME_PUBLICKEY mechanisms
			$container->query(RSA::class),
			$container->query(RSAPrivateKey::class),

			// AuthMechanism::SCHEME_OPENSTACK mechanisms
			$container->query(OpenStackV2::class),
			$container->query(OpenStackV3::class),
			$container->query(Rackspace::class),

			// Specialized mechanisms
			$container->query(AccessKey::class),
			$container->query(KerberosAuth::class),
		];
	}
}
