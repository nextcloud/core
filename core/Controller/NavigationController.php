<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IURLGenerator;

class NavigationController extends OCSController {

	/** @var INavigationManager */
	private $navigationManager;

	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(string $appName, IRequest $request, INavigationManager $navigationManager, IURLGenerator $urlGenerator) {
		parent::__construct($appName, $request);
		$this->navigationManager = $navigationManager;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param bool $absolute
	 * @return DataResponse
	 */
	public function getAppsNavigation(bool $absolute = false): DataResponse {
		$navigation = $this->navigationManager->getAll();
		if ($absolute) {
			$navigation = $this->rewriteToAbsoluteUrls($navigation);
		}
		$navigation = array_values($navigation);
		$etag = $this->generateETag($navigation);
		if ($this->request->getHeader('If-None-Match') === $etag) {
			return new DataResponse([], Http::STATUS_NOT_MODIFIED);
		}
		$response = new DataResponse($navigation);
		$response->setETag($etag);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param bool $absolute
	 * @return DataResponse
	 */
	public function getSettingsNavigation(bool $absolute = false): DataResponse {
		$navigation = $this->navigationManager->getAll('settings');
		if ($absolute) {
			$navigation = $this->rewriteToAbsoluteUrls($navigation);
		}
		$navigation = array_values($navigation);
		$etag = $this->generateETag($navigation);
		if ($this->request->getHeader('If-None-Match') === $etag) {
			return new DataResponse([], Http::STATUS_NOT_MODIFIED);
		}
		$response = new DataResponse($navigation);
		$response->setETag($etag);
		return $response;
	}

	/**
	 * Generate an ETag for a list of navigation entries
	 *
	 * @param array $navigation
	 * @return string
	 */
	private function generateETag(array $navigation): string {
		foreach ($navigation as &$nav) {
			if ($nav['id'] === 'logout') {
				$nav['href'] = 'logout';
			}
		}
		return md5(json_encode($navigation));
	}

	/**
	 * Rewrite href attribute of navigation entries to an absolute URL
	 *
	 * @param array $navigation
	 * @return array
	 */
	private function rewriteToAbsoluteUrls(array $navigation): array {
		foreach ($navigation as &$entry) {
			if (0 !== strpos($entry['href'], $this->urlGenerator->getBaseUrl())) {
				$entry['href'] = $this->urlGenerator->getAbsoluteURL($entry['href']);
			}
			if (0 !== strpos($entry['icon'], $this->urlGenerator->getBaseUrl())) {
				$entry['icon'] = $this->urlGenerator->getAbsoluteURL($entry['icon']);
			}
		}
		return $navigation;
	}
}
