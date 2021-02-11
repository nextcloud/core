<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_Sharing\Tests\Controllers;

use OC\Files\Filesystem;
use OC\Files\Node\Folder;
use OCA\FederatedFileSharing\FederatedShareProvider;
use OCA\Files_Sharing\Controller\ShareController;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Template\ExternalShareMenuAction;
use OCP\AppFramework\Http\Template\LinkMenuAction;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\Template\SimpleMenuAction;
use OCP\Constants;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\NotFoundException;
use OCP\Files\Storage;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IPreview;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use OCP\Activity\IManager;
use OCP\Files\IRootFolder;
use OCP\Defaults;
use OC\Share20\Manager;

/**
 * @group DB
 *
 * @package OCA\Files_Sharing\Controllers
 */
class ShareControllerTest extends \Test\TestCase {

	/** @var string */
	private $user;
	/** @var string */
	private $oldUser;

	/** @var string */
	private $appName = 'files_sharing';
	/** @var ShareController */
	private $shareController;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var ISession|MockObject */
	private $session;
	/** @var \OCP\IPreview|MockObject */
	private $previewManager;
	/** @var \OCP\IConfig|MockObject */
	private $config;
	/** @var  \OC\Share20\Manager|MockObject */
	private $shareManager;
	/** @var IUserManager|MockObject */
	private $userManager;
	/** @var  FederatedShareProvider|MockObject */
	private $federatedShareProvider;
	/** @var IAccountManager|MockObject */
	private $accountManager;
	/** @var IEventDispatcher|MockObject */
	private $eventDispatcher;
	/** @var IL10N */
	private $l10n;

	protected function setUp(): void {
		parent::setUp();
		$this->appName = 'files_sharing';

		$this->shareManager = $this->createMock(Manager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->session = $this->createMock(ISession::class);
		$this->previewManager = $this->createMock(IPreview::class);
		$this->config = $this->createMock(IConfig::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->federatedShareProvider = $this->createMock(FederatedShareProvider::class);
		$this->federatedShareProvider->expects($this->any())
			->method('isOutgoingServer2serverShareEnabled')->willReturn(true);
		$this->federatedShareProvider->expects($this->any())
			->method('isIncomingServer2serverShareEnabled')->willReturn(true);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->shareController = new \OCA\Files_Sharing\Controller\ShareController(
			$this->appName,
			$this->createMock(IRequest::class),
			$this->config,
			$this->urlGenerator,
			$this->userManager,
			$this->createMock(ILogger::class),
			$this->createMock(IManager::class),
			$this->shareManager,
			$this->session,
			$this->previewManager,
			$this->createMock(IRootFolder::class),
			$this->federatedShareProvider,
			$this->accountManager,
			$this->eventDispatcher,
			$this->l10n,
			$this->createMock(Defaults::class)
		);


		// Store current user
		$this->oldUser = \OC_User::getUser();

		// Create a dummy user
		$this->user = \OC::$server->getSecureRandom()->generate(12, ISecureRandom::CHAR_LOWER);

		\OC::$server->getUserManager()->createUser($this->user, $this->user);
		\OC_Util::tearDownFS();
		$this->loginAsUser($this->user);
	}

	protected function tearDown(): void {
		\OC_Util::tearDownFS();
		\OC_User::setUserId('');
		Filesystem::tearDown();
		$user = \OC::$server->getUserManager()->get($this->user);
		if ($user !== null) {
			$user->delete();
		}
		\OC_User::setIncognitoMode(false);

		\OC::$server->getSession()->set('public_link_authenticated', '');

		// Set old user
		\OC_User::setUserId($this->oldUser);
		\OC_Util::setupFS($this->oldUser);
		parent::tearDown();
	}

	public function testShowShareInvalidToken() {
		$this->shareController->setToken('invalidtoken');

		$this->shareManager
			->expects($this->once())
			->method('getShareByToken')
			->with('invalidtoken')
			->will($this->throwException(new ShareNotFound()));

		$this->expectException(NotFoundException::class);

		// Test without a not existing token
		$this->shareController->showShare();
	}

	public function testShowShareNotAuthenticated() {
		$this->shareController->setToken('validtoken');

		$share = \OC::$server->getShareManager()->newShare();
		$share->setPassword('password');

		$this->shareManager
			->expects($this->once())
			->method('getShareByToken')
			->with('validtoken')
			->willReturn($share);

		$this->expectException(NotFoundException::class);

		// Test without a not existing token
		$this->shareController->showShare();
	}


	public function testShowShare() {
		$note = 'personal note';
		$filename = 'file1.txt';

		$this->shareController->setToken('token');

		$owner = $this->createMock(IUser::class);
		$owner->method('getDisplayName')->willReturn('ownerDisplay');
		$owner->method('getUID')->willReturn('ownerUID');
		$owner->method('isEnabled')->willReturn(true);

		$initiator = $this->createMock(IUser::class);
		$initiator->method('getDisplayName')->willReturn('initiatorDisplay');
		$initiator->method('getUID')->willReturn('initiatorUID');
		$initiator->method('isEnabled')->willReturn(true);

		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn($filename);
		$file->method('getMimetype')->willReturn('text/plain');
		$file->method('getSize')->willReturn(33);
		$file->method('isReadable')->willReturn(true);
		$file->method('isShareable')->willReturn(true);

		$accountName = $this->createMock(IAccountProperty::class);
		$accountName->method('getScope')
			->willReturn(IAccountManager::VISIBILITY_PUBLIC);
		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')
			->with(IAccountManager::PROPERTY_DISPLAYNAME)
			->willReturn($accountName);
		$this->accountManager->expects($this->once())
			->method('getAccount')
			->with($owner)
			->willReturn($account);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setId(42);
		$share->setPassword('password')
			->setShareOwner('ownerUID')
			->setSharedBy('initiatorUID')
			->setNode($file)
			->setNote($note)
			->setTarget("/$filename");

		$this->session->method('exists')->with('public_link_authenticated')->willReturn(true);
		$this->session->method('get')->with('public_link_authenticated')->willReturn('42');

		$this->urlGenerator->expects($this->at(0))
			->method('linkToRouteAbsolute')
			->with('files_sharing.sharecontroller.downloadShare', ['token' => 'token', 'filename' => $filename])
			->willReturn('downloadURL');

		$this->previewManager->method('isMimeSupported')->with('text/plain')->willReturn(true);

		$this->config->method('getSystemValue')
			->willReturnMap(
				[
					['max_filesize_animated_gifs_public_sharing', 10, 10],
					['enable_previews', true, true],
					['preview_max_x', 1024, 1024],
					['preview_max_y', 1024, 1024],
				]
			);
		$shareTmpl['maxSizeAnimateGif'] = $this->config->getSystemValue('max_filesize_animated_gifs_public_sharing', 10);
		$shareTmpl['previewEnabled'] = $this->config->getSystemValue('enable_previews', true);

		$this->shareManager
			->expects($this->once())
			->method('getShareByToken')
			->with('token')
			->willReturn($share);
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('core', 'shareapi_public_link_disclaimertext', null)
			->willReturn('My disclaimer text');

		$this->userManager->method('get')->willReturnCallback(function (string $uid) use ($owner, $initiator) {
			if ($uid === 'ownerUID') {
				return $owner;
			}
			if ($uid === 'initiatorUID') {
				return $initiator;
			}
			return null;
		});

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with(
				$this->callback(function ($event) use ($share) {
					return $event->getShare() === $share;
				})
			);

		$this->l10n->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters) {
				return vsprintf($text, $parameters);
			});

		$response = $this->shareController->showShare();
		$sharedTmplParams = [
			'owner' => 'ownerUID',
			'filename' => $filename,
			'directory_path' => "/$filename",
			'mimetype' => 'text/plain',
			'dirToken' => 'token',
			'sharingToken' => 'token',
			'server2serversharing' => true,
			'protected' => 'true',
			'dir' => '',
			'downloadURL' => 'downloadURL',
			'fileSize' => '33 B',
			'nonHumanFileSize' => 33,
			'maxSizeAnimateGif' => 10,
			'previewSupported' => true,
			'previewEnabled' => true,
			'previewMaxX' => 1024,
			'previewMaxY' => 1024,
			'hideFileList' => false,
			'shareOwner' => 'ownerDisplay',
			'disclaimer' => 'My disclaimer text',
			'shareUrl' => null,
			'previewImage' => null,
			'previewURL' => 'downloadURL',
			'note' => $note,
			'hideDownload' => false,
			'showgridview' => false
		];

		$csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
		$csp->addAllowedFrameDomain('\'self\'');
		$expectedResponse = new PublicTemplateResponse($this->appName, 'public', $sharedTmplParams);
		$expectedResponse->setContentSecurityPolicy($csp);
		$expectedResponse->setHeaderTitle($sharedTmplParams['filename']);
		$expectedResponse->setHeaderDetails('shared by ' . $sharedTmplParams['shareOwner']);
		$expectedResponse->setHeaderActions([
			new SimpleMenuAction('download', $this->l10n->t('Download'), 'icon-download-white', $sharedTmplParams['downloadURL'], 0),
			new SimpleMenuAction('download', $this->l10n->t('Download'), 'icon-download', $sharedTmplParams['downloadURL'], 10, $sharedTmplParams['fileSize']),
			new LinkMenuAction($this->l10n->t('Direct link'), 'icon-public', $sharedTmplParams['previewURL']),
			new ExternalShareMenuAction($this->l10n->t('Add to your Nextcloud'), 'icon-external', $sharedTmplParams['owner'], $sharedTmplParams['shareOwner'], $sharedTmplParams['filename']),
		]);

		$this->assertEquals($expectedResponse, $response);
	}

	public function testShowShareWithPrivateName() {
		$note = 'personal note';
		$filename = 'file1.txt';

		$this->shareController->setToken('token');

		$owner = $this->createMock(IUser::class);
		$owner->method('getDisplayName')->willReturn('ownerDisplay');
		$owner->method('getUID')->willReturn('ownerUID');
		$owner->method('isEnabled')->willReturn(true);

		$initiator = $this->createMock(IUser::class);
		$initiator->method('getDisplayName')->willReturn('initiatorDisplay');
		$initiator->method('getUID')->willReturn('initiatorUID');
		$initiator->method('isEnabled')->willReturn(true);

		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn($filename);
		$file->method('getMimetype')->willReturn('text/plain');
		$file->method('getSize')->willReturn(33);
		$file->method('isReadable')->willReturn(true);
		$file->method('isShareable')->willReturn(true);

		$accountName = $this->createMock(IAccountProperty::class);
		$accountName->method('getScope')
			->willReturn(IAccountManager::VISIBILITY_PRIVATE);
		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')
			->with(IAccountManager::PROPERTY_DISPLAYNAME)
			->willReturn($accountName);
		$this->accountManager->expects($this->once())
			->method('getAccount')
			->with($owner)
			->willReturn($account);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setId(42);
		$share->setPassword('password')
			->setShareOwner('ownerUID')
			->setSharedBy('initiatorUID')
			->setNode($file)
			->setNote($note)
			->setTarget("/$filename");

		$this->session->method('exists')->with('public_link_authenticated')->willReturn(true);
		$this->session->method('get')->with('public_link_authenticated')->willReturn('42');

		$this->urlGenerator->expects($this->at(0))
			->method('linkToRouteAbsolute')
			->with('files_sharing.sharecontroller.downloadShare', ['token' => 'token', 'filename' => $filename])
			->willReturn('downloadURL');

		$this->previewManager->method('isMimeSupported')->with('text/plain')->willReturn(true);

		$this->config->method('getSystemValue')
			->willReturnMap(
				[
					['max_filesize_animated_gifs_public_sharing', 10, 10],
					['enable_previews', true, true],
					['preview_max_x', 1024, 1024],
					['preview_max_y', 1024, 1024],
				]
			);
		$shareTmpl['maxSizeAnimateGif'] = $this->config->getSystemValue('max_filesize_animated_gifs_public_sharing', 10);
		$shareTmpl['previewEnabled'] = $this->config->getSystemValue('enable_previews', true);

		$this->shareManager
			->expects($this->once())
			->method('getShareByToken')
			->with('token')
			->willReturn($share);
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('core', 'shareapi_public_link_disclaimertext', null)
			->willReturn('My disclaimer text');

		$this->userManager->method('get')->willReturnCallback(function (string $uid) use ($owner, $initiator) {
			if ($uid === 'ownerUID') {
				return $owner;
			}
			if ($uid === 'initiatorUID') {
				return $initiator;
			}
			return null;
		});

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with(
				$this->callback(function ($event) use ($share) {
					return $event->getShare() === $share;
				})
			);

		$this->l10n->expects($this->any())
			->method('t')
			->will($this->returnCallback(function ($text, $parameters) {
				return vsprintf($text, $parameters);
			}));

		$response = $this->shareController->showShare();
		$sharedTmplParams = [
			'owner' => '',
			'filename' => $filename,
			'directory_path' => "/$filename",
			'mimetype' => 'text/plain',
			'dirToken' => 'token',
			'sharingToken' => 'token',
			'server2serversharing' => true,
			'protected' => 'true',
			'dir' => '',
			'downloadURL' => 'downloadURL',
			'fileSize' => '33 B',
			'nonHumanFileSize' => 33,
			'maxSizeAnimateGif' => 10,
			'previewSupported' => true,
			'previewEnabled' => true,
			'previewMaxX' => 1024,
			'previewMaxY' => 1024,
			'hideFileList' => false,
			'shareOwner' => '',
			'disclaimer' => 'My disclaimer text',
			'shareUrl' => null,
			'previewImage' => null,
			'previewURL' => 'downloadURL',
			'note' => $note,
			'hideDownload' => false,
			'showgridview' => false
		];

		$csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
		$csp->addAllowedFrameDomain('\'self\'');
		$expectedResponse = new PublicTemplateResponse($this->appName, 'public', $sharedTmplParams);
		$expectedResponse->setContentSecurityPolicy($csp);
		$expectedResponse->setHeaderTitle($sharedTmplParams['filename']);
		$expectedResponse->setHeaderDetails('');
		$expectedResponse->setHeaderActions([
			new SimpleMenuAction('download', $this->l10n->t('Download'), 'icon-download-white', $sharedTmplParams['downloadURL'], 0),
			new SimpleMenuAction('download', $this->l10n->t('Download'), 'icon-download', $sharedTmplParams['downloadURL'], 10, $sharedTmplParams['fileSize']),
			new LinkMenuAction($this->l10n->t('Direct link'), 'icon-public', $sharedTmplParams['previewURL']),
			new ExternalShareMenuAction($this->l10n->t('Add to your Nextcloud'), 'icon-external', $sharedTmplParams['owner'], $sharedTmplParams['shareOwner'], $sharedTmplParams['filename']),
		]);

		$this->assertEquals($expectedResponse, $response);
	}

	public function testShowShareHideDownload() {
		$note = 'personal note';
		$filename = 'file1.txt';

		$this->shareController->setToken('token');

		$owner = $this->getMockBuilder(IUser::class)->getMock();
		$owner->method('getDisplayName')->willReturn('ownerDisplay');
		$owner->method('getUID')->willReturn('ownerUID');
		$owner->method('isEnabled')->willReturn(true);

		$initiator = $this->createMock(IUser::class);
		$initiator->method('getDisplayName')->willReturn('initiatorDisplay');
		$initiator->method('getUID')->willReturn('initiatorUID');
		$initiator->method('isEnabled')->willReturn(true);

		$file = $this->getMockBuilder('OCP\Files\File')->getMock();
		$file->method('getName')->willReturn($filename);
		$file->method('getMimetype')->willReturn('text/plain');
		$file->method('getSize')->willReturn(33);
		$file->method('isReadable')->willReturn(true);
		$file->method('isShareable')->willReturn(true);

		$accountName = $this->createMock(IAccountProperty::class);
		$accountName->method('getScope')
			->willReturn(IAccountManager::VISIBILITY_PUBLIC);
		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')
			->with(IAccountManager::PROPERTY_DISPLAYNAME)
			->willReturn($accountName);
		$this->accountManager->expects($this->once())
			->method('getAccount')
			->with($owner)
			->willReturn($account);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setId(42);
		$share->setPassword('password')
			->setShareOwner('ownerUID')
			->setSharedBy('initiatorUID')
			->setNode($file)
			->setNote($note)
			->setTarget("/$filename")
			->setHideDownload(true);

		$this->session->method('exists')->with('public_link_authenticated')->willReturn(true);
		$this->session->method('get')->with('public_link_authenticated')->willReturn('42');

		// Even if downloads are disabled the "downloadURL" parameter is
		// provided to the template, as it is needed to preview audio and GIF
		// files.
		$this->urlGenerator->expects($this->at(0))
			->method('linkToRouteAbsolute')
			->with('files_sharing.sharecontroller.downloadShare', ['token' => 'token', 'filename' => $filename])
			->willReturn('downloadURL');

		$this->previewManager->method('isMimeSupported')->with('text/plain')->willReturn(true);

		$this->config->method('getSystemValue')
			->willReturnMap(
				[
					['max_filesize_animated_gifs_public_sharing', 10, 10],
					['enable_previews', true, true],
					['preview_max_x', 1024, 1024],
					['preview_max_y', 1024, 1024],
				]
			);
		$shareTmpl['maxSizeAnimateGif'] = $this->config->getSystemValue('max_filesize_animated_gifs_public_sharing', 10);
		$shareTmpl['previewEnabled'] = $this->config->getSystemValue('enable_previews', true);

		$this->shareManager
			->expects($this->once())
			->method('getShareByToken')
			->with('token')
			->willReturn($share);
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('core', 'shareapi_public_link_disclaimertext', null)
			->willReturn('My disclaimer text');

		$this->userManager->method('get')->willReturnCallback(function (string $uid) use ($owner, $initiator) {
			if ($uid === 'ownerUID') {
				return $owner;
			}
			if ($uid === 'initiatorUID') {
				return $initiator;
			}
			return null;
		});

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with(
				$this->callback(function ($event) use ($share) {
					return $event->getShare() === $share;
				})
			);

		$this->l10n->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters) {
				return vsprintf($text, $parameters);
			});

		$response = $this->shareController->showShare();
		$sharedTmplParams = [
			'owner' => 'ownerUID',
			'filename' => $filename,
			'directory_path' => "/$filename",
			'mimetype' => 'text/plain',
			'dirToken' => 'token',
			'sharingToken' => 'token',
			'server2serversharing' => true,
			'protected' => 'true',
			'dir' => '',
			'downloadURL' => 'downloadURL',
			'fileSize' => '33 B',
			'nonHumanFileSize' => 33,
			'maxSizeAnimateGif' => 10,
			'previewSupported' => true,
			'previewEnabled' => true,
			'previewMaxX' => 1024,
			'previewMaxY' => 1024,
			'hideFileList' => false,
			'shareOwner' => 'ownerDisplay',
			'disclaimer' => 'My disclaimer text',
			'shareUrl' => null,
			'previewImage' => null,
			'previewURL' => 'downloadURL',
			'note' => $note,
			'hideDownload' => true,
			'showgridview' => false
		];

		$csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
		$csp->addAllowedFrameDomain('\'self\'');
		$expectedResponse = new PublicTemplateResponse($this->appName, 'public', $sharedTmplParams);
		$expectedResponse->setContentSecurityPolicy($csp);
		$expectedResponse->setHeaderTitle($sharedTmplParams['filename']);
		$expectedResponse->setHeaderDetails('shared by ' . $sharedTmplParams['shareOwner']);
		$expectedResponse->setHeaderActions([]);

		$this->assertEquals($expectedResponse, $response);
	}

	/**
	 * Checks file drop shares:
	 * - there must not be any header action
	 * - the template param "hideFileList" should be true
	 *
	 * @test
	 * @return void
	 */
	public function testShareFileDrop() {
		$this->shareController->setToken('token');

		$owner = $this->getMockBuilder(IUser::class)->getMock();
		$owner->method('getDisplayName')->willReturn('ownerDisplay');
		$owner->method('getUID')->willReturn('ownerUID');
		$owner->method('isEnabled')->willReturn(true);

		$initiator = $this->createMock(IUser::class);
		$initiator->method('getDisplayName')->willReturn('initiatorDisplay');
		$initiator->method('getUID')->willReturn('initiatorUID');
		$initiator->method('isEnabled')->willReturn(true);

		/* @var MockObject|Storage $storage */
		$storage = $this->getMockBuilder(Storage::class)
			->disableOriginalConstructor()
			->getMock();

		/* @var MockObject|Folder $folder */
		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$folder->method('getName')->willReturn('/fileDrop');
		$folder->method('isReadable')->willReturn(true);
		$folder->method('isShareable')->willReturn(true);
		$folder->method('getStorage')->willReturn($storage);
		$folder->method('get')->with('')->willReturn($folder);
		$folder->method('getSize')->willReturn(1337);

		$accountName = $this->createMock(IAccountProperty::class);
		$accountName->method('getScope')
			->willReturn(IAccountManager::VISIBILITY_PUBLIC);
		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')
			->with(IAccountManager::PROPERTY_DISPLAYNAME)
			->willReturn($accountName);
		$this->accountManager->expects($this->once())
			->method('getAccount')
			->with($owner)
			->willReturn($account);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setId(42);
		$share->setPermissions(Constants::PERMISSION_CREATE)
			->setShareOwner('ownerUID')
			->setSharedBy('initiatorUID')
			->setNode($folder)
			->setTarget('/fileDrop');

		$this->shareManager
			->expects($this->once())
			->method('getShareByToken')
			->with('token')
			->willReturn($share);

		$this->userManager->method('get')->willReturnCallback(function (string $uid) use ($owner, $initiator) {
			if ($uid === 'ownerUID') {
				return $owner;
			}
			if ($uid === 'initiatorUID') {
				return $initiator;
			}
			return null;
		});

		$this->l10n->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters) {
				return vsprintf($text, $parameters);
			});

		$response = $this->shareController->showShare();
		// skip the "folder" param for tests
		$responseParams = $response->getParams();
		unset($responseParams['folder']);
		$response->setParams($responseParams);

		$sharedTmplParams = [
			'owner' => 'ownerUID',
			'filename' => '/fileDrop',
			'directory_path' => '/fileDrop',
			'mimetype' => null,
			'dirToken' => 'token',
			'sharingToken' => 'token',
			'server2serversharing' => true,
			'protected' => 'false',
			'dir' => null,
			'downloadURL' => '',
			'fileSize' => '1 KB',
			'nonHumanFileSize' => 1337,
			'maxSizeAnimateGif' => null,
			'previewSupported' => null,
			'previewEnabled' => null,
			'previewMaxX' => null,
			'previewMaxY' => null,
			'hideFileList' => true,
			'shareOwner' => 'ownerDisplay',
			'disclaimer' => null,
			'shareUrl' => '',
			'previewImage' => '',
			'previewURL' => '',
			'note' => '',
			'hideDownload' => false,
			'showgridview' => false
		];

		$csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
		$csp->addAllowedFrameDomain('\'self\'');
		$expectedResponse = new PublicTemplateResponse($this->appName, 'public', $sharedTmplParams);
		$expectedResponse->setContentSecurityPolicy($csp);
		$expectedResponse->setHeaderTitle($sharedTmplParams['filename']);
		$expectedResponse->setHeaderDetails('shared by ' . $sharedTmplParams['shareOwner']);

		self::assertEquals($expectedResponse, $response);
	}


	public function testShowShareInvalid() {
		$this->expectException(\OCP\Files\NotFoundException::class);

		$filename = 'file1.txt';
		$this->shareController->setToken('token');

		$owner = $this->getMockBuilder(IUser::class)->getMock();
		$owner->method('getDisplayName')->willReturn('ownerDisplay');
		$owner->method('getUID')->willReturn('ownerUID');

		$file = $this->getMockBuilder('OCP\Files\File')->getMock();
		$file->method('getName')->willReturn($filename);
		$file->method('getMimetype')->willReturn('text/plain');
		$file->method('getSize')->willReturn(33);
		$file->method('isShareable')->willReturn(false);
		$file->method('isReadable')->willReturn(true);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setId(42);
		$share->setPassword('password')
			->setShareOwner('ownerUID')
			->setNode($file)
			->setTarget("/$filename");

		$this->session->method('exists')->with('public_link_authenticated')->willReturn(true);
		$this->session->method('get')->with('public_link_authenticated')->willReturn('42');

		$this->previewManager->method('isMimeSupported')->with('text/plain')->willReturn(true);

		$this->config->method('getSystemValue')
			->willReturnMap(
				[
					['max_filesize_animated_gifs_public_sharing', 10, 10],
					['enable_previews', true, true],
				]
			);
		$shareTmpl['maxSizeAnimateGif'] = $this->config->getSystemValue('max_filesize_animated_gifs_public_sharing', 10);
		$shareTmpl['previewEnabled'] = $this->config->getSystemValue('enable_previews', true);

		$this->shareManager
			->expects($this->once())
			->method('getShareByToken')
			->with('token')
			->willReturn($share);

		$this->userManager->method('get')->with('ownerUID')->willReturn($owner);

		$this->shareController->showShare();
	}

	public function testDownloadShareWithCreateOnlyShare() {
		$share = $this->getMockBuilder(IShare::class)->getMock();
		$share->method('getPassword')->willReturn('password');
		$share
			->expects($this->once())
			->method('getPermissions')
			->willReturn(\OCP\Constants::PERMISSION_CREATE);

		$this->shareManager
			->expects($this->once())
			->method('getShareByToken')
			->with('validtoken')
			->willReturn($share);

		// Test with a password protected share and no authentication
		$response = $this->shareController->downloadShare('validtoken');
		$expectedResponse = new DataResponse('Share has no read permission');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testDisabledOwner() {
		$this->shareController->setToken('token');

		$owner = $this->getMockBuilder(IUser::class)->getMock();
		$owner->method('isEnabled')->willReturn(false);

		$initiator = $this->createMock(IUser::class);
		$initiator->method('isEnabled')->willReturn(false);

		/* @var MockObject|Folder $folder */
		$folder = $this->createMock(Folder::class);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setId(42);
		$share->setPermissions(Constants::PERMISSION_CREATE)
			->setShareOwner('ownerUID')
			->setSharedBy('initiatorUID')
			->setNode($folder)
			->setTarget('/share');

		$this->shareManager
			->expects($this->once())
			->method('getShareByToken')
			->with('token')
			->willReturn($share);

		$this->userManager->method('get')->willReturnCallback(function (string $uid) use ($owner, $initiator) {
			if ($uid === 'ownerUID') {
				return $owner;
			}
			if ($uid === 'initiatorUID') {
				return $initiator;
			}
			return null;
		});

		$this->expectException(NotFoundException::class);

		$this->shareController->showShare();
	}

	public function testDisabledInitiator() {
		$this->shareController->setToken('token');

		$owner = $this->getMockBuilder(IUser::class)->getMock();
		$owner->method('isEnabled')->willReturn(false);

		$initiator = $this->createMock(IUser::class);
		$initiator->method('isEnabled')->willReturn(true);

		/* @var MockObject|Folder $folder */
		$folder = $this->createMock(Folder::class);

		$share = \OC::$server->getShareManager()->newShare();
		$share->setId(42);
		$share->setPermissions(Constants::PERMISSION_CREATE)
			->setShareOwner('ownerUID')
			->setSharedBy('initiatorUID')
			->setNode($folder)
			->setTarget('/share');

		$this->shareManager
			->expects($this->once())
			->method('getShareByToken')
			->with('token')
			->willReturn($share);

		$this->userManager->method('get')->willReturnCallback(function (string $uid) use ($owner, $initiator) {
			if ($uid === 'ownerUID') {
				return $owner;
			}
			if ($uid === 'initiatorUID') {
				return $initiator;
			}
			return null;
		});

		$this->expectException(NotFoundException::class);

		$this->shareController->showShare();
	}
}
