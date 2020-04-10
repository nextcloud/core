<?php
/**
 *
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

namespace OCA\Files_Sharing\Controller;

use OCA\Files_External\NotFoundException;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\IRequest;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;

class ShareInfoController extends ApiController {

	/** @var IManager */
	private $shareManager;

	/**
	 * ShareInfoController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IManager $shareManager
	 */
	public function __construct($appName,
								IRequest $request,
								IManager $shareManager) {
		parent::__construct($appName, $request);

		$this->shareManager = $shareManager;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $t
	 * @param null $password
	 * @param null $dir
	 * @return JSONResponse
	 * @throws ShareNotFound
	 */
	public function info($t, $password = null, $dir = null) {
		try {
			$share = $this->shareManager->getShareByToken($t);
		} catch (ShareNotFound $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($share->getPassword() && !$this->shareManager->checkPassword($share, $password)) {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if (!($share->getPermissions() & Constants::PERMISSION_READ)) {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$permissionMask = $share->getPermissions();
		$node = $share->getNode();

		if ($dir !== null && $node instanceof Folder) {
			try {
				$node = $node->get($dir);
			} catch (NotFoundException $e) {

			}
		}

		return new JSONResponse($this->parseNode($node, $permissionMask));
	}

	private function parseNode(Node $node, int $permissionMask) {
		if ($node instanceof File) {
			return $this->parseFile($node, $permissionMask);
		}
		return $this->parseFolder($node, $permissionMask);
	}

	private function parseFile(File $file, int $permissionMask) {
		return $this->format($file, $permissionMask);
	}

	private function parseFolder(Folder $folder, int $permissionMask) {
		$data = $this->format($folder, $permissionMask);

		$data['children'] = [];

		$nodes = $folder->getDirectoryListing();
		foreach ($nodes as $node) {
			$data['children'][] = $this->parseNode($node, $permissionMask);
		}

		return $data;
	}

	private function format(Node $node, int $permissionMask) {
		$entry = [];

		$entry['id'] = $node->getId();
		$entry['parentId'] = $node->getParent()->getId();
		$entry['mtime'] = $node->getMTime();

		$entry['name'] = $node->getName();
		$entry['permissions'] = $node->getPermissions() & $permissionMask;
		$entry['mimetype'] = $node->getMimetype();
		$entry['size'] = $node->getSize();
		$entry['type'] = $node->getType();
		$entry['etag'] = $node->getEtag();

		return $entry;
	}
}
