<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\AppFramework\Middleware;

use OC\AppFramework\OCS\BaseResponse;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\IRequest;

class CompressionMiddleware extends Middleware {

	/** @var bool */
	private $useGZip;

	/** @var IRequest */
	private $request;

	public function __construct(IRequest $request) {
		$this->request = $request;
		$this->useGZip = false;
	}

	public function afterController($controller, $methodName, Response $response) {
		// By default we do not gzip
		$allowGzip = false;

		// Only return gzipped content for 200 responses
		if ($response->getStatus() !== Http::STATUS_OK) {
			return $response;
		}

		// Check if we are even asked for gzip
		$header = $this->request->getHeader('Accept-Encoding');
		if (strpos($header, 'gzip') === false) {
			return $response;
		}

		// We only allow gzip in some cases
		if ($response instanceof BaseResponse) {
			$allowGzip = true;
		}
		if ($response instanceof JSONResponse) {
			$allowGzip = true;
		}
		if ($response instanceof TemplateResponse) {
			$allowGzip = true;
		}

		if ($allowGzip) {
			$this->useGZip = true;
			$response->addHeader('Content-Encoding', 'gzip');
		}

		return $response;
	}

	public function beforeOutput($controller, $methodName, $output) {
		if (!$this->useGZip) {
			return $output;
		}

		return gzencode($output);
	}
}
