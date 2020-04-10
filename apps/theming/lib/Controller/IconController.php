<?php
/**
 * @copyright Copyright (c) 2016 Julius Haertl <jus@bitgrid.net>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Michael Weimann <mail@michael-weimann.eu>
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

namespace OCA\Theming\Controller;

use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Theming\IconBuilder;
use OCA\Theming\ImageManager;
use OCA\Theming\ThemingDefaults;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\Files\NotFoundException;
use OCP\IRequest;

class IconController extends Controller {
	/** @var ThemingDefaults */
	private $themingDefaults;
	/** @var IconBuilder */
	private $iconBuilder;
	/** @var ImageManager */
	private $imageManager;
	/** @var FileAccessHelper */
	private $fileAccessHelper;

	/**
	 * IconController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param ThemingDefaults $themingDefaults
	 * @param IconBuilder $iconBuilder
	 * @param ImageManager $imageManager
	 * @param FileAccessHelper $fileAccessHelper
	 */
	public function __construct(
		$appName,
		IRequest $request,
		ThemingDefaults $themingDefaults,
		IconBuilder $iconBuilder,
		ImageManager $imageManager,
		FileAccessHelper $fileAccessHelper
	) {
		parent::__construct($appName, $request);

		$this->themingDefaults = $themingDefaults;
		$this->iconBuilder = $iconBuilder;
		$this->imageManager = $imageManager;
		$this->fileAccessHelper = $fileAccessHelper;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param $app string app name
	 * @param $image string image file name (svg required)
	 * @return FileDisplayResponse|NotFoundResponse
	 * @throws \Exception
	 */
	public function getThemedIcon(string $app, string $image): Response {
		try {
			$iconFile = $this->imageManager->getCachedImage('icon-' . $app . '-' . str_replace('/', '_',$image));
		} catch (NotFoundException $exception) {
			$icon = $this->iconBuilder->colorSvg($app, $image);
			if ($icon === false || $icon === '') {
				return new NotFoundResponse();
			}
			$iconFile = $this->imageManager->setCachedImage('icon-' . $app . '-' . str_replace('/', '_',$image), $icon);
		}
		if ($iconFile !== false) {
			$response = new FileDisplayResponse($iconFile, Http::STATUS_OK, ['Content-Type' => 'image/svg+xml']);
			$response->cacheFor(86400);
			return $response;
		}

		return new NotFoundResponse();
	}

	/**
	 * Return a 32x32 favicon as png
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param $app string app name
	 * @return FileDisplayResponse|DataDisplayResponse
	 * @throws \Exception
	 */
	public function getFavicon(string $app = 'core'): Response {
		$response = null;
		$iconFile = null;
		try {
			$iconFile = $this->imageManager->getImage('favicon', false);
			$response = new FileDisplayResponse($iconFile, Http::STATUS_OK, ['Content-Type' => 'image/x-icon']);
		} catch (NotFoundException $e) {
		}
		if ($iconFile === null && $this->imageManager->shouldReplaceIcons()) {
			try {
				$iconFile = $this->imageManager->getCachedImage('favIcon-' . $app);
			} catch (NotFoundException $exception) {
				$icon = $this->iconBuilder->getFavicon($app);
				$iconFile = $this->imageManager->setCachedImage('favIcon-' . $app, $icon);
			}
			if ($iconFile !== false) {
				$response = new FileDisplayResponse($iconFile, Http::STATUS_OK, ['Content-Type' => 'image/x-icon']);
			}
		}
		if($response === null) {
			$fallbackLogo = \OC::$SERVERROOT . '/core/img/favicon.png';
			$response = new DataDisplayResponse($this->fileAccessHelper->file_get_contents($fallbackLogo), Http::STATUS_OK, ['Content-Type' => 'image/x-icon']);
		}
		$response->cacheFor(86400);
		return $response;
	}

	/**
	 * Return a 512x512 icon for touch devices
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param $app string app name
	 * @return FileDisplayResponse|NotFoundResponse
	 * @throws \Exception
	 */
	public function getTouchIcon(string $app = 'core'): Response {
		$response = null;
		try {
			$iconFile = $this->imageManager->getImage('favicon');
			$response = new FileDisplayResponse($iconFile, Http::STATUS_OK, ['Content-Type' => 'image/x-icon']);
		} catch (NotFoundException $e) {
		}
		if ($this->imageManager->shouldReplaceIcons()) {
			try {
				$iconFile = $this->imageManager->getCachedImage('touchIcon-' . $app);
			} catch (NotFoundException $exception) {
				$icon = $this->iconBuilder->getTouchIcon($app);
				$iconFile = $this->imageManager->setCachedImage('touchIcon-' . $app, $icon);
			}
			if ($iconFile !== false) {
				$response = new FileDisplayResponse($iconFile, Http::STATUS_OK, ['Content-Type' => 'image/png']);
			}
		}
		if($response === null) {
			$fallbackLogo = \OC::$SERVERROOT . '/core/img/favicon-touch.png';
			$response = new DataDisplayResponse($this->fileAccessHelper->file_get_contents($fallbackLogo), Http::STATUS_OK, ['Content-Type' => 'image/png']);
		}
		$response->cacheFor(86400);
		return $response;
	}
}
