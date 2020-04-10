<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Core\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IPreview;
use OCP\IRequest;

class PreviewController extends Controller {

	/** @var string */
	private $userId;

	/** @var IRootFolder */
	private $root;

	/** @var IPreview */
	private $preview;

	/**
	 * PreviewController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IPreview $preview
	 * @param IRootFolder $root
	 * @param string $userId
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(string $appName,
								IRequest $request,
								IPreview $preview,
								IRootFolder $root,
								?string $userId
	) {
		parent::__construct($appName, $request);

		$this->preview = $preview;
		$this->root = $root;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $file
	 * @param int $x
	 * @param int $y
	 * @param bool $a
	 * @param bool $forceIcon
	 * @param string $mode
	 * @return DataResponse|FileDisplayResponse
	 */
	public function getPreview(
		string $file = '',
		int $x = 32,
		int $y = 32,
		bool $a = false,
		bool $forceIcon = true,
		string $mode = 'fill'): Http\Response {

		if ($file === '' || $x === 0 || $y === 0) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$userFolder = $this->root->getUserFolder($this->userId);
			$node = $userFolder->get($file);
		} catch (NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		return $this->fetchPreview($node, $x, $y, $a, $forceIcon, $mode);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $fileId
	 * @param int $x
	 * @param int $y
	 * @param bool $a
	 * @param bool $forceIcon
	 * @param string $mode
	 *
	 * @return DataResponse|FileDisplayResponse
	 */
	public function getPreviewByFileId(
		int $fileId = -1,
		int $x = 32,
		int $y = 32,
		bool $a = false,
		bool $forceIcon = true,
		string $mode = 'fill') {

		if ($fileId === -1 || $x === 0 || $y === 0) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$userFolder = $this->root->getUserFolder($this->userId);
		$nodes = $userFolder->getById($fileId);

		if (\count($nodes) === 0) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$node = array_pop($nodes);

		return $this->fetchPreview($node, $x, $y, $a, $forceIcon, $mode);
	}

	/**
	 * @param Node $node
	 * @param int $x
	 * @param int $y
	 * @param bool $a
	 * @param bool $forceIcon
	 * @param string $mode
	 * @return DataResponse|FileDisplayResponse
	 */
	private function fetchPreview(
		Node $node,
		int $x,
		int $y,
		bool $a = false,
		bool $forceIcon = true,
		string $mode) : Http\Response {

		if (!($node instanceof File) || (!$forceIcon && !$this->preview->isAvailable($node))) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		if (!$node->isReadable()) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$f = $this->preview->getPreview($node, $x, $y, !$a, $mode);
			$response = new FileDisplayResponse($f, Http::STATUS_OK, ['Content-Type' => $f->getMimeType()]);
			$response->cacheFor(3600 * 24);
			return $response;
		} catch (NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

	}
}
