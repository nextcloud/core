<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author fnuesse <felix.nuesse@t-online.de>
 * @author fnuesse <fnuesse@techfak.uni-bielefeld.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Max Kovalenko <mxss1998@yandex.ru>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\Files\Controller;

use OCA\Files\Activity\Helper;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files\Event\LoadSidebar;
use OCA\Viewer\Event\LoadViewer;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\Template\ITemplateManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * Class ViewController
 *
 * @package OCA\Files\Controller
 */
class ViewController extends Controller {
	/** @var string */
	protected $appName;
	/** @var IRequest */
	protected $request;
	/** @var IURLGenerator */
	protected $urlGenerator;
	/** @var IL10N */
	protected $l10n;
	/** @var IConfig */
	protected $config;
	/** @var IEventDispatcher */
	protected $eventDispatcher;
	/** @var IUserSession */
	protected $userSession;
	/** @var IAppManager */
	protected $appManager;
	/** @var IRootFolder */
	protected $rootFolder;
	/** @var Helper */
	protected $activityHelper;
	/** @var IInitialState */
	private $initialState;
	/** @var ITemplateManager */
	private $templateManager;

	public function __construct(string $appName,
		IRequest $request,
		IURLGenerator $urlGenerator,
		IL10N $l10n,
		IConfig $config,
		IEventDispatcher $eventDispatcher,
		IUserSession $userSession,
		IAppManager $appManager,
		IRootFolder $rootFolder,
		Helper $activityHelper,
		IInitialState $initialState,
		ITemplateManager $templateManager
	) {
		parent::__construct($appName, $request);
		$this->appName = $appName;
		$this->request = $request;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->eventDispatcher = $eventDispatcher;
		$this->userSession = $userSession;
		$this->appManager = $appManager;
		$this->rootFolder = $rootFolder;
		$this->activityHelper = $activityHelper;
		$this->initialState = $initialState;
		$this->templateManager = $templateManager;
	}

	/**
	 * @param string $appName
	 * @param string $scriptName
	 * @return string
	 */
	protected function renderScript($appName, $scriptName) {
		$content = '';
		$appPath = \OC_App::getAppPath($appName);
		$scriptPath = $appPath . '/' . $scriptName;
		if (file_exists($scriptPath)) {
			// TODO: sanitize path / script name ?
			ob_start();
			include $scriptPath;
			$content = ob_get_contents();
			@ob_end_clean();
		}

		return $content;
	}

	/**
	 * FIXME: Replace with non static code
	 *
	 * @return array
	 * @throws \OCP\Files\NotFoundException
	 */
	protected function getStorageInfo() {
		\OC_Util::setupFS();
		$dirInfo = \OC\Files\Filesystem::getFileInfo('/', false);

		return \OC_Helper::getStorageInfo('/', $dirInfo);
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 *
	 * @param string $fileid
	 * @return TemplateResponse|RedirectResponse
	 * @throws NotFoundException
	 */
	public function showFile(string $fileid = null): Response {
		// This is the entry point from the `/f/{fileid}` URL which is hardcoded in the server.
		try {
			return $this->redirectToFile($fileid);
		} catch (NotFoundException $e) {
			return new RedirectResponse($this->urlGenerator->linkToRoute('files.view.index', ['fileNotFound' => true]));
		}
	}

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 *
	 * @param string $dir
	 * @param string $view
	 * @param string $fileid
	 * @param bool $fileNotFound
	 * @return TemplateResponse|RedirectResponse
	 * @throws NotFoundException
	 */
	public function index($dir = '', $view = '', $fileid = null, $fileNotFound = false) {
		if ($fileid !== null) {
			try {
				return $this->redirectToFile($fileid);
			} catch (NotFoundException $e) {
				return new RedirectResponse($this->urlGenerator->linkToRoute('files.view.index', ['fileNotFound' => true]));
			}
		}

		$nav = new \OCP\Template('files', 'appnavigation', '');

		// Load the files we need
		\OCP\Util::addStyle('files', 'merged');
		\OCP\Util::addScript('files', 'merged-index');
		\OCP\Util::addScript('files', 'dist/templates');
		\OCP\Util::addScript('files', 'dist/download');

		// mostly for the home storage's free space
		// FIXME: Make non static
		$storageInfo = $this->getStorageInfo();

		$user = $this->userSession->getUser()->getUID();

		// Get all the user favorites to create a submenu
		try {
			$favElements = $this->activityHelper->getFavoriteFilePaths($this->userSession->getUser()->getUID());
		} catch (\RuntimeException $e) {
			$favElements['folders'] = [];
		}

		$collapseClasses = '';
		if (count($favElements['folders']) > 0) {
			$collapseClasses = 'collapsible';
		}

		$favoritesSublistArray = [];

		$navBarPositionPosition = 6;
		$currentCount = 0;
		foreach ($favElements['folders'] as $dir) {
			$link = $this->urlGenerator->linkToRoute('files.view.index', ['dir' => $dir, 'view' => 'files']);
			$sortingValue = ++$currentCount;
			$element = [
				'id' => str_replace('/', '-', $dir),
				'view' => 'files',
				'href' => $link,
				'dir' => $dir,
				'order' => $navBarPositionPosition,
				'folderPosition' => $sortingValue,
				'name' => basename($dir),
				'icon' => 'files',
				'quickaccesselement' => 'true'
			];

			array_push($favoritesSublistArray, $element);
			$navBarPositionPosition++;
		}

		$navItems = \OCA\Files\App::getNavigationManager()->getAll();

		// add the favorites entry in menu
		$navItems['favorites']['sublist'] = $favoritesSublistArray;
		$navItems['favorites']['classes'] = $collapseClasses;

		// parse every menu and add the expandedState user value
		foreach ($navItems as $key => $item) {
			if (isset($item['expandedState'])) {
				$navItems[$key]['defaultExpandedState'] = $this->config->getUserValue($this->userSession->getUser()->getUID(), 'files', $item['expandedState'], '0') === '1';
			}
		}

		$nav->assign('navigationItems', $navItems);

		$nav->assign('usage', \OC_Helper::humanFileSize($storageInfo['used']));
		if ($storageInfo['quota'] === \OCP\Files\FileInfo::SPACE_UNLIMITED) {
			$totalSpace = $this->l10n->t('Unlimited');
		} else {
			$totalSpace = \OC_Helper::humanFileSize($storageInfo['total']);
		}
		$nav->assign('total_space', $totalSpace);
		$nav->assign('quota', $storageInfo['quota']);
		$nav->assign('usage_relative', $storageInfo['relative']);

		$nav->assign('webdav_url', \OCP\Util::linkToRemote('dav/files/' . $user));

		$contentItems = [];

		// render the container content for every navigation item
		foreach ($navItems as $item) {
			$content = '';
			if (isset($item['script'])) {
				$content = $this->renderScript($item['appname'], $item['script']);
			}
			// parse submenus
			if (isset($item['sublist'])) {
				foreach ($item['sublist'] as $subitem) {
					$subcontent = '';
					if (isset($subitem['script'])) {
						$subcontent = $this->renderScript($subitem['appname'], $subitem['script']);
					}
					$contentItems[$subitem['id']] = [
						'id' => $subitem['id'],
						'content' => $subcontent
					];
				}
			}
			$contentItems[$item['id']] = [
				'id' => $item['id'],
				'content' => $content
			];
		}

		$event = new LoadAdditionalScriptsEvent();
		$this->eventDispatcher->dispatchTyped($event);
		$this->eventDispatcher->dispatchTyped(new LoadSidebar());
		// Load Viewer scripts
		if (class_exists(LoadViewer::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadViewer());
		}
		$this->initialState->provideInitialState('templates_path', $this->templateManager->hasTemplateDirectory() ? $this->templateManager->getTemplatePath() : false);
		$this->initialState->provideInitialState('templates', $this->templateManager->listCreators());

		$params = [];
		$params['usedSpacePercent'] = (int) $storageInfo['relative'];
		$params['owner'] = $storageInfo['owner'] ?? '';
		$params['ownerDisplayName'] = $storageInfo['ownerDisplayName'] ?? '';
		$params['isPublic'] = false;
		$params['allowShareWithLink'] = $this->config->getAppValue('core', 'shareapi_allow_links', 'yes');
		$params['defaultFileSorting'] = $this->config->getUserValue($user, 'files', 'file_sorting', 'name');
		$params['defaultFileSortingDirection'] = $this->config->getUserValue($user, 'files', 'file_sorting_direction', 'asc');
		$params['showgridview'] = $this->config->getUserValue($user, 'files', 'show_grid', false);
		$params['isIE'] = \OC_Util::isIe();
		$showHidden = (bool) $this->config->getUserValue($this->userSession->getUser()->getUID(), 'files', 'show_hidden', false);
		$params['showHiddenFiles'] = $showHidden ? 1 : 0;
		$cropImagePreviews = (bool) $this->config->getUserValue($this->userSession->getUser()->getUID(), 'files', 'crop_image_previews', true);
		$params['cropImagePreviews'] = $cropImagePreviews ? 1 : 0;
		$params['fileNotFound'] = $fileNotFound ? 1 : 0;
		$params['appNavigation'] = $nav;
		$params['appContents'] = $contentItems;
		$params['hiddenFields'] = $event->getHiddenFields();

		$response = new TemplateResponse(
			$this->appName,
			'index',
			$params
		);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameDomain('\'self\'');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	/**
	 * Redirects to the file list and highlight the given file id
	 *
	 * @param string $fileId file id to show
	 * @return RedirectResponse redirect response or not found response
	 * @throws \OCP\Files\NotFoundException
	 */
	private function redirectToFile($fileId) {
		$uid = $this->userSession->getUser()->getUID();
		$baseFolder = $this->rootFolder->getUserFolder($uid);
		$files = $baseFolder->getById($fileId);
		$params = [];

		if (empty($files) && $this->appManager->isEnabledForUser('files_trashbin')) {
			$baseFolder = $this->rootFolder->get($uid . '/files_trashbin/files/');
			$files = $baseFolder->getById($fileId);
			$params['view'] = 'trashbin';
		}

		if (!empty($files)) {
			$file = current($files);
			if ($file instanceof Folder) {
				// set the full path to enter the folder
				$params['dir'] = $baseFolder->getRelativePath($file->getPath());
			} else {
				// set parent path as dir
				$params['dir'] = $baseFolder->getRelativePath($file->getParent()->getPath());
				// and scroll to the entry
				$params['scrollto'] = $file->getName();
			}

			return new RedirectResponse($this->urlGenerator->linkToRoute('files.view.index', $params));
		}
		throw new \OCP\Files\NotFoundException();
	}
}
