<?php
/**
 * @copyright Copyright (c) 2016 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stephan Müller <mail@stephanmueller.eu>
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

namespace OCA\ShareByMail\Tests;

use OC\CapabilitiesManager;
use OC\Mail\Message;
use OCA\ShareByMail\Settings\SettingsManager;
use OCA\ShareByMail\ShareByMailProvider;
use OCP\Defaults;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Test\TestCase;

/**
 * Class ShareByMailProviderTest
 *
 * @package OCA\ShareByMail\Tests
 * @group DB
 */
class ShareByMailProviderTest extends TestCase {

	/** @var  IDBConnection */
	private $connection;

	/** @var  IManager */
	private $shareManager;

	/** @var  IL10N | \PHPUnit_Framework_MockObject_MockObject */
	private $l;

	/** @var  ILogger | \PHPUnit_Framework_MockObject_MockObject */
	private $logger;

	/** @var  IRootFolder | \PHPUnit_Framework_MockObject_MockObject */
	private $rootFolder;

	/** @var  IUserManager | \PHPUnit_Framework_MockObject_MockObject */
	private $userManager;

	/** @var  ISecureRandom | \PHPUnit_Framework_MockObject_MockObject */
	private $secureRandom;

	/** @var  IMailer | \PHPUnit_Framework_MockObject_MockObject */
	private $mailer;

	/** @var  IURLGenerator | \PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;

	/** @var  IShare | \PHPUnit_Framework_MockObject_MockObject */
	private $share;

	/** @var  \OCP\Activity\IManager | \PHPUnit_Framework_MockObject_MockObject */
	private $activityManager;

	/** @var  SettingsManager | \PHPUnit_Framework_MockObject_MockObject */
	private $settingsManager;

	/** @var Defaults|\PHPUnit_Framework_MockObject_MockObject */
	private $defaults;

	/** @var  IHasher | \PHPUnit_Framework_MockObject_MockObject */
	private $hasher;

	/** @var  CapabilitiesManager | \PHPUnit_Framework_MockObject_MockObject */
	private $capabilitiesManager;

	protected function setUp(): void {
		parent::setUp();

		$this->shareManager = \OC::$server->getShareManager();
		$this->connection = \OC::$server->getDatabaseConnection();

		$this->l = $this->getMockBuilder(IL10N::class)->getMock();
		$this->l->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});
		$this->logger = $this->getMockBuilder(ILogger::class)->getMock();
		$this->rootFolder = $this->getMockBuilder('OCP\Files\IRootFolder')->getMock();
		$this->userManager = $this->getMockBuilder(IUserManager::class)->getMock();
		$this->secureRandom = $this->getMockBuilder('\OCP\Security\ISecureRandom')->getMock();
		$this->mailer = $this->getMockBuilder('\OCP\Mail\IMailer')->getMock();
		$this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$this->share = $this->getMockBuilder(IShare::class)->getMock();
		$this->activityManager = $this->getMockBuilder('OCP\Activity\IManager')->getMock();
		$this->settingsManager = $this->getMockBuilder(SettingsManager::class)->disableOriginalConstructor()->getMock();
		$this->defaults = $this->createMock(Defaults::class);
		$this->hasher = $this->getMockBuilder(IHasher::class)->getMock();
		$this->capabilitiesManager = $this->getMockBuilder(CapabilitiesManager::class)->disableOriginalConstructor()->getMock();

		$this->userManager->expects($this->any())->method('userExists')->willReturn(true);
	}

	/**
	 * get instance of Mocked ShareByMailProvider
	 *
	 * @param array $mockedMethods internal methods which should be mocked
	 * @return \PHPUnit_Framework_MockObject_MockObject | ShareByMailProvider
	 */
	private function getInstance(array $mockedMethods = []) {

		$instance = $this->getMockBuilder('OCA\ShareByMail\ShareByMailProvider')
			->setConstructorArgs(
				[
					$this->connection,
					$this->secureRandom,
					$this->userManager,
					$this->rootFolder,
					$this->l,
					$this->logger,
					$this->mailer,
					$this->urlGenerator,
					$this->activityManager,
					$this->settingsManager,
					$this->defaults,
					$this->hasher,
					$this->capabilitiesManager
				]
			);

		if (!empty($mockedMethods)) {
			$instance->setMethods($mockedMethods);
			return $instance->getMock();
		}

		return new ShareByMailProvider(
			$this->connection,
			$this->secureRandom,
			$this->userManager,
			$this->rootFolder,
			$this->l,
			$this->logger,
			$this->mailer,
			$this->urlGenerator,
			$this->activityManager,
			$this->settingsManager,
			$this->defaults,
			$this->hasher,
			$this->capabilitiesManager
		);

	}

	protected function tearDown(): void {
		$this->connection->getQueryBuilder()->delete('share')->execute();

		parent::tearDown();
	}

	public function testCreate() {
		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->expects($this->any())->method('getSharedWith')->willReturn('user1');

		$node = $this->getMockBuilder(File::class)->getMock();
		$node->expects($this->any())->method('getName')->willReturn('filename');

		$instance = $this->getInstance(['getSharedWith', 'createMailShare', 'getRawShare', 'createShareObject', 'createShareActivity', 'sendPassword']);

		$instance->expects($this->once())->method('getSharedWith')->willReturn([]);
		$instance->expects($this->once())->method('createMailShare')->with($share)->willReturn(42);
		$instance->expects($this->once())->method('createShareActivity')->with($share);
		$instance->expects($this->once())->method('getRawShare')->with(42)->willReturn('rawShare');
		$instance->expects($this->once())->method('createShareObject')->with('rawShare')->willReturn('shareObject');
		$instance->expects($this->any())->method('sendPassword')->willReturn(true);
		$share->expects($this->any())->method('getNode')->willReturn($node);
		$this->settingsManager->expects($this->any())->method('enforcePasswordProtection')->willReturn(false);
		$this->settingsManager->expects($this->any())->method('sendPasswordByMail')->willReturn(true);

		$this->assertSame('shareObject',
			$instance->create($share)
		);
	}

	public function testCreateSendPasswordByMailWithoutEnforcedPasswordProtection() {
		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->expects($this->any())->method('getSharedWith')->willReturn('receiver@example.com');
		$share->expects($this->any())->method('getSendPasswordByTalk')->willReturn(false);
		$share->expects($this->any())->method('getSharedBy')->willReturn('owner');

		$node = $this->getMockBuilder(File::class)->getMock();
		$node->expects($this->any())->method('getName')->willReturn('filename');

		$instance = $this->getInstance(['getSharedWith', 'createMailShare', 'getRawShare', 'createShareObject', 'createShareActivity', 'autoGeneratePassword', 'createPasswordSendActivity']);

		$instance->expects($this->once())->method('getSharedWith')->willReturn([]);
		$instance->expects($this->once())->method('createMailShare')->with($share)->willReturn(42);
		$instance->expects($this->once())->method('createShareActivity')->with($share);
		$instance->expects($this->once())->method('getRawShare')->with(42)->willReturn('rawShare');
		$instance->expects($this->once())->method('createShareObject')->with('rawShare')->willReturn('shareObject');
		$share->expects($this->any())->method('getNode')->willReturn($node);

		// The autogenerated password should not be mailed.
		$this->settingsManager->expects($this->any())->method('enforcePasswordProtection')->willReturn(false);
		$this->settingsManager->expects($this->any())->method('sendPasswordByMail')->willReturn(true);
		$instance->expects($this->never())->method('autoGeneratePassword');

		$this->mailer->expects($this->never())->method('send');

		$this->assertSame('shareObject',
			$instance->create($share)
		);
	}

	public function testCreateSendPasswordByMailWithEnforcedPasswordProtection() {
		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->expects($this->any())->method('getSharedWith')->willReturn('receiver@example.com');
		$share->expects($this->any())->method('getSendPasswordByTalk')->willReturn(false);
		$share->expects($this->any())->method('getSharedBy')->willReturn('owner');

		$node = $this->getMockBuilder(File::class)->getMock();
		$node->expects($this->any())->method('getName')->willReturn('filename');

		$instance = $this->getInstance(['getSharedWith', 'createMailShare', 'getRawShare', 'createShareObject', 'createShareActivity', 'autoGeneratePassword', 'createPasswordSendActivity']);

		$instance->expects($this->once())->method('getSharedWith')->willReturn([]);
		$instance->expects($this->once())->method('createMailShare')->with($share)->willReturn(42);
		$instance->expects($this->once())->method('createShareActivity')->with($share);
		$instance->expects($this->once())->method('getRawShare')->with(42)->willReturn('rawShare');
		$instance->expects($this->once())->method('createShareObject')->with('rawShare')->willReturn('shareObject');
		$share->expects($this->any())->method('getNode')->willReturn($node);

		// The autogenerated password should be mailed to the receiver of the share.
		$this->settingsManager->expects($this->any())->method('enforcePasswordProtection')->willReturn(true);
		$this->settingsManager->expects($this->any())->method('sendPasswordByMail')->willReturn(true);
		$instance->expects($this->once())->method('autoGeneratePassword')->with($share)->willReturn('password');

		$message = $this->createMock(IMessage::class);
		$message->expects($this->once())->method('setTo')->with(['receiver@example.com']);
		$this->mailer->expects($this->once())->method('createMessage')->willReturn($message);
		$this->mailer->expects($this->once())->method('send');

		$this->assertSame('shareObject',
			$instance->create($share)
		);
	}

	public function testCreateSendPasswordByTalkWithEnforcedPasswordProtection() {
		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->expects($this->any())->method('getSharedWith')->willReturn('receiver@example.com');
		$share->expects($this->any())->method('getSendPasswordByTalk')->willReturn(true);
		$share->expects($this->any())->method('getSharedBy')->willReturn('owner');

		$node = $this->getMockBuilder(File::class)->getMock();
		$node->expects($this->any())->method('getName')->willReturn('filename');

		$instance = $this->getInstance(['getSharedWith', 'createMailShare', 'getRawShare', 'createShareObject', 'createShareActivity', 'autoGeneratePassword', 'createPasswordSendActivity']);

		$instance->expects($this->once())->method('getSharedWith')->willReturn([]);
		$instance->expects($this->once())->method('createMailShare')->with($share)->willReturn(42);
		$instance->expects($this->once())->method('createShareActivity')->with($share);
		$instance->expects($this->once())->method('getRawShare')->with(42)->willReturn('rawShare');
		$instance->expects($this->once())->method('createShareObject')->with('rawShare')->willReturn('shareObject');
		$share->expects($this->any())->method('getNode')->willReturn($node);

		// The autogenerated password should be mailed to the owner of the share.
		$this->settingsManager->expects($this->any())->method('enforcePasswordProtection')->willReturn(true);
		$this->settingsManager->expects($this->any())->method('sendPasswordByMail')->willReturn(true);
		$instance->expects($this->once())->method('autoGeneratePassword')->with($share)->willReturn('password');

		$message = $this->createMock(IMessage::class);
		$message->expects($this->once())->method('setTo')->with(['owner@example.com' => 'Owner display name']);
		$this->mailer->expects($this->once())->method('createMessage')->willReturn($message);
		$this->mailer->expects($this->once())->method('send');

		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())->method('get')->with('owner')->willReturn($user);
		$user->expects($this->once())->method('getDisplayName')->willReturn('Owner display name');
		$user->expects($this->once())->method('getEMailAddress')->willReturn('owner@example.com');

		$this->assertSame('shareObject',
			$instance->create($share)
		);
	}


	public function testCreateFailed() {
		$this->expectException(\Exception::class);

		$this->share->expects($this->once())->method('getSharedWith')->willReturn('user1');
		$node = $this->getMockBuilder('OCP\Files\Node')->getMock();
		$node->expects($this->any())->method('getName')->willReturn('fileName');
		$this->share->expects($this->any())->method('getNode')->willReturn($node);

		$instance = $this->getInstance(['getSharedWith', 'createMailShare', 'getRawShare', 'createShareObject']);

		$instance->expects($this->once())->method('getSharedWith')->willReturn(['found']);
		$instance->expects($this->never())->method('createMailShare');
		$instance->expects($this->never())->method('getRawShare');
		$instance->expects($this->never())->method('createShareObject');

		$this->assertSame('shareObject',
			$instance->create($this->share)
		);
	}

	public function testCreateMailShare() {
		$this->share->expects($this->any())->method('getToken')->willReturn('token');
		$this->share->expects($this->once())->method('setToken')->with('token');
		$node = $this->getMockBuilder('OCP\Files\Node')->getMock();
		$node->expects($this->any())->method('getName')->willReturn('fileName');
		$this->share->expects($this->any())->method('getNode')->willReturn($node);

		$instance = $this->getInstance(['generateToken', 'addShareToDB', 'sendMailNotification']);

		$instance->expects($this->once())->method('generateToken')->willReturn('token');
		$instance->expects($this->once())->method('addShareToDB')->willReturn(42);
		$instance->expects($this->once())->method('sendMailNotification');
		$this->urlGenerator->expects($this->once())->method('linkToRouteAbsolute')
			->with('files_sharing.sharecontroller.showShare', ['token' => 'token']);
		$instance->expects($this->once())->method('sendMailNotification');

		$this->assertSame(42,
			$this->invokePrivate($instance, 'createMailShare', [$this->share])
		);

	}


	public function testCreateMailShareFailed() {
		$this->expectException(\OC\HintException::class);

		$this->share->expects($this->any())->method('getToken')->willReturn('token');
		$this->share->expects($this->once())->method('setToken')->with('token');
		$node = $this->getMockBuilder('OCP\Files\Node')->getMock();
		$node->expects($this->any())->method('getName')->willReturn('fileName');
		$this->share->expects($this->any())->method('getNode')->willReturn($node);

		$instance = $this->getInstance(['generateToken', 'addShareToDB', 'sendMailNotification']);

		$instance->expects($this->once())->method('generateToken')->willReturn('token');
		$instance->expects($this->once())->method('addShareToDB')->willReturn(42);
		$instance->expects($this->once())->method('sendMailNotification');
		$this->urlGenerator->expects($this->once())->method('linkToRouteAbsolute')
			->with('files_sharing.sharecontroller.showShare', ['token' => 'token']);
		$instance->expects($this->once())->method('sendMailNotification')
			->willReturnCallback(
				function () {
					throw new \Exception('should be converted to a hint exception');
				}
			);

		$this->assertSame(42,
			$this->invokePrivate($instance, 'createMailShare', [$this->share])
		);

	}

	public function testGenerateToken() {
		$instance = $this->getInstance();

		$this->secureRandom->expects($this->once())->method('generate')->willReturn('token');

		$this->assertSame('token',
			$this->invokePrivate($instance, 'generateToken')
		);
	}

	public function testAddShareToDB() {
		$itemSource = 11;
		$itemType = 'file';
		$shareWith = 'user@server.com';
		$sharedBy = 'user1';
		$uidOwner = 'user2';
		$permissions = 1;
		$token = 'token';
		$password = 'password';
		$sendPasswordByTalk = true;
		$hideDownload = true;


		$instance = $this->getInstance();
		$id = $this->invokePrivate(
			$instance,
			'addShareToDB',
			[
				$itemSource,
				$itemType,
				$shareWith,
				$sharedBy,
				$uidOwner,
				$permissions,
				$token,
				$password,
				$sendPasswordByTalk,
				$hideDownload
			]
		);

		$qb = $this->connection->getQueryBuilder();
		$result = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->execute()->fetchAll();

		$this->assertSame(1, count($result));

		$this->assertSame($itemSource, (int)$result[0]['item_source']);
		$this->assertSame($itemType, $result[0]['item_type']);
		$this->assertSame($shareWith, $result[0]['share_with']);
		$this->assertSame($sharedBy, $result[0]['uid_initiator']);
		$this->assertSame($uidOwner, $result[0]['uid_owner']);
		$this->assertSame($permissions, (int)$result[0]['permissions']);
		$this->assertSame($token, $result[0]['token']);
		$this->assertSame($password, $result[0]['password']);
		$this->assertSame($sendPasswordByTalk, (bool)$result[0]['password_by_talk']);
		$this->assertSame($hideDownload, (bool)$result[0]['hide_download']);

	}

	public function testUpdate() {

		$itemSource = 11;
		$itemType = 'file';
		$shareWith = 'user@server.com';
		$sharedBy = 'user1';
		$uidOwner = 'user2';
		$permissions = 1;
		$token = 'token';
		$note = 'personal note';


		$instance = $this->getInstance();

		$id = $this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, $token, $note);

		$this->share->expects($this->once())->method('getPermissions')->willReturn($permissions + 1);
		$this->share->expects($this->once())->method('getShareOwner')->willReturn($uidOwner);
		$this->share->expects($this->once())->method('getSharedBy')->willReturn($sharedBy);
		$this->share->expects($this->any())->method('getNote')->willReturn($note);
		$this->share->expects($this->atLeastOnce())->method('getId')->willReturn($id);

		$this->assertSame($this->share,
			$instance->update($this->share)
		);

		$qb = $this->connection->getQueryBuilder();
		$result = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->execute()->fetchAll();

		$this->assertSame(1, count($result));

		$this->assertSame($itemSource, (int)$result[0]['item_source']);
		$this->assertSame($itemType, $result[0]['item_type']);
		$this->assertSame($shareWith, $result[0]['share_with']);
		$this->assertSame($sharedBy, $result[0]['uid_initiator']);
		$this->assertSame($uidOwner, $result[0]['uid_owner']);
		$this->assertSame($permissions + 1, (int)$result[0]['permissions']);
		$this->assertSame($token, $result[0]['token']);
		$this->assertSame($note, $result[0]['note']);
	}

	public function dataUpdateSendPassword() {
		return [
			['password', 'hashed', 'hashed new', false, false, true],
			['', 'hashed', 'hashed new', false, false, false],
			[null, 'hashed', 'hashed new', false, false, false],
			['password', 'hashed', 'hashed', false, false, false],
			['password', 'hashed', 'hashed new', false, true, false],
			['password', 'hashed', 'hashed new', true, false, true],
			['password', 'hashed', 'hashed', true, false, true],
		];
	}

	/**
	 * @dataProvider dataUpdateSendPassword
	 *
	 * @param string|null plainTextPassword
	 * @param string originalPassword
	 * @param string newPassword
	 * @param string originalSendPasswordByTalk
	 * @param string newSendPasswordByTalk
	 * @param bool sendMail
	 */
	public function testUpdateSendPassword($plainTextPassword, string $originalPassword, string $newPassword, $originalSendPasswordByTalk, $newSendPasswordByTalk, bool $sendMail) {
		$node = $this->getMockBuilder(File::class)->getMock();
		$node->expects($this->any())->method('getName')->willReturn('filename');

		$originalShare = $this->getMockBuilder(IShare::class)->getMock();
		$originalShare->expects($this->any())->method('getSharedWith')->willReturn('receiver@example.com');
		$originalShare->expects($this->any())->method('getNode')->willReturn($node);
		$originalShare->expects($this->any())->method('getId')->willReturn(42);
		$originalShare->expects($this->any())->method('getPassword')->willReturn($originalPassword);
		$originalShare->expects($this->any())->method('getSendPasswordByTalk')->willReturn($originalSendPasswordByTalk);

		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->expects($this->any())->method('getSharedWith')->willReturn('receiver@example.com');
		$share->expects($this->any())->method('getNode')->willReturn($node);
		$share->expects($this->any())->method('getId')->willReturn(42);
		$share->expects($this->any())->method('getPassword')->willReturn($newPassword);
		$share->expects($this->any())->method('getSendPasswordByTalk')->willReturn($newSendPasswordByTalk);

		if ($sendMail) {
			$this->mailer->expects($this->once())->method('send');
		} else {
			$this->mailer->expects($this->never())->method('send');
		}

		$instance = $this->getInstance(['getShareById', 'createPasswordSendActivity']);
		$instance->expects($this->once())->method('getShareById')->willReturn($originalShare);

		$this->assertSame($share,
			$instance->update($share, $plainTextPassword)
		);
	}

	public function testDelete() {
		$instance = $this->getInstance(['removeShareFromTable', 'createShareActivity']);
		$this->share->expects($this->once())->method('getId')->willReturn(42);
		$instance->expects($this->once())->method('removeShareFromTable')->with(42);
		$instance->expects($this->once())->method('createShareActivity')->with($this->share, 'unshare');
		$instance->delete($this->share);
	}

	public function testGetShareById() {
		$instance = $this->getInstance(['createShareObject']);

		$itemSource = 11;
		$itemType = 'file';
		$shareWith = 'user@server.com';
		$sharedBy = 'user1';
		$uidOwner = 'user2';
		$permissions = 1;
		$token = 'token';

		$this->createDummyShare($itemType, $itemSource, $shareWith, "user1wrong", "user2wrong", $permissions, $token);
		$id2 = $this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, $token);

		$instance->expects($this->once())->method('createShareObject')
			->willReturnCallback(
				function ($data) use ($uidOwner, $sharedBy, $id2) {
					$this->assertSame($uidOwner, $data['uid_owner']);
					$this->assertSame($sharedBy, $data['uid_initiator']);
					$this->assertSame($id2, (int)$data['id']);
					return $this->share;
				}
			);

		$result = $instance->getShareById($id2);

		$this->assertInstanceOf('OCP\Share\IShare', $result);
	}


	public function testGetShareByIdFailed() {
		$this->expectException(\OCP\Share\Exceptions\ShareNotFound::class);

		$instance = $this->getInstance(['createShareObject']);

		$itemSource = 11;
		$itemType = 'file';
		$shareWith = 'user@server.com';
		$sharedBy = 'user1';
		$uidOwner = 'user2';
		$permissions = 1;
		$token = 'token';

		$id = $this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, $token);

		$instance->getShareById($id+1);
	}

	public function testGetShareByPath() {

		$itemSource = 11;
		$itemType = 'file';
		$shareWith = 'user@server.com';
		$sharedBy = 'user1';
		$uidOwner = 'user2';
		$permissions = 1;
		$token = 'token';

		$node = $this->getMockBuilder('OCP\Files\Node')->getMock();
		$node->expects($this->once())->method('getId')->willReturn($itemSource);


		$instance = $this->getInstance(['createShareObject']);

		$this->createDummyShare($itemType, 111, $shareWith, $sharedBy, $uidOwner, $permissions, $token);
		$id = $this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, $token);

		$instance->expects($this->once())->method('createShareObject')
			->willReturnCallback(
				function ($data) use ($uidOwner, $sharedBy, $id) {
					$this->assertSame($uidOwner, $data['uid_owner']);
					$this->assertSame($sharedBy, $data['uid_initiator']);
					$this->assertSame($id, (int)$data['id']);
					return $this->share;
				}
			);

		$result = $instance->getSharesByPath($node);

		$this->assertTrue(is_array($result));
		$this->assertSame(1, count($result));
		$this->assertInstanceOf('OCP\Share\IShare', $result[0]);
	}

	public function testGetShareByToken() {

		$itemSource = 11;
		$itemType = 'file';
		$shareWith = 'user@server.com';
		$sharedBy = 'user1';
		$uidOwner = 'user2';
		$permissions = 1;
		$token = 'token';

		$instance = $this->getInstance(['createShareObject']);

		$idMail = $this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, $token);
		$idPublic = $this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, $token, '', \OCP\Share::SHARE_TYPE_LINK);

		$this->assertTrue($idMail !== $idPublic);

		$instance->expects($this->once())->method('createShareObject')
			->willReturnCallback(
				function ($data) use ($idMail) {
					$this->assertSame($idMail, (int)$data['id']);
					return $this->share;
				}
			);

		$result = $instance->getShareByToken('token');

		$this->assertInstanceOf('OCP\Share\IShare', $result);
	}


	public function testGetShareByTokenFailed() {
		$this->expectException(\OCP\Share\Exceptions\ShareNotFound::class);


		$itemSource = 11;
		$itemType = 'file';
		$shareWith = 'user@server.com';
		$sharedBy = 'user1';
		$uidOwner = 'user2';
		$permissions = 1;
		$token = 'token';

		$instance = $this->getInstance(['createShareObject']);

		$idMail = $this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, $token);
		$idPublic = $this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, "token2", '', \OCP\Share::SHARE_TYPE_LINK);

		$this->assertTrue($idMail !== $idPublic);

		$this->assertInstanceOf('OCP\Share\IShare',
			$instance->getShareByToken('token2')
		);
	}

	public function testRemoveShareFromTable() {
		$itemSource = 11;
		$itemType = 'file';
		$shareWith = 'user@server.com';
		$sharedBy = 'user1';
		$uidOwner = 'user2';
		$permissions = 1;
		$token = 'token';

		$instance = $this->getInstance();

		$id = $this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, $token);

		$query = $this->connection->getQueryBuilder();
		$query->select('*')->from('share')
			->where($query->expr()->eq('id', $query->createNamedParameter($id)));
		$before = $query->execute()->fetchAll();

		$this->assertTrue(is_array($before));
		$this->assertSame(1, count($before));

		$this->invokePrivate($instance, 'removeShareFromTable', [$id]);

		$query = $this->connection->getQueryBuilder();
		$query->select('*')->from('share')
			->where($query->expr()->eq('id', $query->createNamedParameter($id)));
		$after = $query->execute()->fetchAll();

		$this->assertTrue(is_array($after));
		$this->assertEmpty($after);
	}

	public function testUserDeleted() {

		$itemSource = 11;
		$itemType = 'file';
		$shareWith = 'user@server.com';
		$sharedBy = 'user1';
		$uidOwner = 'user2';
		$permissions = 1;
		$token = 'token';

		$this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, $token);
		$id = $this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, 'user2Wrong', $permissions, $token);

		$query = $this->connection->getQueryBuilder();
		$query->select('*')->from('share');
		$before = $query->execute()->fetchAll();

		$this->assertTrue(is_array($before));
		$this->assertSame(2, count($before));


		$instance = $this->getInstance();

		$instance->userDeleted($uidOwner, \OCP\Share::SHARE_TYPE_EMAIL);

		$query = $this->connection->getQueryBuilder();
		$query->select('*')->from('share');
		$after = $query->execute()->fetchAll();

		$this->assertTrue(is_array($after));
		$this->assertSame(1, count($after));
		$this->assertSame($id, (int)$after[0]['id']);

	}

	public function testGetRawShare() {
		$itemSource = 11;
		$itemType = 'file';
		$shareWith = 'user@server.com';
		$sharedBy = 'user1';
		$uidOwner = 'user2';
		$permissions = 1;
		$token = 'token';

		$instance = $this->getInstance();

		$id = $this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, $token);

		$result = $this->invokePrivate($instance, 'getRawShare', [$id]);

		$this->assertTrue(is_array($result));
		$this->assertSame($itemSource, (int)$result['item_source']);
		$this->assertSame($itemType, $result['item_type']);
		$this->assertSame($shareWith, $result['share_with']);
		$this->assertSame($sharedBy, $result['uid_initiator']);
		$this->assertSame($uidOwner, $result['uid_owner']);
		$this->assertSame($permissions, (int)$result['permissions']);
		$this->assertSame($token, $result['token']);
	}


	public function testGetRawShareFailed() {
		$this->expectException(\OCP\Share\Exceptions\ShareNotFound::class);

		$itemSource = 11;
		$itemType = 'file';
		$shareWith = 'user@server.com';
		$sharedBy = 'user1';
		$uidOwner = 'user2';
		$permissions = 1;
		$token = 'token';

		$instance = $this->getInstance();

		$id = $this->createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, $token);

		$this->invokePrivate($instance, 'getRawShare', [$id+1]);
	}

	private function createDummyShare($itemType, $itemSource, $shareWith, $sharedBy, $uidOwner, $permissions, $token, $note='', $shareType = \OCP\Share::SHARE_TYPE_EMAIL) {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter($shareType))
			->setValue('item_type', $qb->createNamedParameter($itemType))
			->setValue('item_source', $qb->createNamedParameter($itemSource))
			->setValue('file_source', $qb->createNamedParameter($itemSource))
			->setValue('share_with', $qb->createNamedParameter($shareWith))
			->setValue('uid_owner', $qb->createNamedParameter($uidOwner))
			->setValue('uid_initiator', $qb->createNamedParameter($sharedBy))
			->setValue('permissions', $qb->createNamedParameter($permissions))
			->setValue('token', $qb->createNamedParameter($token))
			->setValue('note', $qb->createNamedParameter($note))
			->setValue('stime', $qb->createNamedParameter(time()));

		/*
		 * Added to fix https://github.com/owncloud/core/issues/22215
		 * Can be removed once we get rid of ajax/share.php
		 */
		$qb->setValue('file_target', $qb->createNamedParameter(''));

		$qb->execute();
		$id = $qb->getLastInsertId();

		return (int)$id;
	}

	public function testGetSharesInFolder() {
		$userManager = \OC::$server->getUserManager();
		$rootFolder = \OC::$server->getRootFolder();

		$provider = $this->getInstance(['sendMailNotification', 'createShareActivity']);

		$u1 = $userManager->createUser('testFed', md5(time()));
		$u2 = $userManager->createUser('testFed2', md5(time()));

		$folder1 = $rootFolder->getUserFolder($u1->getUID())->newFolder('foo');
		$file1 = $folder1->newFile('bar1');
		$file2 = $folder1->newFile('bar2');

		$share1 = $this->shareManager->newShare();
		$share1->setSharedWith('user@server.com')
			->setSharedBy($u1->getUID())
			->setShareOwner($u1->getUID())
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file1);
		$provider->create($share1);

		$share2 = $this->shareManager->newShare();
		$share2->setSharedWith('user@server.com')
			->setSharedBy($u2->getUID())
			->setShareOwner($u1->getUID())
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($file2);
		$provider->create($share2);

		$result = $provider->getSharesInFolder($u1->getUID(), $folder1, false);
		$this->assertCount(1, $result);
		$this->assertCount(1, $result[$file1->getId()]);

		$result = $provider->getSharesInFolder($u1->getUID(), $folder1, true);
		$this->assertCount(2, $result);
		$this->assertCount(1, $result[$file1->getId()]);
		$this->assertCount(1, $result[$file2->getId()]);

		$u1->delete();
		$u2->delete();
	}

	public function testGetAccessList() {
		$userManager = \OC::$server->getUserManager();
		$rootFolder = \OC::$server->getRootFolder();

		$provider = $this->getInstance(['sendMailNotification', 'createShareActivity']);

		$u1 = $userManager->createUser('testFed', md5(time()));
		$u2 = $userManager->createUser('testFed2', md5(time()));

		$folder = $rootFolder->getUserFolder($u1->getUID())->newFolder('foo');

		$accessList = $provider->getAccessList([$folder], true);
		$this->assertArrayHasKey('public', $accessList);
		$this->assertFalse($accessList['public']);
		$accessList = $provider->getAccessList([$folder], false);
		$this->assertArrayHasKey('public', $accessList);
		$this->assertFalse($accessList['public']);

		$share1 = $this->shareManager->newShare();
		$share1->setSharedWith('user@server.com')
			->setSharedBy($u1->getUID())
			->setShareOwner($u1->getUID())
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder);
		$share1 = $provider->create($share1);

		$share2 = $this->shareManager->newShare();
		$share2->setSharedWith('user2@server.com')
			->setSharedBy($u2->getUID())
			->setShareOwner($u1->getUID())
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setNode($folder);
		$share2 = $provider->create($share2);

		$accessList = $provider->getAccessList([$folder], true);
		$this->assertArrayHasKey('public', $accessList);
		$this->assertTrue($accessList['public']);
		$accessList = $provider->getAccessList([$folder], false);
		$this->assertArrayHasKey('public', $accessList);
		$this->assertTrue($accessList['public']);

		$provider->delete($share2);

		$accessList = $provider->getAccessList([$folder], true);
		$this->assertArrayHasKey('public', $accessList);
		$this->assertTrue($accessList['public']);
		$accessList = $provider->getAccessList([$folder], false);
		$this->assertArrayHasKey('public', $accessList);
		$this->assertTrue($accessList['public']);

		$provider->delete($share1);

		$accessList = $provider->getAccessList([$folder], true);
		$this->assertArrayHasKey('public', $accessList);
		$this->assertFalse($accessList['public']);
		$accessList = $provider->getAccessList([$folder], false);
		$this->assertArrayHasKey('public', $accessList);
		$this->assertFalse($accessList['public']);

		$u1->delete();
		$u2->delete();
	}

	public function testSendMailNotificationWithSameUserAndUserEmail() {
		$provider = $this->getInstance();
		$user = $this->createMock(IUser::class);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('OwnerUser')
			->willReturn($user);
		$user
			->expects($this->once())
			->method('getDisplayName')
			->willReturn('Mrs. Owner User');
		$message = $this->createMock(Message::class);
		$this->mailer
			->expects($this->once())
			->method('createMessage')
			->willReturn($message);
		$template = $this->createMock(IEMailTemplate::class);
		$this->mailer
			->expects($this->once())
			->method('createEMailTemplate')
			->willReturn($template);
		$template
			->expects($this->once())
			->method('addHeader');
		$template
			->expects($this->once())
			->method('addHeading')
			->with('Mrs. Owner User shared »file.txt« with you');
		$template
			->expects($this->once())
			->method('addBodyText')
			->with(
				'Mrs. Owner User shared »file.txt« with you. Click the button below to open it.',
				'Mrs. Owner User shared »file.txt« with you.'
			);
		$template
			->expects($this->once())
			->method('addBodyButton')
			->with(
				'Open »file.txt«',
				'https://example.com/file.txt'
			);
		$message
			->expects($this->once())
			->method('setTo')
			->with(['john@doe.com']);
		$this->defaults
			->expects($this->once())
			->method('getName')
			->willReturn('UnitTestCloud');
		$message
			->expects($this->once())
			->method('setFrom')
			->with([
				\OCP\Util::getDefaultEmailAddress('UnitTestCloud') => 'Mrs. Owner User via UnitTestCloud'
			]);
		$user
			->expects($this->once())
			->method('getEMailAddress')
			->willReturn('owner@example.com');
		$message
			->expects($this->once())
			->method('setReplyTo')
			->with(['owner@example.com' => 'Mrs. Owner User']);
		$this->defaults
			->expects($this->exactly(2))
			->method('getSlogan')
			->willReturn('Testing like 1990');
		$template
			->expects($this->once())
			->method('addFooter')
			->with('UnitTestCloud - Testing like 1990');
		$template
			->expects($this->once())
			->method('setSubject')
			->with('Mrs. Owner User shared »file.txt« with you');
		$message
			->expects($this->once())
			->method('useTemplate')
			->with($template);
		$this->mailer
			->expects($this->once())
			->method('send')
			->with($message);

		self::invokePrivate(
			$provider,
			'sendMailNotification',
			[
				'file.txt',
				'https://example.com/file.txt',
				'OwnerUser',
				'john@doe.com',
				null,
			]);
	}

	public function testSendMailNotificationWithDifferentUserAndNoUserEmail() {
		$provider = $this->getInstance();
		$initiatorUser = $this->createMock(IUser::class);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('InitiatorUser')
			->willReturn($initiatorUser);
		$initiatorUser
			->expects($this->once())
			->method('getDisplayName')
			->willReturn('Mr. Initiator User');
		$message = $this->createMock(Message::class);
		$this->mailer
			->expects($this->once())
			->method('createMessage')
			->willReturn($message);
		$template = $this->createMock(IEMailTemplate::class);
		$this->mailer
			->expects($this->once())
			->method('createEMailTemplate')
			->willReturn($template);
		$template
			->expects($this->once())
			->method('addHeader');
		$template
			->expects($this->once())
			->method('addHeading')
			->with('Mr. Initiator User shared »file.txt« with you');
		$template
			->expects($this->once())
			->method('addBodyText')
			->with(
				'Mr. Initiator User shared »file.txt« with you. Click the button below to open it.',
				'Mr. Initiator User shared »file.txt« with you.'
			);
		$template
			->expects($this->once())
			->method('addBodyButton')
			->with(
				'Open »file.txt«',
				'https://example.com/file.txt'
			);
		$message
			->expects($this->once())
			->method('setTo')
			->with(['john@doe.com']);
		$this->defaults
			->expects($this->once())
			->method('getName')
			->willReturn('UnitTestCloud');
		$message
			->expects($this->once())
			->method('setFrom')
			->with([
				\OCP\Util::getDefaultEmailAddress('UnitTestCloud') => 'Mr. Initiator User via UnitTestCloud'
			]);
		$message
			->expects($this->never())
			->method('setReplyTo');
		$template
			->expects($this->once())
			->method('addFooter')
			->with('');
		$template
			->expects($this->once())
			->method('setSubject')
			->with('Mr. Initiator User shared »file.txt« with you');
		$message
			->expects($this->once())
			->method('useTemplate')
			->with($template);
		$this->mailer
			->expects($this->once())
			->method('send')
			->with($message);

		self::invokePrivate(
			$provider,
			'sendMailNotification',
			[
				'file.txt',
				'https://example.com/file.txt',
				'InitiatorUser',
				'john@doe.com',
				null,
			]);
	}
}
