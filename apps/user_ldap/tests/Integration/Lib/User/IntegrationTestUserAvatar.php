<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roger Szabo <roger.szabo@web.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\User_LDAP\Tests\Integration\Lib\User;

use OCA\User_LDAP\FilesystemHelper;
use OCA\User_LDAP\LogWrapper;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\Tests\Integration\AbstractIntegrationTest;
use OCA\User_LDAP\User\Manager;
use OCA\User_LDAP\User\User;
use OCA\User_LDAP\User_LDAP;
use OCP\Image;

require_once __DIR__ . '/../../Bootstrap.php';

class IntegrationTestUserAvatar extends AbstractIntegrationTest {
	/** @var  UserMapping */
	protected $mapping;

	/**
	 * prepares the LDAP environment and sets up a test configuration for
	 * the LDAP backend.
	 */
	public function init() {
		require(__DIR__ . '/../../setup-scripts/createExplicitUsers.php');
		parent::init();
		$this->mapping = new UserMapping(\OC::$server->getDatabaseConnection());
		$this->mapping->clear();
		$this->access->setUserMapper($this->mapping);
		$userBackend  = new User_LDAP($this->access, \OC::$server->getConfig(), \OC::$server->getNotificationManager(), \OC::$server->getUserSession(), \OC::$server->query('LDAPUserPluginManager'));
		\OC_User::useBackend($userBackend);
	}

	/**
	 * A method that does the common steps of test cases 1 and 2. The evaluation
	 * is not happening here.
	 *
	 * @param string $dn
	 * @param string $username
	 * @param string $image
	 */
	private function execFetchTest($dn, $username, $image) {
		$this->setJpegPhotoAttribute($dn, $image);

		// assigns our self-picked oc username to the dn
		$this->mapping->map($dn, $username, 'fakeUUID-' . $username);

		// initialize home folder and make sure that the user will update
		// also remove an possibly existing avatar
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($username);
		\OC::$server->getUserFolder($username);
		\OC::$server->getConfig()->deleteUserValue($username, 'user_ldap', User::USER_PREFKEY_LASTREFRESH);
		if(\OC::$server->getAvatarManager()->getAvatar($username)->exists()) {
			\OC::$server->getAvatarManager()->getAvatar($username)->remove();
		}

		// finally attempt to get the avatar set
		$user = $this->userManager->get($dn);
		$user->updateAvatar();
	}

	/**
	 * tests whether an avatar can be retrieved from LDAP and stored correctly
	 *
	 * @return bool
	 */
	protected function case1() {
		$image = file_get_contents(__DIR__ . '/../../data/avatar-valid.jpg');
		$dn = 'uid=alice,ou=Users,' . $this->base;
		$username = 'alice1337';

		$this->execFetchTest($dn, $username, $image);

		return \OC::$server->getAvatarManager()->getAvatar($username)->exists();
	}

	/**
	 * tests whether an image received from LDAP which is of an invalid file
	 * type is dealt with properly (i.e. not set and not dying).
	 *
	 * @return bool
	 */
	protected function case2() {
		// gif by Pmspinner from https://commons.wikimedia.org/wiki/File:Avatar2469_3.gif
		$image = file_get_contents(__DIR__ . '/../../data/avatar-invalid.gif');
		$dn = 'uid=boris,ou=Users,' . $this->base;
		$username = 'boris7844';

		$this->execFetchTest($dn, $username, $image);

		return !\OC::$server->getAvatarManager()->getAvatar($username)->exists();
	}

	/**
	 * This writes an image to the 'jpegPhoto' attribute on LDAP.
	 *
	 * @param string $dn
	 * @param string $image An image read via file_get_contents
	 * @throws \OC\ServerNotAvailableException
	 */
	private function setJpegPhotoAttribute($dn, $image) {
		$changeSet = ['jpegphoto' => $image];
		ldap_mod_add($this->connection->getConnectionResource(), $dn, $changeSet);
	}

	protected function initUserManager() {
		$this->userManager = new Manager(
			\OC::$server->getConfig(),
			new FilesystemHelper(),
			new LogWrapper(),
			\OC::$server->getAvatarManager(),
			new Image(),
			\OC::$server->getDatabaseConnection(),
			\OC::$server->getUserManager(),
			\OC::$server->getNotificationManager()
		);
	}

	/**
	 * sets up the LDAP configuration to be used for the test
	 */
	protected function initConnection() {
		parent::initConnection();
		$this->connection->setConfiguration([
			'ldapUserFilter' => 'objectclass=inetOrgPerson',
			'ldapUserDisplayName' => 'displayName',
			'ldapGroupDisplayName' => 'cn',
			'ldapLoginFilter' => 'uid=%uid',
		]);
	}
}

/** @var string $host */
/** @var int $port */
/** @var string $adn */
/** @var string $apwd */
/** @var string $bdn */
$test = new IntegrationTestUserAvatar($host, $port, $adn, $apwd, $bdn);
$test->init();
$test->run();
