<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files\Tests\Controller;

use OCA\Files\Activity\Helper;
use OCA\Files\Controller\ViewController;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Template\ITemplateManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Template;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

/**
 * Class ViewControllerTest
 *
 * @package OCA\Files\Tests\Controller
 */
class ViewControllerTest extends TestCase {
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;
	/** @var IL10N */
	private $l10n;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var EventDispatcherInterface */
	private $eventDispatcher;
	/** @var ViewController|\PHPUnit\Framework\MockObject\MockObject */
	private $viewController;
	/** @var IUser */
	private $user;
	/** @var IUserSession */
	private $userSession;
	/** @var IAppManager|\PHPUnit\Framework\MockObject\MockObject */
	private $appManager;
	/** @var IRootFolder|\PHPUnit\Framework\MockObject\MockObject */
	private $rootFolder;
	/** @var Helper|\PHPUnit\Framework\MockObject\MockObject */
	private $activityHelper;
	/** @var IInitialState|\PHPUnit\Framework\MockObject\MockObject */
	private $initialState;
	/** @var ITemplateManager|\PHPUnit\Framework\MockObject\MockObject */
	private $templateManager;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->getMockBuilder(IRequest::class)->getMock();
		$this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$this->l10n = $this->getMockBuilder(IL10N::class)->getMock();
		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->userSession = $this->getMockBuilder(IUserSession::class)->getMock();
		$this->appManager = $this->getMockBuilder('\OCP\App\IAppManager')->getMock();
		$this->user = $this->getMockBuilder(IUser::class)->getMock();
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn('testuser1');
		$this->userSession->expects($this->any())
			->method('getUser')
			->willReturn($this->user);
		$this->rootFolder = $this->getMockBuilder('\OCP\Files\IRootFolder')->getMock();
		$this->activityHelper = $this->createMock(Helper::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->templateManager = $this->createMock(ITemplateManager::class);
		$this->viewController = $this->getMockBuilder('\OCA\Files\Controller\ViewController')
			->setConstructorArgs([
				'files',
				$this->request,
				$this->urlGenerator,
				$this->l10n,
				$this->config,
				$this->eventDispatcher,
				$this->userSession,
				$this->appManager,
				$this->rootFolder,
				$this->activityHelper,
				$this->initialState,
				$this->templateManager,
			])
		->setMethods([
			'getStorageInfo',
			'renderScript'
		])
		->getMock();
	}

	public function testIndexWithRegularBrowser() {
		$this->viewController
			->expects($this->once())
			->method('getStorageInfo')
			->willReturn([
				'used' => 123,
				'quota' => 100,
				'total' => 100,
				'relative' => 123,
				'owner' => 'MyName',
				'ownerDisplayName' => 'MyDisplayName',
			]);
		$this->config
			->method('getUserValue')
			->willReturnMap([
				[$this->user->getUID(), 'files', 'file_sorting', 'name', 'name'],
				[$this->user->getUID(), 'files', 'file_sorting_direction', 'asc', 'asc'],
				[$this->user->getUID(), 'files', 'show_hidden', false, false],
				[$this->user->getUID(), 'files', 'crop_image_previews', true, true],
				[$this->user->getUID(), 'files', 'show_grid', true],
			]);

		$this->config
				->expects($this->any())
				->method('getAppValue')
				->willReturnArgument(2);

		$nav = new Template('files', 'appnavigation');
		$nav->assign('usage_relative', 123);
		$nav->assign('usage', '123 B');
		$nav->assign('quota', 100);
		$nav->assign('total_space', '100 B');
		$nav->assign('webdav_url', 'http://localhost/remote.php/dav/files/testuser1/');
		$nav->assign('navigationItems', [
			'files' => [
				'id' => 'files',
				'appname' => 'files',
				'script' => 'list.php',
				'order' => 0,
				'name' => \OC::$server->getL10N('files')->t('All files'),
				'active' => false,
				'icon' => '',
				'type' => 'link',
				'classes' => '',
			],
			'recent' => [
				'id' => 'recent',
				'appname' => 'files',
				'script' => 'recentlist.php',
				'order' => 2,
				'name' => \OC::$server->getL10N('files')->t('Recent'),
				'active' => false,
				'icon' => '',
				'type' => 'link',
				'classes' => '',
			],
			'favorites' => [
				'id' => 'favorites',
				'appname' => 'files',
				'script' => 'simplelist.php',
				'order' => 5,
				'name' => \OC::$server->getL10N('files')->t('Favorites'),
				'active' => false,
				'icon' => '',
				'type' => 'link',
				'classes' => 'collapsible',
				'sublist' => [
					[
						'id' => '-test1',
						'view' => 'files',
						'href' => '',
						'dir' => '/test1',
						'order' => 6,
						'folderPosition' => 1,
						'name' => 'test1',
						'icon' => 'files',
						'quickaccesselement' => 'true',
					],
					[
						'name' => 'test2',
						'id' => '-test2-',
						'view' => 'files',
						'href' => '',
						'dir' => '/test2/',
						'order' => 7,
						'folderPosition' => 2,
						'icon' => 'files',
						'quickaccesselement' => 'true',
					],
					[
						'name' => 'sub4',
						'id' => '-test3-sub4',
						'view' => 'files',
						'href' => '',
						'dir' => '/test3/sub4',
						'order' => 8,
						'folderPosition' => 3,
						'icon' => 'files',
						'quickaccesselement' => 'true',
					],
					[
						'name' => 'sub6',
						'id' => '-test5-sub6-',
						'view' => 'files',
						'href' => '',
						'dir' => '/test5/sub6/',
						'order' => 9,
						'folderPosition' => 4,
						'icon' => 'files',
						'quickaccesselement' => 'true',
					],
				],
				'defaultExpandedState' => false,
				'expandedState' => 'show_Quick_Access'
			],
			'systemtagsfilter' => [
				'id' => 'systemtagsfilter',
				'appname' => 'systemtags',
				'script' => 'list.php',
				'order' => 25,
				'name' => \OC::$server->getL10N('systemtags')->t('Tags'),
				'active' => false,
				'icon' => '',
				'type' => 'link',
				'classes' => '',
			],
			'trashbin' => [
				'id' => 'trashbin',
				'appname' => 'files_trashbin',
				'script' => 'list.php',
				'order' => 50,
				'name' => \OC::$server->getL10N('files_trashbin')->t('Deleted files'),
				'active' => false,
				'icon' => '',
				'type' => 'link',
				'classes' => 'pinned',
			],
			'shareoverview' => [
				'id' => 'shareoverview',
				'appname' => 'files_sharing',
				'script' => 'list.php',
				'order' => 18,
				'name' => \OC::$server->getL10N('files_sharing')->t('Shares'),
				'classes' => 'collapsible',
				'sublist' => [
					[
						'id' => 'sharingout',
						'appname' => 'files_sharing',
						'script' => 'list.php',
						'order' => 16,
						'name' => \OC::$server->getL10N('files_sharing')->t('Shared with others'),
					],
					[
						'id' => 'sharingin',
						'appname' => 'files_sharing',
						'script' => 'list.php',
						'order' => 15,
						'name' => \OC::$server->getL10N('files_sharing')->t('Shared with you'),
					],
					[
						'id' => 'sharinglinks',
						'appname' => 'files_sharing',
						'script' => 'list.php',
						'order' => 17,
						'name' => \OC::$server->getL10N('files_sharing')->t('Shared by link', []),
					],
					[
						'id' => 'deletedshares',
						'appname' => 'files_sharing',
						'script' => 'list.php',
						'order' => 19,
						'name' => \OC::$server->getL10N('files_sharing')->t('Deleted shares'),
					],
					[
						'id' => 'pendingshares',
						'appname' => 'files_sharing',
						'script' => 'list.php',
						'order' => 19,
						'name' => \OC::$server->getL10N('files_sharing')->t('Pending shares'),
					],
				],
				'active' => false,
				'icon' => '',
				'type' => 'link',
				'expandedState' => 'show_sharing_menu',
				'defaultExpandedState' => false,
			]
		]);

		$expected = new Http\TemplateResponse(
			'files',
			'index',
			[
				'usedSpacePercent' => 123,
				'owner' => 'MyName',
				'ownerDisplayName' => 'MyDisplayName',
				'isPublic' => false,
				'defaultFileSorting' => 'name',
				'defaultFileSortingDirection' => 'asc',
				'showHiddenFiles' => 0,
				'cropImagePreviews' => 1,
				'fileNotFound' => 0,
				'allowShareWithLink' => 'yes',
				'appNavigation' => $nav,
				'appContents' => [
					'files' => [
						'id' => 'files',
						'content' => null,
					],
					'recent' => [
						'id' => 'recent',
						'content' => null,
					],
					'favorites' => [
						'id' => 'favorites',
						'content' => null,
					],
					'systemtagsfilter' => [
						'id' => 'systemtagsfilter',
						'content' => null,
					],
					'trashbin' => [
						'id' => 'trashbin',
						'content' => null,
					],
					'sharingout' => [
						'id' => 'sharingout',
						'content' => null,
					],
					'sharingin' => [
						'id' => 'sharingin',
						'content' => null,
					],
					'sharinglinks' => [
						'id' => 'sharinglinks',
						'content' => null,
					],
					'deletedshares' => [
						'id' => 'deletedshares',
						'content' => null,
					],
					'pendingshares' => [
						'id' => 'pendingshares',
						'content' => null
					],
					'shareoverview' => [
						'id' => 'shareoverview',
						'content' => null,
					],
					'-test1' => [
						'id' => '-test1',
						'content' => '',
					],
					'-test2-' => [
						'id' => '-test2-',
						'content' => '',
					],
					'-test3-sub4' => [
						'id' => '-test3-sub4',
						'content' => '',
					],
					'-test5-sub6-' => [
						'id' => '-test5-sub6-',
						'content' => '',
					],
				],
				'hiddenFields' => [],
				'showgridview' => false,
				'isIE' => false,
			]
		);
		$policy = new Http\ContentSecurityPolicy();
		$policy->addAllowedFrameDomain('\'self\'');
		$expected->setContentSecurityPolicy($policy);

		$this->activityHelper->method('getFavoriteFilePaths')
			->with($this->user->getUID())
			->willReturn([
				'item' => [],
				'folders' => [
					'/test1',
					'/test2/',
					'/test3/sub4',
					'/test5/sub6/',
				],
			]);

		$this->assertEquals($expected, $this->viewController->index('MyDir', 'MyView'));
	}

	public function testShowFileRouteWithFolder() {
		$node = $this->getMockBuilder(Folder::class)->getMock();
		$node->expects($this->once())
			->method('getPath')
			->willReturn('/testuser1/files/test/sub');

		$baseFolder = $this->getMockBuilder(Folder::class)->getMock();

		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('testuser1')
			->willReturn($baseFolder);

		$baseFolder->expects($this->at(0))
			->method('getById')
			->with(123)
			->willReturn([$node]);
		$baseFolder->expects($this->at(1))
			->method('getRelativePath')
			->with('/testuser1/files/test/sub')
			->willReturn('/test/sub');

		$this->urlGenerator
			->expects($this->once())
			->method('linkToRoute')
			->with('files.view.index', ['dir' => '/test/sub'])
			->willReturn('/apps/files/?dir=/test/sub');

		$expected = new Http\RedirectResponse('/apps/files/?dir=/test/sub');
		$this->assertEquals($expected, $this->viewController->index('/whatever', '', '123'));
	}

	public function testShowFileRouteWithFile() {
		$parentNode = $this->getMockBuilder(Folder::class)->getMock();
		$parentNode->expects($this->once())
			->method('getPath')
			->willReturn('testuser1/files/test');

		$baseFolder = $this->getMockBuilder(Folder::class)->getMock();

		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('testuser1')
			->willReturn($baseFolder);

		$node = $this->getMockBuilder(File::class)->getMock();
		$node->expects($this->once())
			->method('getParent')
			->willReturn($parentNode);
		$node->expects($this->once())
			->method('getName')
			->willReturn('somefile.txt');

		$baseFolder->expects($this->at(0))
			->method('getById')
			->with(123)
			->willReturn([$node]);
		$baseFolder->expects($this->at(1))
			->method('getRelativePath')
			->with('testuser1/files/test')
			->willReturn('/test');

		$this->urlGenerator
			->expects($this->once())
			->method('linkToRoute')
			->with('files.view.index', ['dir' => '/test', 'scrollto' => 'somefile.txt'])
			->willReturn('/apps/files/?dir=/test/sub&scrollto=somefile.txt');

		$expected = new Http\RedirectResponse('/apps/files/?dir=/test/sub&scrollto=somefile.txt');
		$this->assertEquals($expected, $this->viewController->index('/whatever', '', '123'));
	}

	public function testShowFileRouteWithInvalidFileId() {
		$baseFolder = $this->getMockBuilder(Folder::class)->getMock();
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('testuser1')
			->willReturn($baseFolder);

		$baseFolder->expects($this->at(0))
			->method('getById')
			->with(123)
			->willReturn([]);

		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('files.view.index', ['fileNotFound' => true])
			->willReturn('redirect.url');

		$response = $this->viewController->index('MyDir', 'MyView', '123');
		$this->assertInstanceOf('OCP\AppFramework\Http\RedirectResponse', $response);
		$this->assertEquals('redirect.url', $response->getRedirectURL());
	}

	public function testShowFileRouteWithTrashedFile() {
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('files_trashbin')
			->willReturn(true);

		$parentNode = $this->getMockBuilder(Folder::class)->getMock();
		$parentNode->expects($this->once())
			->method('getPath')
			->willReturn('testuser1/files_trashbin/files/test.d1462861890/sub');

		$baseFolderFiles = $this->getMockBuilder(Folder::class)->getMock();
		$baseFolderTrash = $this->getMockBuilder(Folder::class)->getMock();

		$this->rootFolder->expects($this->at(0))
			->method('getUserFolder')
			->with('testuser1')
			->willReturn($baseFolderFiles);
		$this->rootFolder->expects($this->at(1))
			->method('get')
			->with('testuser1/files_trashbin/files/')
			->willReturn($baseFolderTrash);

		$baseFolderFiles->expects($this->once())
			->method('getById')
			->with(123)
			->willReturn([]);

		$node = $this->getMockBuilder(File::class)->getMock();
		$node->expects($this->once())
			->method('getParent')
			->willReturn($parentNode);
		$node->expects($this->once())
			->method('getName')
			->willReturn('somefile.txt');

		$baseFolderTrash->expects($this->at(0))
			->method('getById')
			->with(123)
			->willReturn([$node]);
		$baseFolderTrash->expects($this->at(1))
			->method('getRelativePath')
			->with('testuser1/files_trashbin/files/test.d1462861890/sub')
			->willReturn('/test.d1462861890/sub');

		$this->urlGenerator
			->expects($this->once())
			->method('linkToRoute')
			->with('files.view.index', ['view' => 'trashbin', 'dir' => '/test.d1462861890/sub', 'scrollto' => 'somefile.txt'])
			->willReturn('/apps/files/?view=trashbin&dir=/test.d1462861890/sub&scrollto=somefile.txt');

		$expected = new Http\RedirectResponse('/apps/files/?view=trashbin&dir=/test.d1462861890/sub&scrollto=somefile.txt');
		$this->assertEquals($expected, $this->viewController->index('/whatever', '', '123'));
	}
}
