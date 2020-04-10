<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

use OCA\Files_Sharing\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;

class AcceptController extends Controller {

	/** @var ShareManager */
	private $shareManager;

	/** @var IUserSession */
	private $userSession;

	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(IRequest $request, ShareManager $shareManager, IUserSession $userSession, IURLGenerator $urlGenerator) {
		parent::__construct(Application::APP_ID, $request);

		$this->shareManager = $shareManager;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function accept(string $shareId): Response {
		try {
			$share = $this->shareManager->getShareById($shareId);
		} catch (ShareNotFound $e) {
			return new NotFoundResponse();
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return new NotFoundResponse();
		}

		try {
			$share = $this->shareManager->acceptShare($share, $user->getUID());
		} catch (\Exception $e) {
			// Just ignore
		}

		$url = $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $share->getNode()->getId()]);

		return new RedirectResponse($url);
	}
}
