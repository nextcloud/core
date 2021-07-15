<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Robin Appelman <robin@icewind.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\DAV\Connector\Sabre;

use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class ChecksumUpdatePlugin extends ServerPlugin {
	/**
	 * @var \Sabre\DAV\Server
	 */
	protected $server;

	public function initialize(\Sabre\DAV\Server $server) {
		$this->server = $server;
		$server->on('method:PATCH', [$this, 'httpPatch']);
	}

	public function getPluginName(): string {
		return 'checksumupdate';
	}

	public function getHTTPMethods($uri): array {
		$tree = $this->server->tree;

		if ($tree->nodeExists($uri)) {
			$node = $tree->getNodeForPath($uri);
			if ($node instanceof File) {
				return ['PATCH'];
			}
		}

		return [];
	}

	public function getFeatures(): array {
		return ['nextcloud-checksum-update'];
	}

	public function httpPatch(RequestInterface $request, ResponseInterface $response) {
		$path = $request->getPath();

		$node = $this->server->tree->getNodeForPath($path);
		if ($node instanceof File) {
			$type = strtolower(
				(string)$request->getHeader('X-Recalculate-Hash')
			);

			$hash = $node->hash($type);
			if ($hash) {
				$checksum = strtoupper($type) . ':' . $hash;
				$node->setChecksum($checksum);
				$response->addHeader('OC-Checksum', $checksum);
				$response->setHeader('Content-Length', '0');
				$response->setStatus(204);

				return false;
			}
		}
	}
}
