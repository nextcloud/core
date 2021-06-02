<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Clark Tomlinson <fallen013@gmail.com>
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

namespace OCA\Encryption\AppInfo;

use OCA\Encryption\Crypto\Crypt;
use OCA\Encryption\Crypto\DecryptAll;
use OCA\Encryption\Crypto\EncryptAll;
use OCA\Encryption\Crypto\Encryption;
use OCA\Encryption\HookManager;
use OCA\Encryption\Hooks\UserHooks;
use OCA\Encryption\KeyManager;
use OCA\Encryption\Recovery;
use OCA\Encryption\Session;
use OCA\Encryption\Users\Setup;
use OCA\Encryption\Util;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Encryption\IManager;
use OCP\IConfig;

class Application extends \OCP\AppFramework\App implements IBootstrap {

	/**
	 * @param array $urlParams
	 */
	public function __construct($urlParams = []) {
		parent::__construct('encryption', $urlParams);
	}

	public function setUp(IManager $encryptionManager) {
		if ($encryptionManager->isEnabled()) {
			/** @var Setup $setup */
			$setup = $this->getContainer()->query(Setup::class);
			$setup->setupSystem();
		}
	}

	/**
	 * register hooks
	 */
	public function registerHooks(IConfig $config) {
		if (!$config->getSystemValueBool('maintenance')) {
			$container = $this->getContainer();
			$server = $container->getServer();
			// Register our hooks and fire them.
			$hookManager = new HookManager();

			$hookManager->registerHook([
				new UserHooks($container->query(KeyManager::class),
					$server->getUserManager(),
					$server->getLogger(),
					$container->query(Setup::class),
					$server->getUserSession(),
					$container->query(Util::class),
					$container->query(Session::class),
					$container->query(Crypt::class),
					$container->query(Recovery::class))
			]);

			$hookManager->fireHooks();
		} else {
			// Logout user if we are in maintenance to force re-login
			$this->getContainer()->getServer()->getUserSession()->logout();
		}
	}

	public function registerEncryptionModule(IManager $encryptionManager) {
		$container = $this->getContainer();

		$encryptionManager->registerEncryptionModule(
			Encryption::ID,
			Encryption::DISPLAY_NAME,
			function () use ($container) {
				return new Encryption(
				$container->query(Crypt::class),
				$container->query(KeyManager::class),
				$container->query(Util::class),
				$container->query(Session::class),
				$container->query(EncryptAll::class),
				$container->query(DecryptAll::class),
				$container->getServer()->getLogger(),
				$container->getServer()->getL10N($container->getAppName())
			);
			});
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
		$encryptionManager = $context->getServerContainer()->getEncryptionManager();
		$encryptionSystemReady = $encryptionManager->isReady();

		if ($encryptionSystemReady) {
			$this->registerEncryptionModule($encryptionManager);
			$this->registerHooks(\OC::$server->getConfig());
			$this->setUp($encryptionManager);
		}
		\OCP\Util::addScript('encryption', 'encryption');
	}
}
