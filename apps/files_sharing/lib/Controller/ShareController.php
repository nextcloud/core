<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author j3l11234 <297259024@qq.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Sulzer <jonas@violoncello.ch>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author MartB <mart.b@outlook.de>
 * @author Maxence Lange <maxence@pontapreta.net>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Piotr Filiciak <piotr@filiciak.pl>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sascha Sambale <mastixmc@gmail.com>
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

namespace OCA\Files_Sharing\Controller;

use OC_Files;
use OC_Util;
use OC\Security\CSP\ContentSecurityPolicy;
use OCA\FederatedFileSharing\FederatedShareProvider;
use OCA\Files_Sharing\Activity\Providers\Downloads;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Viewer\Event\LoadViewer;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\AuthPublicShareController;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Template\ExternalShareMenuAction;
use OCP\AppFramework\Http\Template\LinkMenuAction;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\Template\SimpleMenuAction;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IPreview;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Share;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use OCP\Template;

/**
 * Class ShareController
 *
 * @package OCA\Files_Sharing\Controllers
 */
class ShareController extends AuthPublicShareController {

	/** @var IConfig */
	protected $config;
	/** @var IUserManager */
	protected $userManager;
	/** @var ILogger */
	protected $logger;
	/** @var \OCP\Activity\IManager */
	protected $activityManager;
	/** @var IPreview */
	protected $previewManager;
	/** @var IRootFolder */
	protected $rootFolder;
	/** @var FederatedShareProvider */
	protected $federatedShareProvider;
	/** @var IAccountManager */
	protected $accountManager;
	/** @var IEventDispatcher */
	protected $eventDispatcher;
	/** @var IL10N */
	protected $l10n;
	/** @var Defaults */
	protected $defaults;
	/** @var ShareManager */
	protected $shareManager;

	/** @var Share\IShare */
	protected $share;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IURLGenerator $urlGenerator
	 * @param IUserManager $userManager
	 * @param ILogger $logger
	 * @param \OCP\Activity\IManager $activityManager
	 * @param \OCP\Share\IManager $shareManager
	 * @param ISession $session
	 * @param IPreview $previewManager
	 * @param IRootFolder $rootFolder
	 * @param FederatedShareProvider $federatedShareProvider
	 * @param IAccountManager $accountManager
	 * @param IEventDispatcher $eventDispatcher
	 * @param IL10N $l10n
	 * @param Defaults $defaults
	 */
	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IURLGenerator $urlGenerator,
								IUserManager $userManager,
								ILogger $logger,
								\OCP\Activity\IManager $activityManager,
								ShareManager $shareManager,
								ISession $session,
								IPreview $previewManager,
								IRootFolder $rootFolder,
								FederatedShareProvider $federatedShareProvider,
								IAccountManager $accountManager,
								IEventDispatcher $eventDispatcher,
								IL10N $l10n,
								Defaults $defaults) {
		parent::__construct($appName, $request, $session, $urlGenerator);

		$this->config = $config;
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->activityManager = $activityManager;
		$this->previewManager = $previewManager;
		$this->rootFolder = $rootFolder;
		$this->federatedShareProvider = $federatedShareProvider;
		$this->accountManager = $accountManager;
		$this->eventDispatcher = $eventDispatcher;
		$this->l10n = $l10n;
		$this->defaults = $defaults;
		$this->shareManager = $shareManager;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * Show the authentication page
	 * The form has to submit to the authenticate method route
	 */
	public function showAuthenticate(): TemplateResponse {
		$templateParameters = ['share' => $this->share];

		$this->eventDispatcher->dispatchTyped(new BeforeTemplateRenderedEvent($this->share, BeforeTemplateRenderedEvent::SCOPE_PUBLIC_SHARE_AUTH));

		$response = new TemplateResponse('core', 'publicshareauth', $templateParameters, 'guest');
		if ($this->share->getSendPasswordByTalk()) {
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedConnectDomain('*');
			$csp->addAllowedMediaDomain('blob:');
			$response->setContentSecurityPolicy($csp);
		}

		return $response;
	}

	/**
	 * The template to show when authentication failed
	 */
	protected function showAuthFailed(): TemplateResponse {
		$templateParameters = ['share' => $this->share, 'wrongpw' => true];

		$this->eventDispatcher->dispatchTyped(new BeforeTemplateRenderedEvent($this->share, BeforeTemplateRenderedEvent::SCOPE_PUBLIC_SHARE_AUTH));

		$response = new TemplateResponse('core', 'publicshareauth', $templateParameters, 'guest');
		if ($this->share->getSendPasswordByTalk()) {
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedConnectDomain('*');
			$csp->addAllowedMediaDomain('blob:');
			$response->setContentSecurityPolicy($csp);
		}

		return $response;
	}

	protected function verifyPassword(string $password): bool {
		return $this->shareManager->checkPassword($this->share, $password);
	}

	protected function getPasswordHash(): string {
		return $this->share->getPassword();
	}

	public function isValidToken(): bool {
		try {
			$this->share = $this->shareManager->getShareByToken($this->getToken());
		} catch (ShareNotFound $e) {
			return false;
		}

		return true;
	}

	protected function isPasswordProtected(): bool {
		return $this->share->getPassword() !== null;
	}

	protected function authSucceeded() {
		// For share this was always set so it is still used in other apps
		$this->session->set('public_link_authenticated', (string)$this->share->getId());
	}

	protected function authFailed() {
		$this->emitAccessShareHook($this->share, 403, 'Wrong password');
	}

	/**
	 * throws hooks when a share is attempted to be accessed
	 *
	 * @param \OCP\Share\IShare|string $share the Share instance if available,
	 * otherwise token
	 * @param int $errorCode
	 * @param string $errorMessage
	 * @throws \OC\HintException
	 * @throws \OC\ServerNotAvailableException
	 */
	protected function emitAccessShareHook($share, $errorCode = 200, $errorMessage = '') {
		$itemType = $itemSource = $uidOwner = '';
		$token = $share;
		$exception = null;
		if ($share instanceof \OCP\Share\IShare) {
			try {
				$token = $share->getToken();
				$uidOwner = $share->getSharedBy();
				$itemType = $share->getNodeType();
				$itemSource = $share->getNodeId();
			} catch (\Exception $e) {
				// we log what we know and pass on the exception afterwards
				$exception = $e;
			}
		}
		\OC_Hook::emit(Share::class, 'share_link_access', [
			'itemType' => $itemType,
			'itemSource' => $itemSource,
			'uidOwner' => $uidOwner,
			'token' => $token,
			'errorCode' => $errorCode,
			'errorMessage' => $errorMessage,
		]);
		if (!is_null($exception)) {
			throw $exception;
		}
	}

	/**
	 * Validate the permissions of the share
	 *
	 * @param Share\IShare $share
	 * @return bool
	 */
	private function validateShare(\OCP\Share\IShare $share) {
		// If the owner is disabled no access to the linke is granted
		$owner = $this->userManager->get($share->getShareOwner());
		if ($owner === null || !$owner->isEnabled()) {
			return false;
		}

		// If the initiator of the share is disabled no access is granted
		$initiator = $this->userManager->get($share->getSharedBy());
		if ($initiator === null || !$initiator->isEnabled()) {
			return false;
		}

		return $share->getNode()->isReadable() && $share->getNode()->isShareable();
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 *
	 * @param string $path
	 * @return TemplateResponse
	 * @throws NotFoundException
	 * @throws \Exception
	 */
	public function showShare($path = ''): TemplateResponse {
		\OC_User::setIncognitoMode(true);

		// Check whether share exists
		try {
			$share = $this->shareManager->getShareByToken($this->getToken());
		} catch (ShareNotFound $e) {
			$this->emitAccessShareHook($this->getToken(), 404, 'Share not found');
			throw new NotFoundException();
		}

		if (!$this->validateShare($share)) {
			throw new NotFoundException();
		}

		$shareNode = $share->getNode();

		// We can't get the path of a file share
		try {
			if ($shareNode instanceof \OCP\Files\File && $path !== '') {
				$this->emitAccessShareHook($share, 404, 'Share not found');
				throw new NotFoundException();
			}
		} catch (\Exception $e) {
			$this->emitAccessShareHook($share, 404, 'Share not found');
			throw $e;
		}

		$shareTmpl = [];
		$shareTmpl['owner'] = '';
		$shareTmpl['shareOwner'] = '';

		$owner = $this->userManager->get($share->getShareOwner());
		if ($owner instanceof IUser) {
			$ownerAccount = $this->accountManager->getAccount($owner);

			$ownerName = $ownerAccount->getProperty(IAccountManager::PROPERTY_DISPLAYNAME);
			if ($ownerName->getScope() === IAccountManager::VISIBILITY_PUBLIC) {
				$shareTmpl['owner'] = $owner->getUID();
				$shareTmpl['shareOwner'] = $owner->getDisplayName();
			}
		}

		$shareTmpl['filename'] = $shareNode->getName();
		$shareTmpl['directory_path'] = $share->getTarget();
		$shareTmpl['note'] = $share->getNote();
		$shareTmpl['mimetype'] = $shareNode->getMimetype();
		$shareTmpl['previewSupported'] = $this->previewManager->isMimeSupported($shareNode->getMimetype());
		$shareTmpl['dirToken'] = $this->getToken();
		$shareTmpl['sharingToken'] = $this->getToken();
		$shareTmpl['server2serversharing'] = $this->federatedShareProvider->isOutgoingServer2serverShareEnabled();
		$shareTmpl['protected'] = $share->getPassword() !== null ? 'true' : 'false';
		$shareTmpl['dir'] = '';
		$shareTmpl['nonHumanFileSize'] = $shareNode->getSize();
		$shareTmpl['fileSize'] = \OCP\Util::humanFileSize($shareNode->getSize());
		$shareTmpl['hideDownload'] = $share->getHideDownload();

		$hideFileList = false;

		if ($shareNode instanceof \OCP\Files\Folder) {
			$shareIsFolder = true;

			try {
				$folderNode = $shareNode->get($path);
			} catch (\OCP\Files\NotFoundException $e) {
				$this->emitAccessShareHook($share, 404, 'Share not found');
				throw new NotFoundException();
			}

			$shareTmpl['dir'] = $shareNode->getRelativePath($folderNode->getPath());

			/*
			 * The OC_Util methods require a view. This just uses the node API
			 */
			$freeSpace = $share->getNode()->getStorage()->free_space($share->getNode()->getInternalPath());
			if ($freeSpace < \OCP\Files\FileInfo::SPACE_UNLIMITED) {
				$freeSpace = max($freeSpace, 0);
			} else {
				$freeSpace = (INF > 0) ? INF: PHP_INT_MAX; // work around https://bugs.php.net/bug.php?id=69188
			}

			$hideFileList = !($share->getPermissions() & \OCP\Constants::PERMISSION_READ);
			$maxUploadFilesize = $freeSpace;

			$folder = new Template('files', 'list', '');

			$folder->assign('dir', $shareNode->getRelativePath($folderNode->getPath()));
			$folder->assign('dirToken', $this->getToken());
			$folder->assign('permissions', \OCP\Constants::PERMISSION_READ);
			$folder->assign('isPublic', true);
			$folder->assign('hideFileList', $hideFileList);
			$folder->assign('publicUploadEnabled', 'no');
			// default to list view
			$folder->assign('showgridview', false);
			$folder->assign('uploadMaxFilesize', $maxUploadFilesize);
			$folder->assign('uploadMaxHumanFilesize', \OCP\Util::humanFileSize($maxUploadFilesize));
			$folder->assign('freeSpace', $freeSpace);
			$folder->assign('usedSpacePercent', 0);
			$folder->assign('trash', false);
			$shareTmpl['folder'] = $folder->fetchPage();
		} else {
			$shareIsFolder = false;
		}

		// default to list view
		$shareTmpl['showgridview'] = false;

		$shareTmpl['hideFileList'] = $hideFileList;
		$shareTmpl['downloadURL'] = $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.downloadShare', [
			'token' => $this->getToken(),
			'filename' => $shareIsFolder ? null : $shareNode->getName()
		]);
		$shareTmpl['shareUrl'] = $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $this->getToken()]);
		$shareTmpl['maxSizeAnimateGif'] = $this->config->getSystemValue('max_filesize_animated_gifs_public_sharing', 10);
		$shareTmpl['previewEnabled'] = $this->config->getSystemValue('enable_previews', true);
		$shareTmpl['previewMaxX'] = $this->config->getSystemValue('preview_max_x', 1024);
		$shareTmpl['previewMaxY'] = $this->config->getSystemValue('preview_max_y', 1024);
		$shareTmpl['disclaimer'] = $this->config->getAppValue('core', 'shareapi_public_link_disclaimertext', null);
		$shareTmpl['previewURL'] = $shareTmpl['downloadURL'];

		if ($shareTmpl['previewSupported']) {
			$shareTmpl['previewImage'] = $this->urlGenerator->linkToRouteAbsolute('files_sharing.PublicPreview.getPreview',
				['x' => 200, 'y' => 200, 'file' => $shareTmpl['directory_path'], 'token' => $shareTmpl['dirToken']]);
			$ogPreview = $shareTmpl['previewImage'];

			// We just have direct previews for image files
			if ($shareNode->getMimePart() === 'image') {
				$shareTmpl['previewURL'] = $this->urlGenerator->linkToRouteAbsolute('files_sharing.publicpreview.directLink', ['token' => $this->getToken()]);

				$ogPreview = $shareTmpl['previewURL'];

				//Whatapp is kind of picky about their size requirements
				if ($this->request->isUserAgent(['/^WhatsApp/'])) {
					$ogPreview = $this->urlGenerator->linkToRouteAbsolute('files_sharing.PublicPreview.getPreview', [
						'token' => $this->getToken(),
						'x' => 256,
						'y' => 256,
						'a' => true,
					]);
				}
			}
		} else {
			$shareTmpl['previewImage'] = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'favicon-fb.png'));
			$ogPreview = $shareTmpl['previewImage'];
		}

		// Load files we need
		\OCP\Util::addScript('files', 'semaphore');
		\OCP\Util::addScript('files', 'file-upload');
		\OCP\Util::addStyle('files_sharing', 'publicView');
		\OCP\Util::addScript('files_sharing', 'public');
		\OCP\Util::addScript('files_sharing', 'templates');
		\OCP\Util::addScript('files', 'fileactions');
		\OCP\Util::addScript('files', 'fileactionsmenu');
		\OCP\Util::addScript('files', 'jquery.fileupload');
		\OCP\Util::addScript('files_sharing', 'files_drop');

		if (isset($shareTmpl['folder'])) {
			// JS required for folders
			\OCP\Util::addStyle('files', 'merged');
			\OCP\Util::addScript('files', 'filesummary');
			\OCP\Util::addScript('files', 'templates');
			\OCP\Util::addScript('files', 'breadcrumb');
			\OCP\Util::addScript('files', 'fileinfomodel');
			\OCP\Util::addScript('files', 'newfilemenu');
			\OCP\Util::addScript('files', 'files');
			\OCP\Util::addScript('files', 'filemultiselectmenu');
			\OCP\Util::addScript('files', 'filelist');
			\OCP\Util::addScript('files', 'keyboardshortcuts');
			\OCP\Util::addScript('files', 'operationprogressbar');

			// Load Viewer scripts
			if (class_exists(LoadViewer::class)) {
				$this->eventDispatcher->dispatchTyped(new LoadViewer());
			}
		}

		// OpenGraph Support: http://ogp.me/
		\OCP\Util::addHeader('meta', ['property' => "og:title", 'content' => $shareTmpl['filename']]);
		\OCP\Util::addHeader('meta', ['property' => "og:description", 'content' => $this->defaults->getName() . ($this->defaults->getSlogan() !== '' ? ' - ' . $this->defaults->getSlogan() : '')]);
		\OCP\Util::addHeader('meta', ['property' => "og:site_name", 'content' => $this->defaults->getName()]);
		\OCP\Util::addHeader('meta', ['property' => "og:url", 'content' => $shareTmpl['shareUrl']]);
		\OCP\Util::addHeader('meta', ['property' => "og:type", 'content' => "object"]);
		\OCP\Util::addHeader('meta', ['property' => "og:image", 'content' => $ogPreview]);

		$this->eventDispatcher->dispatchTyped(new BeforeTemplateRenderedEvent($share));

		$csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
		$csp->addAllowedFrameDomain('\'self\'');

		$response = new PublicTemplateResponse($this->appName, 'public', $shareTmpl);
		$response->setHeaderTitle($shareTmpl['filename']);
		if ($shareTmpl['shareOwner'] !== '') {
			$response->setHeaderDetails($this->l10n->t('shared by %s', [$shareTmpl['shareOwner']]));
		}

		$isNoneFileDropFolder = $shareIsFolder === false || $share->getPermissions() !== \OCP\Constants::PERMISSION_CREATE;

		if ($isNoneFileDropFolder && !$share->getHideDownload()) {
			\OCP\Util::addScript('files_sharing', 'public_note');

			$downloadWhite = new SimpleMenuAction('download', $this->l10n->t('Download'), 'icon-download-white', $shareTmpl['downloadURL'], 0);
			$downloadAllWhite = new SimpleMenuAction('download', $this->l10n->t('Download all files'), 'icon-download-white', $shareTmpl['downloadURL'], 0);
			$download = new SimpleMenuAction('download', $this->l10n->t('Download'), 'icon-download', $shareTmpl['downloadURL'], 10, $shareTmpl['fileSize']);
			$downloadAll = new SimpleMenuAction('download', $this->l10n->t('Download all files'), 'icon-download', $shareTmpl['downloadURL'], 10, $shareTmpl['fileSize']);
			$directLink = new LinkMenuAction($this->l10n->t('Direct link'), 'icon-public', $shareTmpl['previewURL']);
			$externalShare = new ExternalShareMenuAction($this->l10n->t('Add to your Nextcloud'), 'icon-external', $shareTmpl['owner'], $shareTmpl['shareOwner'], $shareTmpl['filename']);

			$responseComposer = [];

			if ($shareIsFolder) {
				$responseComposer[] = $downloadAllWhite;
				$responseComposer[] = $downloadAll;
			} else {
				$responseComposer[] = $downloadWhite;
				$responseComposer[] = $download;
			}
			$responseComposer[] = $directLink;
			if ($this->federatedShareProvider->isOutgoingServer2serverShareEnabled()) {
				$responseComposer[] = $externalShare;
			}

			$response->setHeaderActions($responseComposer);
		}

		$response->setContentSecurityPolicy($csp);

		$this->emitAccessShareHook($share);

		return $response;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * @param string $token
	 * @param string $files
	 * @param string $path
	 * @param string $downloadStartSecret
	 * @return void|\OCP\AppFramework\Http\Response
	 * @throws NotFoundException
	 */
	public function downloadShare($token, $files = null, $path = '', $downloadStartSecret = '') {
		\OC_User::setIncognitoMode(true);

		$share = $this->shareManager->getShareByToken($token);

		if (!($share->getPermissions() & \OCP\Constants::PERMISSION_READ)) {
			return new \OCP\AppFramework\Http\DataResponse('Share has no read permission');
		}

		$files_list = null;
		if (!is_null($files)) { // download selected files
			$files_list = json_decode($files);
			// in case we get only a single file
			if ($files_list === null) {
				$files_list = [$files];
			}
			// Just in case $files is a single int like '1234'
			if (!is_array($files_list)) {
				$files_list = [$files_list];
			}
		}

		if (!$this->validateShare($share)) {
			throw new NotFoundException();
		}

		$userFolder = $this->rootFolder->getUserFolder($share->getShareOwner());
		$originalSharePath = $userFolder->getRelativePath($share->getNode()->getPath());


		// Single file share
		if ($share->getNode() instanceof \OCP\Files\File) {
			// Single file download
			$this->singleFileDownloaded($share, $share->getNode());
		}
		// Directory share
		else {
			/** @var \OCP\Files\Folder $node */
			$node = $share->getNode();

			// Try to get the path
			if ($path !== '') {
				try {
					$node = $node->get($path);
				} catch (NotFoundException $e) {
					$this->emitAccessShareHook($share, 404, 'Share not found');
					return new NotFoundResponse();
				}
			}

			$originalSharePath = $userFolder->getRelativePath($node->getPath());

			if ($node instanceof \OCP\Files\File) {
				// Single file download
				$this->singleFileDownloaded($share, $share->getNode());
			} else {
				try {
					if (!empty($files_list)) {
						$this->fileListDownloaded($share, $files_list, $node);
					} else {
						// The folder is downloaded
						$this->singleFileDownloaded($share, $share->getNode());
					}
				} catch (NotFoundException $e) {
					return new NotFoundResponse();
				}
			}
		}

		/* FIXME: We should do this all nicely in OCP */
		OC_Util::tearDownFS();
		OC_Util::setupFS($share->getShareOwner());

		/**
		 * this sets a cookie to be able to recognize the start of the download
		 * the content must not be longer than 32 characters and must only contain
		 * alphanumeric characters
		 */
		if (!empty($downloadStartSecret)
			&& !isset($downloadStartSecret[32])
			&& preg_match('!^[a-zA-Z0-9]+$!', $downloadStartSecret) === 1) {

			// FIXME: set on the response once we use an actual app framework response
			setcookie('ocDownloadStarted', $downloadStartSecret, time() + 20, '/');
		}

		$this->emitAccessShareHook($share);

		$server_params = [ 'head' => $this->request->getMethod() === 'HEAD' ];

		/**
		 * Http range requests support
		 */
		if (isset($_SERVER['HTTP_RANGE'])) {
			$server_params['range'] = $this->request->getHeader('Range');
		}

		// download selected files
		if (!is_null($files) && $files !== '') {
			// FIXME: The exit is required here because otherwise the AppFramework is trying to add headers as well
			// after dispatching the request which results in a "Cannot modify header information" notice.
			OC_Files::get($originalSharePath, $files_list, $server_params);
			exit();
		} else {
			// FIXME: The exit is required here because otherwise the AppFramework is trying to add headers as well
			// after dispatching the request which results in a "Cannot modify header information" notice.
			OC_Files::get(dirname($originalSharePath), basename($originalSharePath), $server_params);
			exit();
		}
	}

	/**
	 * create activity for every downloaded file
	 *
	 * @param Share\IShare $share
	 * @param array $files_list
	 * @param \OCP\Files\Folder $node
	 * @throws NotFoundException when trying to download a folder or multiple files of a "hide download" share
	 */
	protected function fileListDownloaded(Share\IShare $share, array $files_list, \OCP\Files\Folder $node) {
		if ($share->getHideDownload() && count($files_list) > 1) {
			throw new NotFoundException('Downloading more than 1 file');
		}

		foreach ($files_list as $file) {
			$subNode = $node->get($file);
			$this->singleFileDownloaded($share, $subNode);
		}
	}

	/**
	 * create activity if a single file was downloaded from a link share
	 *
	 * @param Share\IShare $share
	 * @throws NotFoundException when trying to download a folder of a "hide download" share
	 */
	protected function singleFileDownloaded(Share\IShare $share, \OCP\Files\Node $node) {
		if ($share->getHideDownload() && $node instanceof Folder) {
			throw new NotFoundException('Downloading a folder');
		}

		$fileId = $node->getId();

		$userFolder = $this->rootFolder->getUserFolder($share->getSharedBy());
		$userNodeList = $userFolder->getById($fileId);
		$userNode = $userNodeList[0];
		$ownerFolder = $this->rootFolder->getUserFolder($share->getShareOwner());
		$userPath = $userFolder->getRelativePath($userNode->getPath());
		$ownerPath = $ownerFolder->getRelativePath($node->getPath());

		$parameters = [$userPath];

		if ($share->getShareType() === IShare::TYPE_EMAIL) {
			if ($node instanceof \OCP\Files\File) {
				$subject = Downloads::SUBJECT_SHARED_FILE_BY_EMAIL_DOWNLOADED;
			} else {
				$subject = Downloads::SUBJECT_SHARED_FOLDER_BY_EMAIL_DOWNLOADED;
			}
			$parameters[] = $share->getSharedWith();
		} else {
			if ($node instanceof \OCP\Files\File) {
				$subject = Downloads::SUBJECT_PUBLIC_SHARED_FILE_DOWNLOADED;
			} else {
				$subject = Downloads::SUBJECT_PUBLIC_SHARED_FOLDER_DOWNLOADED;
			}
		}

		$this->publishActivity($subject, $parameters, $share->getSharedBy(), $fileId, $userPath);

		if ($share->getShareOwner() !== $share->getSharedBy()) {
			$parameters[0] = $ownerPath;
			$this->publishActivity($subject, $parameters, $share->getShareOwner(), $fileId, $ownerPath);
		}
	}

	/**
	 * publish activity
	 *
	 * @param string $subject
	 * @param array $parameters
	 * @param string $affectedUser
	 * @param int $fileId
	 * @param string $filePath
	 */
	protected function publishActivity($subject,
										array $parameters,
										$affectedUser,
										$fileId,
										$filePath) {
		$event = $this->activityManager->generateEvent();
		$event->setApp('files_sharing')
			->setType('public_links')
			->setSubject($subject, $parameters)
			->setAffectedUser($affectedUser)
			->setObject('files', $fileId, $filePath);
		$this->activityManager->publish($event);
	}
}
