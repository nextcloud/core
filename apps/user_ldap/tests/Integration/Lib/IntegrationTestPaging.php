<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\User_LDAP\Tests\Integration\Lib;

use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\Tests\Integration\AbstractIntegrationTest;
use OCA\User_LDAP\User_LDAP;
use OCA\User_LDAP\UserPluginManager;

require_once __DIR__ . '/../Bootstrap.php';

class IntegrationTestPaging extends AbstractIntegrationTest {
	/** @var  UserMapping */
	protected $mapping;

	/** @var User_LDAP */
	protected $backend;

	/** @var int */
	protected $pagingSize = 2;

	/**
	 * prepares the LDAP environment and sets up a test configuration for
	 * the LDAP backend.
	 */
	public function init() {
		require(__DIR__ . '/../setup-scripts/createExplicitUsers.php');
		parent::init();

		$this->backend = new User_LDAP($this->access, \OC::$server->getConfig(), \OC::$server->getNotificationManager(), \OC::$server->getUserSession(), \OC::$server->query(UserPluginManager::class));
	}

	public function initConnection() {
		parent::initConnection();
		$this->connection->setConfiguration([
			'ldapPagingSize' => $this->pagingSize
		]);
	}

	/**
	 * fetch first three, afterwards all users
	 *
	 * @return bool
	 */
	protected function case1() {
		$filter = 'objectclass=inetorgperson';
		$attributes = ['cn', 'dn'];

		$result = $this->access->searchUsers($filter, $attributes, 4);
		// beware, under circumstances, the result  set can be larger then
		// the specified limit! In this case, if we specify a limit of 3,
		// the result will be 4, because the highest possible paging size
		// is 2 (as configured).
		// But also with more than one search base, the limit can be outpaced.
		if (count($result) !== 4) {
			return false;
		}

		$result = $this->access->searchUsers($filter, $attributes);
		if (count($result) !== 7) {
			return false;
		}

		return true;
	}
}

/** @var string $host */
/** @var int $port */
/** @var string $adn */
/** @var string $apwd */
/** @var string $bdn */
$test = new IntegrationTestPaging($host, $port, $adn, $apwd, $bdn);
$test->init();
$test->run();
