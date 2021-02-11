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

namespace OC\AppFramework\Middleware;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IRequest;

class NotModifiedMiddleware extends Middleware {
	/** @var IRequest */
	private $request;

	public function __construct(IRequest $request) {
		$this->request = $request;
	}

	public function afterController($controller, $methodName, Response $response) {
		$etagHeader = $this->request->getHeader('IF_NONE_MATCH');
		if ($etagHeader !== '' && $response->getETag() !== null && trim($etagHeader) === '"' . $response->getETag() . '"') {
			$response->setStatus(Http::STATUS_NOT_MODIFIED);
			return $response;
		}

		$modifiedSinceHeader = $this->request->getHeader('IF_MODIFIED_SINCE');
		if ($modifiedSinceHeader !== '' && $response->getLastModified() !== null && trim($modifiedSinceHeader) === $response->getLastModified()->format(\DateTime::RFC2822)) {
			$response->setStatus(Http::STATUS_NOT_MODIFIED);
			return $response;
		}

		return $response;
	}
}
