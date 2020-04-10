<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Jakub Onderka <ahoj@jakubonderka.cz>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author simonspa <1677436+simonspa@users.noreply.github.com>
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

namespace OCA\Files_Trashbin\Controller;

use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IUserSession;

class PreviewController extends Controller {
	/** @var IRootFolder */
	private $rootFolder;

	/** @var ITrashManager */
	private $trashManager;

	/** @var IUserSession */
	private $userSession;

	/** @var IMimeTypeDetector */
	private $mimeTypeDetector;

	/** @var IPreview */
	private $previewManager;

	/** @var ITimeFactory */
	private $time;

	public function __construct(
		string $appName,
		IRequest $request,
		IRootFolder $rootFolder,
		ITrashManager $trashManager,
		IUserSession $userSession,
		IMimeTypeDetector $mimeTypeDetector,
		IPreview $previewManager,
		ITimeFactory $time
	) {
		parent::__construct($appName, $request);

		$this->trashManager = $trashManager;
		$this->rootFolder = $rootFolder;
		$this->userSession = $userSession;
		$this->mimeTypeDetector = $mimeTypeDetector;
		$this->previewManager = $previewManager;
		$this->time = $time;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return DataResponse|Http\FileDisplayResponse
	 */
	public function getPreview(
		int $fileId = -1,
		int $x = 128,
		int $y = 128
	) {

		if ($fileId === -1 || $x === 0 || $y === 0) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$file = $this->trashManager->getTrashNodeById($this->userSession->getUser(), $fileId);
			if ($file === null) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
			if ($file instanceof Folder) {
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}

			$pathParts = pathinfo($file->getName());
			$extension = $pathParts['extension'] ?? '';
			$fileName = $pathParts['filename'];
			/*
			 * Files in the root of the trashbin are timetamped.
			 * So we have to strip that in order to properly detect the mimetype of the file.
			 */
			if (preg_match('/d\d+/', $extension)) {
				$mimeType = $this->mimeTypeDetector->detectPath($fileName);
			} else {
				$mimeType = $this->mimeTypeDetector->detectPath($file->getName());
			}

			$f = $this->previewManager->getPreview($file, $x, $y, true, IPreview::MODE_FILL, $mimeType);
			$response = new Http\FileDisplayResponse($f, Http::STATUS_OK, ['Content-Type' => $f->getMimeType()]);

			// Cache previews for 24H
			$response->cacheFor(3600 * 24);
			return $response;
		} catch (NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}
}
