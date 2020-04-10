<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
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

use OC\Files\View;
use OCA\Encryption\Controller\RecoveryController;
use OCA\Encryption\Controller\SettingsController;
use OCA\Encryption\Controller\StatusController;
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
use OCP\AppFramework\IAppContainer;
use OCP\Encryption\IManager;
use OCP\IConfig;
use Symfony\Component\Console\Helper\QuestionHelper;

class Application extends \OCP\AppFramework\App {

	/** @var IManager */
	private $encryptionManager;
	/** @var IConfig */
	private $config;

	/**
	 * @param array $urlParams
	 */
	public function __construct($urlParams = []) {
		parent::__construct('encryption', $urlParams);
		$this->encryptionManager = \OC::$server->getEncryptionManager();
		$this->config = \OC::$server->getConfig();
		$this->registerServices();
	}

	public function setUp() {
		if ($this->encryptionManager->isEnabled()) {
			/** @var Setup $setup */
			$setup = $this->getContainer()->query('UserSetup');
			$setup->setupSystem();
		}
	}

	/**
	 * register hooks
	 */
	public function registerHooks() {
		if (!$this->config->getSystemValueBool('maintenance')) {

			$container = $this->getContainer();
			$server = $container->getServer();
			// Register our hooks and fire them.
			$hookManager = new HookManager();

			$hookManager->registerHook([
				new UserHooks($container->query('KeyManager'),
					$server->getUserManager(),
					$server->getLogger(),
					$container->query('UserSetup'),
					$server->getUserSession(),
					$container->query('Util'),
					$container->query('Session'),
					$container->query('Crypt'),
					$container->query('Recovery'))
			]);

			$hookManager->fireHooks();

		} else {
			// Logout user if we are in maintenance to force re-login
			$this->getContainer()->getServer()->getUserSession()->logout();
		}
	}

	public function registerEncryptionModule() {
		$container = $this->getContainer();


		$this->encryptionManager->registerEncryptionModule(
			Encryption::ID,
			Encryption::DISPLAY_NAME,
			function () use ($container) {

			return new Encryption(
				$container->query('Crypt'),
				$container->query('KeyManager'),
				$container->query('Util'),
				$container->query('Session'),
				$container->query('EncryptAll'),
				$container->query('DecryptAll'),
				$container->getServer()->getLogger(),
				$container->getServer()->getL10N($container->getAppName())
			);
		});

	}

	public function registerServices() {
		$container = $this->getContainer();

		$container->registerService('Crypt',
			function (IAppContainer $c) {
				$server = $c->getServer();
				return new Crypt($server->getLogger(),
					$server->getUserSession(),
					$server->getConfig(),
					$server->getL10N($c->getAppName()));
			});

		$container->registerService('Session',
			function (IAppContainer $c) {
				$server = $c->getServer();
				return new Session($server->getSession());
			}
		);

		$container->registerService('KeyManager',
			function (IAppContainer $c) {
				$server = $c->getServer();

				return new KeyManager($server->getEncryptionKeyStorage(),
					$c->query('Crypt'),
					$server->getConfig(),
					$server->getUserSession(),
					new Session($server->getSession()),
					$server->getLogger(),
					$c->query('Util')
				);
			});

		$container->registerService('Recovery',
			function (IAppContainer $c) {
				$server = $c->getServer();

				return new Recovery(
					$server->getUserSession(),
					$c->query('Crypt'),
					$c->query('KeyManager'),
					$server->getConfig(),
					$server->getEncryptionFilesHelper(),
					new View());
			});

		$container->registerService('RecoveryController', function (IAppContainer $c) {
			$server = $c->getServer();
			return new RecoveryController(
				$c->getAppName(),
				$server->getRequest(),
				$server->getConfig(),
				$server->getL10N($c->getAppName()),
				$c->query('Recovery'));
		});

		$container->registerService('StatusController', function (IAppContainer $c) {
			$server = $c->getServer();
			return new StatusController(
				$c->getAppName(),
				$server->getRequest(),
				$server->getL10N($c->getAppName()),
				$c->query('Session'),
				$server->getEncryptionManager()
			);
		});

		$container->registerService('SettingsController', function (IAppContainer $c) {
			$server = $c->getServer();
			return new SettingsController(
				$c->getAppName(),
				$server->getRequest(),
				$server->getL10N($c->getAppName()),
				$server->getUserManager(),
				$server->getUserSession(),
				$c->query('KeyManager'),
				$c->query('Crypt'),
				$c->query('Session'),
				$server->getSession(),
				$c->query('Util')
			);
		});

		$container->registerService('UserSetup',
			function (IAppContainer $c) {
				$server = $c->getServer();
				return new Setup($server->getLogger(),
					$server->getUserSession(),
					$c->query('Crypt'),
					$c->query('KeyManager'));
			});

		$container->registerService('Util',
			function (IAppContainer $c) {
				$server = $c->getServer();

				return new Util(
					new View(),
					$c->query('Crypt'),
					$server->getLogger(),
					$server->getUserSession(),
					$server->getConfig(),
					$server->getUserManager());
			});

		$container->registerService('EncryptAll',
			function (IAppContainer $c) {
				$server = $c->getServer();
				return new EncryptAll(
					$c->query('UserSetup'),
					$c->getServer()->getUserManager(),
					new View(),
					$c->query('KeyManager'),
					$c->query('Util'),
					$server->getConfig(),
					$server->getMailer(),
					$server->getL10N('encryption'),
					new QuestionHelper(),
					$server->getSecureRandom()
				);
			}
		);

		$container->registerService('DecryptAll',
			function (IAppContainer $c) {
				return new DecryptAll(
					$c->query('Util'),
					$c->query('KeyManager'),
					$c->query('Crypt'),
					$c->query('Session'),
					new QuestionHelper()
				);
			}
		);

	}
}
