<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Philippe Jung <phil.jung@free.fr>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roger Szabo <roger.szabo@web.de>
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

namespace OCA\User_LDAP\Tests\User;

use OCA\User_LDAP\Access;
use OCA\User_LDAP\Connection;
use OCA\User_LDAP\FilesystemHelper;
use OCA\User_LDAP\ILDAPWrapper;
use OCA\User_LDAP\LogWrapper;
use OCA\User_LDAP\User\Manager;
use OCA\User_LDAP\User\User;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Image;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\IManager;

/**
 * Class Test_User_Manager
 *
 * @group DB
 *
 * @package OCA\User_LDAP\Tests\User
 */
class ManagerTest extends \Test\TestCase {
	/** @var Access|\PHPUnit\Framework\MockObject\MockObject */
	protected $access;

	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;

	/** @var FilesystemHelper|\PHPUnit\Framework\MockObject\MockObject */
	protected $fileSystemHelper;

	/** @var LogWrapper|\PHPUnit\Framework\MockObject\MockObject */
	protected $log;

	/** @var IAvatarManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $avatarManager;

	/** @var Image|\PHPUnit\Framework\MockObject\MockObject */
	protected $image;

	/** @var IDBConnection|\PHPUnit\Framework\MockObject\MockObject */
	protected $dbc;

	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $ncUserManager;

	/** @var INotificationManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $notificationManager;

	/** @var ILDAPWrapper|\PHPUnit\Framework\MockObject\MockObject */
	protected $ldapWrapper;

	/** @var Connection */
	protected $connection;

	/** @var Manager */
	protected $manager;
	/** @var IManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $shareManager;

	protected function setUp(): void {
		parent::setUp();

		$this->access = $this->createMock(Access::class);
		$this->config = $this->createMock(IConfig::class);
		$this->fileSystemHelper = $this->createMock(FilesystemHelper::class);
		$this->log = $this->createMock(LogWrapper::class);
		$this->avatarManager = $this->createMock(IAvatarManager::class);
		$this->image = $this->createMock(Image::class);
		$this->ncUserManager = $this->createMock(IUserManager::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->ldapWrapper = $this->createMock(ILDAPWrapper::class);
		$this->shareManager = $this->createMock(IManager::class);

		$this->connection = new Connection($this->ldapWrapper, '', null);

		$this->access->expects($this->any())
			->method('getConnection')
			->willReturn($this->connection);

		/** @noinspection PhpUnhandledExceptionInspection */
		$this->manager = new Manager(
			$this->config,
			$this->fileSystemHelper,
			$this->log,
			$this->avatarManager,
			$this->image,
			$this->ncUserManager,
			$this->notificationManager,
			$this->shareManager
		);

		$this->manager->setLdapAccess($this->access);
	}

	public function dnProvider() {
		return [
			['cn=foo,dc=foobar,dc=bar'],
			['uid=foo,o=foobar,c=bar'],
			['ab=cde,f=ghei,mno=pq'],
		];
	}

	/**
	 * @dataProvider dnProvider
	 */
	public function testGetByDNExisting(string $inputDN) {
		$uid = '563418fc-423b-1033-8d1c-ad5f418ee02e';

		$this->access->expects($this->once())
			->method('stringResemblesDN')
			->with($this->equalTo($inputDN))
			->willReturn(true);
		$this->access->expects($this->once())
			->method('dn2username')
			->with($this->equalTo($inputDN))
			->willReturn($uid);
		$this->access->expects($this->never())
			->method('username2dn');

		/** @noinspection PhpUnhandledExceptionInspection */
		$this->manager->get($inputDN);

		// Now we fetch the user again. If this leads to a failing test,
		// runtime caching the manager is broken.
		/** @noinspection PhpUnhandledExceptionInspection */
		$user = $this->manager->get($inputDN);

		$this->assertInstanceOf(User::class, $user);
	}

	public function testGetByDNNotExisting() {
		$inputDN = 'cn=gone,dc=foobar,dc=bar';

		$this->access->expects($this->once())
			->method('stringResemblesDN')
			->with($this->equalTo($inputDN))
			->willReturn(true);
		$this->access->expects($this->once())
			->method('dn2username')
			->with($this->equalTo($inputDN))
			->willReturn(false);
		$this->access->expects($this->once())
			->method('username2dn')
			->with($this->equalTo($inputDN))
			->willReturn(false);

		/** @noinspection PhpUnhandledExceptionInspection */
		$user = $this->manager->get($inputDN);

		$this->assertNull($user);
	}

	public function testGetByUidExisting() {
		$dn = 'cn=foo,dc=foobar,dc=bar';
		$uid = '563418fc-423b-1033-8d1c-ad5f418ee02e';

		$this->access->expects($this->never())
			->method('dn2username');
		$this->access->expects($this->once())
			->method('username2dn')
			->with($this->equalTo($uid))
			->willReturn($dn);
		$this->access->expects($this->once())
			->method('stringResemblesDN')
			->with($this->equalTo($uid))
			->willReturn(false);

		/** @noinspection PhpUnhandledExceptionInspection */
		$this->manager->get($uid);

		// Now we fetch the user again. If this leads to a failing test,
		// runtime caching the manager is broken.
		/** @noinspection PhpUnhandledExceptionInspection */
		$user = $this->manager->get($uid);

		$this->assertInstanceOf(User::class, $user);
	}

	public function testGetByUidNotExisting() {
		$uid = 'gone';

		$this->access->expects($this->never())
			->method('dn2username');
		$this->access->expects($this->exactly(1))
			->method('username2dn')
			->with($this->equalTo($uid))
			->willReturn(false);

		/** @noinspection PhpUnhandledExceptionInspection */
		$user = $this->manager->get($uid);

		$this->assertNull($user);
	}

	public function attributeRequestProvider() {
		return [
			[false],
			[true],
		];
	}

	/**
	 * @dataProvider attributeRequestProvider
	 */
	public function testGetAttributes($minimal) {
		$this->connection->setConfiguration([
			'ldapEmailAttribute' => 'MAIL',
			'ldapUserAvatarRule' => 'default',
			'ldapQuotaAttribute' => '',
			'ldapUserDisplayName2' => 'Mail',
		]);

		$attributes = $this->manager->getAttributes($minimal);

		$this->assertTrue(in_array('dn', $attributes));
		$this->assertTrue(in_array(strtolower($this->access->getConnection()->ldapEmailAttribute), $attributes));
		$this->assertTrue(!in_array($this->access->getConnection()->ldapEmailAttribute, $attributes)); #cases check
		$this->assertFalse(in_array('', $attributes));
		$this->assertSame(!$minimal, in_array('jpegphoto', $attributes));
		$this->assertSame(!$minimal, in_array('thumbnailphoto', $attributes));
		$valueCounts = array_count_values($attributes);
		$this->assertSame(1, $valueCounts['mail']);
	}
}
