<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Connector\Sabre;

use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * Copies the "Etag" header to "OC-Etag" after any request.
 * This is a workaround for setups that automatically strip
 * or mangle Etag headers.
 */
class CopyEtagHeaderPlugin extends \Sabre\DAV\ServerPlugin {

	/** @var \Sabre\DAV\Server */
	private $server;
	/**
	 * This initializes the plugin.
	 *
	 * @param \Sabre\DAV\Server $server Sabre server
	 *
	 * @return void
	 */
	public function initialize(\Sabre\DAV\Server $server) {
		$this->server = $server;

		$server->on('afterMethod:*', [$this, 'afterMethod']);
		$server->on('afterMove', [$this, 'afterMove']);
	}

	/**
	 * After method, copy the "Etag" header to "OC-Etag" header.
	 *
	 * @param RequestInterface $request request
	 * @param ResponseInterface $response response
	 */
	public function afterMethod(RequestInterface $request, ResponseInterface $response) {
		$eTag = $response->getHeader('Etag');
		if (!empty($eTag)) {
			$response->setHeader('OC-ETag', $eTag);
		}
	}

	/**
	 * Called after a node is moved.
	 *
	 * This allows the backend to move all the associated properties.
	 *
	 * @param string $source
	 * @param string $destination
	 * @return void
	 */
	public function afterMove($source, $destination) {
		$node = $this->server->tree->getNodeForPath($destination);
		if ($node instanceof File) {
			$eTag = $node->getETag();
			$this->server->httpResponse->setHeader('OC-ETag', $eTag);
			$this->server->httpResponse->setHeader('ETag', $eTag);
		}
	}
}
