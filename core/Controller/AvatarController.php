<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\Core\Controller;

use OC\AppFramework\Utility\TimeFactory;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IAvatarManager;
use OCP\ICache;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * Class AvatarController
 *
 * @package OC\Core\Controller
 */
class AvatarController extends Controller {

	/** @var IAvatarManager */
	protected $avatarManager;

	/** @var ICache */
	protected $cache;

	/** @var IL10N */
	protected $l;

	/** @var IUserManager */
	protected $userManager;

	/** @var IUserSession */
	protected $userSession;

	/** @var IRootFolder */
	protected $rootFolder;

	/** @var ILogger */
	protected $logger;

	/** @var string */
	protected $userId;

	/** @var TimeFactory */
	protected $timeFactory;

	public function __construct($appName,
								IRequest $request,
								IAvatarManager $avatarManager,
								ICache $cache,
								IL10N $l10n,
								IUserManager $userManager,
								IRootFolder $rootFolder,
								ILogger $logger,
								$userId,
								TimeFactory $timeFactory) {
		parent::__construct($appName, $request);

		$this->avatarManager = $avatarManager;
		$this->cache = $cache;
		$this->l = $l10n;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->logger = $logger;
		$this->userId = $userId;
		$this->timeFactory = $timeFactory;
	}


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 * @PublicPage
	 *
	 * @param string $userId
	 * @param int $size
	 * @return JSONResponse|FileDisplayResponse
	 */
	public function getAvatar($userId, $size) {
		// min/max size
		if ($size > 2048) {
			$size = 2048;
		} elseif ($size <= 0) {
			$size = 64;
		}

		try {
			$avatar = $this->avatarManager->getAvatar($userId);
			$avatarFile = $avatar->getFile($size);
			$response = new FileDisplayResponse(
				$avatarFile,
				Http::STATUS_OK,
				['Content-Type' => $avatarFile->getMimeType(), 'X-NC-IsCustomAvatar' => (int)$avatar->isCustomAvatar()]
			);
		} catch (\Exception $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		// Cache for 1 day
		$response->cacheFor(60 * 60 * 24);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $path
	 * @return JSONResponse
	 */
	public function postAvatar($path) {
		$files = $this->request->getUploadedFile('files');

		if (isset($path)) {
			$path = stripslashes($path);
			$userFolder = $this->rootFolder->getUserFolder($this->userId);
			/** @var File $node */
			$node = $userFolder->get($path);
			if (!($node instanceof File)) {
				return new JSONResponse(['data' => ['message' => $this->l->t('Please select a file.')]]);
			}
			if ($node->getSize() > 20 * 1024 * 1024) {
				return new JSONResponse(
					['data' => ['message' => $this->l->t('File is too big')]],
					Http::STATUS_BAD_REQUEST
				);
			}

			if ($node->getMimeType() !== 'image/jpeg' && $node->getMimeType() !== 'image/png') {
				return new JSONResponse(
					['data' => ['message' => $this->l->t('The selected file is not an image.')]],
					Http::STATUS_BAD_REQUEST
				);
			}

			try {
				$content = $node->getContent();
			} catch (\OCP\Files\NotPermittedException $e) {
				return new JSONResponse(
					['data' => ['message' => $this->l->t('The selected file cannot be read.')]],
					Http::STATUS_BAD_REQUEST
				);
			}
		} elseif (!is_null($files)) {
			if (
				$files['error'][0] === 0 &&
				 is_uploaded_file($files['tmp_name'][0]) &&
				!\OC\Files\Filesystem::isFileBlacklisted($files['tmp_name'][0])
			) {
				if ($files['size'][0] > 20 * 1024 * 1024) {
					return new JSONResponse(
						['data' => ['message' => $this->l->t('File is too big')]],
						Http::STATUS_BAD_REQUEST
					);
				}
				$this->cache->set('avatar_upload', file_get_contents($files['tmp_name'][0]), 7200);
				$content = $this->cache->get('avatar_upload');
				unlink($files['tmp_name'][0]);
			} else {
				return new JSONResponse(
					['data' => ['message' => $this->l->t('Invalid file provided')]],
					Http::STATUS_BAD_REQUEST
				);
			}
		} else {
			//Add imgfile
			return new JSONResponse(
				['data' => ['message' => $this->l->t('No image or file provided')]],
				Http::STATUS_BAD_REQUEST
			);
		}

		try {
			$image = new \OC_Image();
			$image->loadFromData($content);
			$image->readExif($content);
			$image->fixOrientation();

			if ($image->valid()) {
				$mimeType = $image->mimeType();
				if ($mimeType !== 'image/jpeg' && $mimeType !== 'image/png') {
					return new JSONResponse(
						['data' => ['message' => $this->l->t('Unknown filetype')]],
						Http::STATUS_OK
					);
				}

				$this->cache->set('tmpAvatar', $image->data(), 7200);
				return new JSONResponse(
					['data' => 'notsquare'],
					Http::STATUS_OK
				);
			} else {
				return new JSONResponse(
					['data' => ['message' => $this->l->t('Invalid image')]],
					Http::STATUS_OK
				);
			}
		} catch (\Exception $e) {
			$this->logger->logException($e, ['app' => 'core']);
			return new JSONResponse(['data' => ['message' => $this->l->t('An error occurred. Please contact your admin.')]], Http::STATUS_OK);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 */
	public function deleteAvatar() {
		try {
			$avatar = $this->avatarManager->getAvatar($this->userId);
			$avatar->remove();
			return new JSONResponse();
		} catch (\Exception $e) {
			$this->logger->logException($e, ['app' => 'core']);
			return new JSONResponse(['data' => ['message' => $this->l->t('An error occurred. Please contact your admin.')]], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return JSONResponse|DataDisplayResponse
	 */
	public function getTmpAvatar() {
		$tmpAvatar = $this->cache->get('tmpAvatar');
		if (is_null($tmpAvatar)) {
			return new JSONResponse(['data' => [
				'message' => $this->l->t("No temporary profile picture available, try again")
			]],
									Http::STATUS_NOT_FOUND);
		}

		$image = new \OC_Image();
		$image->loadFromData($tmpAvatar);

		$resp = new DataDisplayResponse($image->data(),
				Http::STATUS_OK,
				['Content-Type' => $image->mimeType()]);

		$resp->setETag((string)crc32($image->data()));
		$resp->cacheFor(0);
		$resp->setLastModified(new \DateTime('now', new \DateTimeZone('GMT')));
		return $resp;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param array $crop
	 * @return JSONResponse
	 */
	public function postCroppedAvatar($crop) {
		if (is_null($crop)) {
			return new JSONResponse(['data' => ['message' => $this->l->t("No crop data provided")]],
									Http::STATUS_BAD_REQUEST);
		}

		if (!isset($crop['x'], $crop['y'], $crop['w'], $crop['h'])) {
			return new JSONResponse(['data' => ['message' => $this->l->t("No valid crop data provided")]],
									Http::STATUS_BAD_REQUEST);
		}

		$tmpAvatar = $this->cache->get('tmpAvatar');
		if (is_null($tmpAvatar)) {
			return new JSONResponse(['data' => [
				'message' => $this->l->t("No temporary profile picture available, try again")
			]],
									Http::STATUS_BAD_REQUEST);
		}

		$image = new \OC_Image();
		$image->loadFromData($tmpAvatar);
		$image->crop($crop['x'], $crop['y'], (int)round($crop['w']), (int)round($crop['h']));
		try {
			$avatar = $this->avatarManager->getAvatar($this->userId);
			$avatar->set($image);
			// Clean up
			$this->cache->remove('tmpAvatar');
			return new JSONResponse(['status' => 'success']);
		} catch (\OC\NotSquareException $e) {
			return new JSONResponse(['data' => ['message' => $this->l->t('Crop is not square')]],
									Http::STATUS_BAD_REQUEST);
		} catch (\Exception $e) {
			$this->logger->logException($e, ['app' => 'core']);
			return new JSONResponse(['data' => ['message' => $this->l->t('An error occurred. Please contact your admin.')]], Http::STATUS_BAD_REQUEST);
		}
	}
}
