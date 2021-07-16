<?php
/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Files_Versions\Controller;

use OCA\Files_Versions\Versions\IVersionManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IUserSession;

class PreviewController extends Controller {

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserSession */
	private $userSession;

	/** @var IVersionManager */
	private $versionManager;

	/** @var IPreview */
	private $previewManager;

	public function __construct(
		string $appName,
		IRequest $request,
		IRootFolder $rootFolder,
		IUserSession $userSession,
		IVersionManager $versionManager,
		IPreview $previewManager
	) {
		parent::__construct($appName, $request);

		$this->rootFolder = $rootFolder;
		$this->userSession = $userSession;
		$this->versionManager = $versionManager;
		$this->previewManager = $previewManager;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $file
	 * @param int $x
	 * @param int $y
	 * @param string $version
	 * @return DataResponse|FileDisplayResponse
	 */
	public function getPreview(
		string $file = '',
		int $x = 44,
		int $y = 44,
		string $version = ''
	) {
		if ($file === '' || $version === '' || $x === 0 || $y === 0) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$user = $this->userSession->getUser();
			$userFolder = $this->rootFolder->getUserFolder($user->getUID());
			$file = $userFolder->get($file);
			$versionFile = $this->versionManager->getVersionFile($user, $file, $version);
			$preview = $this->previewManager->getPreview($versionFile, $x, $y, true, IPreview::MODE_FILL, $versionFile->getMimetype());
			return new FileDisplayResponse($preview, Http::STATUS_OK, ['Content-Type' => $preview->getMimeType()]);
		} catch (NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}
}
