<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author root <root@localhost.localdomain>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
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

namespace OCA\User_LDAP\Tests\Integration;

use OCA\User_LDAP\Access;
use OCA\User_LDAP\Connection;
use OCA\User_LDAP\FilesystemHelper;
use OCA\User_LDAP\GroupPluginManager;
use OCA\User_LDAP\Helper;
use OCA\User_LDAP\LDAP;
use OCA\User_LDAP\LogWrapper;
use OCA\User_LDAP\User\Manager;
use OCA\User_LDAP\UserPluginManager;
use OCP\Share\IManager;

abstract class AbstractIntegrationTest {
	/** @var  LDAP */
	protected $ldap;

	/** @var  Connection */
	protected $connection;

	/** @var Access */
	protected $access;

	/** @var Manager */
	protected $userManager;

	/** @var Helper */
	protected $helper;

	/** @var  string */
	protected $base;

	/** @var string[] */
	protected $server;

	public function __construct($host, $port, $bind, $pwd, $base) {
		$this->base = $base;
		$this->server = [
			'host' => $host,
			'port' => $port,
			'dn' => $bind,
			'pwd' => $pwd
		];
	}

	/**
	 * prepares the LDAP environment and sets up a test configuration for
	 * the LDAP backend.
	 */
	public function init() {
		\OC::$server->registerService(UserPluginManager::class, function () {
			return new \OCA\User_LDAP\UserPluginManager();
		});
		\OC::$server->registerService(GroupPluginManager::class, function () {
			return new \OCA\User_LDAP\GroupPluginManager();
		});

		$this->initLDAPWrapper();
		$this->initConnection();
		$this->initUserManager();
		$this->initHelper();
		$this->initAccess();
	}

	/**
	 * initializes the test LDAP wrapper
	 */
	protected function initLDAPWrapper() {
		$this->ldap = new LDAP();
	}

	/**
	 * sets up the LDAP configuration to be used for the test
	 */
	protected function initConnection() {
		$this->connection = new Connection($this->ldap, '', null);
		$this->connection->setConfiguration([
			'ldapHost' => $this->server['host'],
			'ldapPort' => $this->server['port'],
			'ldapBase' => $this->base,
			'ldapAgentName' => $this->server['dn'],
			'ldapAgentPassword' => $this->server['pwd'],
			'ldapUserFilter' => 'objectclass=inetOrgPerson',
			'ldapUserDisplayName' => 'cn',
			'ldapGroupDisplayName' => 'cn',
			'ldapLoginFilter' => '(|(uid=%uid)(samaccountname=%uid))',
			'ldapCacheTTL' => 0,
			'ldapConfigurationActive' => 1,
		]);
	}

	/**
	 * initializes an LDAP user manager instance
	 */
	protected function initUserManager() {
		$this->userManager = new Manager(
			\OC::$server->getConfig(),
			new FilesystemHelper(),
			new LogWrapper(),
			\OC::$server->getAvatarManager(),
			new \OCP\Image(),
			\OC::$server->getUserManager(),
			\OC::$server->getNotificationManager(),
			\OC::$server->get(IManager::class)
		);
	}

	/**
	 * initializes the test Helper
	 */
	protected function initHelper() {
		$this->helper = new Helper(\OC::$server->getConfig(), \OC::$server->getDatabaseConnection());
	}

	/**
	 * initializes the Access test instance
	 */
	protected function initAccess() {
		$this->access = new Access($this->connection, $this->ldap, $this->userManager, $this->helper, \OC::$server->getConfig());
	}

	/**
	 * runs the test cases while outputting progress and result information
	 *
	 * If a test failed, the script is exited with return code 1.
	 */
	public function run() {
		$methods = get_class_methods($this);
		$atLeastOneCaseRan = false;
		foreach ($methods as $method) {
			if (strpos($method, 'case') === 0) {
				print("running $method " . PHP_EOL);
				try {
					if (!$this->$method()) {
						print(PHP_EOL . '>>> !!! Test ' . $method . ' FAILED !!! <<<' . PHP_EOL . PHP_EOL);
						exit(1);
					}
					$atLeastOneCaseRan = true;
				} catch (\Exception $e) {
					print(PHP_EOL . '>>> !!! Test ' . $method . ' RAISED AN EXCEPTION !!! <<<' . PHP_EOL);
					print($e->getMessage() . PHP_EOL . PHP_EOL);
					exit(1);
				}
			}
		}
		if ($atLeastOneCaseRan) {
			print('Tests succeeded' . PHP_EOL);
		} else {
			print('No Test was available.' . PHP_EOL);
			exit(1);
		}
	}
}
