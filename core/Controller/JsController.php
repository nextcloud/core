<?php

declare(strict_types=1);

/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

use OC\Files\AppData\Factory;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IRequest;

class JsController extends Controller {

	/** @var IAppData */
	protected $appData;

	/** @var ITimeFactory */
	protected $timeFactory;

	public function __construct($appName, IRequest $request, Factory $appDataFactory, ITimeFactory $timeFactory) {
		parent::__construct($appName, $request);

		$this->appData = $appDataFactory->get('js');
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * @param string $fileName js filename with extension
	 * @param string $appName js folder name
	 * @return FileDisplayResponse|NotFoundResponse
	 */
	public function getJs(string $fileName, string $appName): Response {
		try {
			$folder = $this->appData->getFolder($appName);
			$gzip = false;
			$file = $this->getFile($folder, $fileName, $gzip);
		} catch(NotFoundException $e) {
			return new NotFoundResponse();
		}

		$response = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => 'application/javascript']);
		if ($gzip) {
			$response->addHeader('Content-Encoding', 'gzip');
		}

		$ttl = 31536000;
		$response->addHeader('Cache-Control', 'max-age='.$ttl.', immutable');

		$expires = new \DateTime();
		$expires->setTimestamp($this->timeFactory->getTime());
		$expires->add(new \DateInterval('PT'.$ttl.'S'));
		$response->addHeader('Expires', $expires->format(\DateTime::RFC1123));
		$response->addHeader('Pragma', 'cache');
		return $response;
	}

	/**
	 * @param ISimpleFolder $folder
	 * @param string $fileName
	 * @param bool $gzip is set to true if we use the gzip file
	 * @return ISimpleFile
	 *
	 * @throws NotFoundException
	 */
	private function getFile(ISimpleFolder $folder, string $fileName, bool &$gzip): ISimpleFile {
		$encoding = $this->request->getHeader('Accept-Encoding');

		if (strpos($encoding, 'gzip') !== false) {
			try {
				$gzip = true;
				return $folder->getFile($fileName . '.gzip'); # Safari doesn't like .gz
			} catch (NotFoundException $e) {
				// continue
			}
		}

		$gzip = false;
		return $folder->getFile($fileName);
	}
}
