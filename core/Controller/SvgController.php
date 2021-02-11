<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, John Molakvoæ (skjnldsv@protonmail.com)
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

use OC\Template\IconsCacher;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;

class SvgController extends Controller {

	/** @var string */
	protected $serverRoot;

	/** @var ITimeFactory */
	protected $timeFactory;

	/** @var IAppManager */
	protected $appManager;

	/** @var IconsCacher */
	private $iconsCacher;

	public function __construct(string $appName,
								IRequest $request,
								ITimeFactory $timeFactory,
								IAppManager $appManager,
								IconsCacher $iconsCacher) {
		parent::__construct($appName, $request);

		$this->serverRoot = \OC::$SERVERROOT;
		$this->timeFactory = $timeFactory;
		$this->appManager = $appManager;
		$this->iconsCacher = $iconsCacher;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * Generate svg from filename with the requested color
	 *
	 * @param string $folder
	 * @param string $fileName
	 * @param string $color
	 * @return DataDisplayResponse|NotFoundResponse
	 */
	public function getSvgFromCore(string $folder, string $fileName, string $color = 'ffffff') {
		$path = $this->serverRoot . "/core/img/$folder/$fileName.svg";
		return $this->getSvg($path, $color, $fileName);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * Generate svg from filename with the requested color
	 *
	 * @param string $app
	 * @param string $fileName
	 * @param string $color
	 * @return DataDisplayResponse|NotFoundResponse
	 */
	public function getSvgFromApp(string $app, string $fileName, string $color = 'ffffff') {
		try {
			$appPath = $this->appManager->getAppPath($app);
		} catch (AppPathNotFoundException $e) {
			return new NotFoundResponse();
		}

		$path = $appPath . "/img/$fileName.svg";
		return $this->getSvg($path, $color, $fileName);
	}

	/**
	 * Generate svg from filename with the requested color
	 *
	 * @param string $path
	 * @param string $color
	 * @param string $fileName
	 * @return DataDisplayResponse|NotFoundResponse
	 */
	private function getSvg(string $path, string $color, string $fileName) {
		if (!file_exists($path)) {
			return new NotFoundResponse();
		}

		$svg = file_get_contents($path);

		if ($svg === null) {
			return new NotFoundResponse();
		}

		$svg = $this->iconsCacher->colorizeSvg($svg, $color);

		$response = new DataDisplayResponse($svg, Http::STATUS_OK, ['Content-Type' => 'image/svg+xml']);

		// Set cache control
		$ttl = 31536000;
		$response->cacheFor($ttl);
		$response->addHeader('Content-Disposition', 'inline; filename="' . $fileName . '.svg"');
		$expires = new \DateTime();
		$expires->setTimestamp($this->timeFactory->getTime());
		$expires->add(new \DateInterval('PT' . $ttl . 'S'));
		$response->addHeader('Expires', $expires->format(\DateTime::RFC1123));
		$response->addHeader('Pragma', 'cache');

		return $response;
	}
}
