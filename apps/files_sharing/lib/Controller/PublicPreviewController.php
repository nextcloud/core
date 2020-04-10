<?php
/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\PublicShareController;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\IPreview;
use OCP\IRequest;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;

class PublicPreviewController extends PublicShareController {

	/** @var ShareManager */
	private $shareManager;

	/** @var IPreview */
	private $previewManager;

	/** @var IShare */
	private $share;

	public function __construct(string $appName,
								IRequest $request,
								ShareManager $shareManger,
								ISession $session,
								IPreview $previewManager) {
		parent::__construct($appName, $request, $session);

		$this->shareManager = $shareManger;
		$this->previewManager = $previewManager;
	}

	protected function getPasswordHash(): string {
		return $this->share->getPassword();
	}

	public function isValidToken(): bool {
		try {
			$this->share = $this->shareManager->getShareByToken($this->getToken());
			return true;
		} catch (ShareNotFound $e) {
			return false;
		}
	}

	protected function isPasswordProtected(): bool {
		return $this->share->getPassword() !== null;
	}


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $file
	 * @param int $x
	 * @param int $y
	 * @param bool $a
	 * @return DataResponse|FileDisplayResponse
	 */
	public function getPreview(
		string $token,
		string $file = '',
		int $x = 32,
		int $y = 32,
		$a = false
	) {

		if ($token === '' || $x === 0 || $y === 0) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$share = $this->shareManager->getShareByToken($token);
		} catch (ShareNotFound $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (($share->getPermissions() & Constants::PERMISSION_READ) === 0) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$node = $share->getNode();
			if ($node instanceof Folder) {
				$file = $node->get($file);
			} else {
				$file = $node;
			}

			$f = $this->previewManager->getPreview($file, $x, $y, !$a);
			$response = new FileDisplayResponse($f, Http::STATUS_OK, ['Content-Type' => $f->getMimeType()]);
			$response->cacheFor(3600 * 24);
			return $response;
		} catch (NotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * @param $token
	 * @return DataResponse|FileDisplayResponse
	 */
	public function directLink($token) {
		// No token no image
		if ($token === '') {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		// No share no image
		try {
			$share = $this->shareManager->getShareByToken($token);
		} catch (ShareNotFound $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		// No permissions no image
		if (($share->getPermissions() & Constants::PERMISSION_READ) === 0) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		// Password protected shares have no direct link!
		if ($share->getPassword() !== null) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$node = $share->getNode();
			if ($node instanceof Folder) {
				// Direct link only works for single files
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}

			$f = $this->previewManager->getPreview($node, -1, -1, false);
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
