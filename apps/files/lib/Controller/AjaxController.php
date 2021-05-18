<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files\Controller;

use OC_Files;
use OCA\Files\Helper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\NotFoundException;
use OCP\Files\Utils\IDownloadManager;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;

class AjaxController extends Controller {
	/** @var ISession */
	private $session;
	/** @var IConfig */
	private $config;

	/** @var IDownloadManager */
	private $downloadManager;

	public function __construct(
		string $appName,
		IRequest $request,
		ISession $session,
		IConfig $config,
		IDownloadManager $downloadManager
	) {
		parent::__construct($appName, $request);
		$this->session = $session;
		$this->request = $request;
		$this->config = $config;
		$this->downloadManager = $downloadManager;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getStorageStats(string $dir = '/'): JSONResponse {
		\OC_Util::setupFS();
		try {
			return new JSONResponse([
				'status' => 'success',
				'data' => Helper::buildFileStorageStatistics($dir),
			]);
		} catch (NotFoundException $e) {
			return new JSONResponse([
				'status' => 'error',
				'data' => [
					'message' => 'Folder not found'
				],
			]);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function registerDownload($files, string $dir = '', string $downloadStartSecret = '') {
		if (is_string($files)) {
			$files = [$files];
		} elseif (!is_array($files)) {
			throw new \InvalidArgumentException('Invalid argument for files');
		}

		$token = $this->downloadManager->register([
			'files' => $files,
			'dir' => $dir,
			'downloadStartSecret' => $downloadStartSecret,
		]);

		return new JSONResponse(['token' => $token]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function download(string $token) {

		$data = $this->downloadManager->retrieve($token);
		$this->session->close();

		if (strlen($data['downloadStartSecret']) <= 32
			&& (preg_match('!^[a-zA-Z0-9]+$!', $data['downloadStartSecret']) === 1)
		) {
			setcookie('ocDownloadStarted', $data['downloadStartSecret'], time() + 20, '/');
		}

		$serverParams = [ 'head' => $this->request->getMethod() === 'HEAD' ];
		if (isset($_SERVER['HTTP_RANGE'])) {
			$serverParams['range'] = $this->request->getHeader('Range');
		}

		OC_Files::get($data['dir'], $data['files'], $serverParams);
	}
}
