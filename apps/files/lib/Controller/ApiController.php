<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Felix Nüsse <Felix.nuesse@t-online.de>
 * @author fnuesse <felix.nuesse@t-online.de>
 * @author fnuesse <fnuesse@techfak.uni-bielefeld.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Max Kovalenko <mxss1998@yandex.ru>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Nina Pypchenko <22447785+nina-py@users.noreply.github.com>
 * @author Richard Steinmetz <richard@steinmetz.cloud>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tobias Kaminsky <tobias@kaminsky.me>
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

use OC\Files\Node\Node;
use OCA\Files\Capabilities;
use OCA\Files\Service\TagService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCP\Share\IShare;

/**
 * Class ApiController
 *
 * @package OCA\Files\Controller
 */
class ApiController extends Controller {
	/** @var TagService */
	private $tagService;
	/** @var IManager * */
	private $shareManager;
	/** @var IPreview */
	private $previewManager;
	/** @var IUserSession */
	private $userSession;
	/** @var IConfig */
	private $config;
	/** @var Folder */
	private $userFolder;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IUserSession $userSession
	 * @param TagService $tagService
	 * @param IPreview $previewManager
	 * @param IManager $shareManager
	 * @param IConfig $config
	 * @param Folder $userFolder
	 */
	public function __construct($appName,
								IRequest $request,
								IUserSession $userSession,
								TagService $tagService,
								IPreview $previewManager,
								IManager $shareManager,
								IConfig $config,
								Folder $userFolder) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
		$this->tagService = $tagService;
		$this->previewManager = $previewManager;
		$this->shareManager = $shareManager;
		$this->config = $config;
		$this->userFolder = $userFolder;
	}

	/**
	 * Gets a thumbnail of the specified file
	 *
	 * @since API version 1.0
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @StrictCookieRequired
	 *
	 * @param int $x
	 * @param int $y
	 * @param string $file URL-encoded filename
	 * @return DataResponse|FileDisplayResponse
	 */
	public function getThumbnail($x, $y, $file) {
		if ($x < 1 || $y < 1) {
			return new DataResponse(['message' => 'Requested size must be numeric and a positive value.'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$file = $this->userFolder->get($file);
			if ($file instanceof Folder) {
				throw new NotFoundException();
			}

			/** @var File $file */
			$preview = $this->previewManager->getPreview($file, $x, $y, true);

			return new FileDisplayResponse($preview, Http::STATUS_OK, ['Content-Type' => $preview->getMimeType()]);
		} catch (NotFoundException $e) {
			return new DataResponse(['message' => 'File not found.'], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Updates the info of the specified file path
	 * The passed tags are absolute, which means they will
	 * replace the actual tag selection.
	 *
	 * @NoAdminRequired
	 *
	 * @param string $path path
	 * @param array|string $tags array of tags
	 * @return DataResponse
	 */
	public function updateFileTags($path, $tags = null) {
		$result = [];
		// if tags specified or empty array, update tags
		if (!is_null($tags)) {
			try {
				$this->tagService->updateFileTags($path, $tags);
			} catch (\OCP\Files\NotFoundException $e) {
				return new DataResponse([
					'message' => $e->getMessage()
				], Http::STATUS_NOT_FOUND);
			} catch (\OCP\Files\StorageNotAvailableException $e) {
				return new DataResponse([
					'message' => $e->getMessage()
				], Http::STATUS_SERVICE_UNAVAILABLE);
			} catch (\Exception $e) {
				return new DataResponse([
					'message' => $e->getMessage()
				], Http::STATUS_NOT_FOUND);
			}
			$result['tags'] = $tags;
		}
		return new DataResponse($result);
	}

	/**
	 * @param \OCP\Files\Node[] $nodes
	 * @return array
	 */
	private function formatNodes(array $nodes) {
		$shareTypesForNodes = $this->getShareTypesForNodes($nodes);
		return array_values(array_map(function (Node $node) use ($shareTypesForNodes) {
			$shareTypes = $shareTypesForNodes[$node->getId()] ?? [];
			$file = \OCA\Files\Helper::formatFileInfo($node->getFileInfo());
			$file['hasPreview'] = $this->previewManager->isAvailable($node);
			$parts = explode('/', dirname($node->getPath()), 4);
			if (isset($parts[3])) {
				$file['path'] = '/' . $parts[3];
			} else {
				$file['path'] = '/';
			}
			if (!empty($shareTypes)) {
				$file['shareTypes'] = $shareTypes;
			}
			return $file;
		}, $nodes));
	}

	/**
	 * Get the share types for each node
	 *
	 * @param \OCP\Files\Node[] $nodes
	 * @return array<int, int[]> list of share types for each fileid
	 */
	private function getShareTypesForNodes(array $nodes): array {
		$userId = $this->userSession->getUser()->getUID();
		$requestedShareTypes = [
			IShare::TYPE_USER,
			IShare::TYPE_GROUP,
			IShare::TYPE_LINK,
			IShare::TYPE_REMOTE,
			IShare::TYPE_EMAIL,
			IShare::TYPE_ROOM,
			IShare::TYPE_DECK,
		];
		$shareTypes = [];

		$nodeIds = array_map(function (Node $node) {
			return $node->getId();
		}, $nodes);

		foreach ($requestedShareTypes as $shareType) {
			$nodesLeft = array_combine($nodeIds, array_fill(0, count($nodeIds), true));
			$offset = 0;

			// fetch shares until we've either found shares for all nodes or there are no more shares left
			while (count($nodesLeft) > 0) {
				$shares = $this->shareManager->getSharesBy($userId, $shareType, null, false, 100, $offset);
				foreach ($shares as $share) {
					$fileId = $share->getNodeId();
					if (isset($nodesLeft[$fileId])) {
						if (!isset($shareTypes[$fileId])) {
							$shareTypes[$fileId] = [];
						}
						$shareTypes[$fileId][] = $shareType;
						unset($nodesLeft[$fileId]);
					}
				}

				if (count($shares) < 100) {
					break;
				} else {
					$offset += count($shares);
				}
			}
		}
		return $shareTypes;
	}

	/**
	 * Returns a list of recently modifed files.
	 *
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getRecentFiles() {
		$nodes = $this->userFolder->getRecent(100);
		$files = $this->formatNodes($nodes);
		return new DataResponse(['files' => $files]);
	}

	/**
	 * Change the default sort mode
	 *
	 * @NoAdminRequired
	 *
	 * @param string $mode
	 * @param string $direction
	 * @return Response
	 */
	public function updateFileSorting($mode, $direction): Response {
		if (!in_array($mode, Capabilities::SORTING_MODES) || !in_array($direction, Capabilities::SORTING_DIRECTIONS)) {
			$response = new Response();
			$response->setStatus(Http::STATUS_UNPROCESSABLE_ENTITY);
			return $response;
		}
		$this->config->setUserValue($this->userSession->getUser()->getUID(), 'files', 'file_sorting', $mode);
		$this->config->setUserValue($this->userSession->getUser()->getUID(), 'files', 'file_sorting_direction', $direction);
		return new Response();
	}


	/**
	 * Get the default sort mode
	 *
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 */
	public function getFileSorting(): JSONResponse {
		$file_sorting = $this->config->getUserValue($this->userSession->getUser()->getUID(), 'files', 'file_sorting', Capabilities::SORTING_MODES[0]);
		$file_sorting_direction = $this->config->getUserValue($this->userSession->getUser()->getUID(), 'files', 'file_sorting_direction', Capabilities::SORTING_DIRECTIONS[0]);
		return new JSONResponse([
			'file_sorting' => $file_sorting,
			'file_sorting_direction' => $file_sorting_direction
		]);
	}

	/**
	 * Toggle default for showing/hiding hidden files
	 *
	 * @NoAdminRequired
	 *
	 * @param bool $show
	 * @return Response
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function showHiddenFiles(bool $show): Response {
		$this->config->setUserValue($this->userSession->getUser()->getUID(), 'files', 'show_hidden', $show ? '1' : '0');
		return new Response();
	}

	/**
	 * Toggle default for cropping preview images
	 *
	 * @NoAdminRequired
	 *
	 * @param bool $crop
	 * @return Response
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function cropImagePreviews(bool $crop): Response {
		$this->config->setUserValue($this->userSession->getUser()->getUID(), 'files', 'crop_image_previews', $crop ? '1' : '0');
		return new Response();
	}

	/**
	 * Toggle default for files grid view
	 *
	 * @NoAdminRequired
	 *
	 * @param bool $show
	 * @return Response
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function showGridView(bool $show): Response {
		$this->config->setUserValue($this->userSession->getUser()->getUID(), 'files', 'show_grid', $show ? '1' : '0');
		return new Response();
	}

	/**
	 * Get default settings for the grid view
	 *
	 * @NoAdminRequired
	 */
	public function getGridView() {
		$status = $this->config->getUserValue($this->userSession->getUser()->getUID(), 'files', 'show_grid', '0') === '1';
		return new JSONResponse(['gridview' => $status]);
	}

	/**
	 * Toggle default for showing/hiding xxx folder
	 *
	 * @NoAdminRequired
	 *
	 * @param int $show
	 * @param string $key the key of the folder
	 *
	 * @return Response
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function toggleShowFolder(int $show, string $key): Response {
		// ensure the edited key exists
		$navItems = \OCA\Files\App::getNavigationManager()->getAll();
		foreach ($navItems as $item) {
			// check if data is valid
			if (($show === 0 || $show === 1) && isset($item['expandedState']) && $key === $item['expandedState']) {
				$this->config->setUserValue($this->userSession->getUser()->getUID(), 'files', $key, (string)$show);
				return new Response();
			}
		}
		$response = new Response();
		$response->setStatus(Http::STATUS_FORBIDDEN);
		return $response;
	}

	/**
	 * Get sorting-order for custom sorting
	 *
	 * @NoAdminRequired
	 *
	 * @param string $folderpath
	 * @return string
	 * @throws \OCP\Files\NotFoundException
	 */
	public function getNodeType($folderpath) {
		$node = $this->userFolder->get($folderpath);
		return $node->getType();
	}
}
