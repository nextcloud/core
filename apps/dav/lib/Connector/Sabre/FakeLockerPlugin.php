<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

use Sabre\DAV\INode;
use Sabre\DAV\Locks\LockInfo;
use Sabre\DAV\PropFind;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\Xml\Property\LockDiscovery;
use Sabre\DAV\Xml\Property\SupportedLock;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * Class FakeLockerPlugin is a plugin only used when connections come in from
 * OS X via Finder. The fake locking plugin does emulate Class 2 WebDAV support
 * (locking of files) which allows Finder to access the storage in write mode as
 * well.
 *
 * No real locking is performed, instead the plugin just returns always positive
 * responses.
 *
 * @see https://github.com/owncloud/core/issues/17732
 * @package OCA\DAV\Connector\Sabre
 */
class FakeLockerPlugin extends ServerPlugin {
	/** @var \Sabre\DAV\Server */
	private $server;

	/** {@inheritDoc} */
	public function initialize(\Sabre\DAV\Server $server) {
		$this->server = $server;
		$this->server->on('method:LOCK', [$this, 'fakeLockProvider'], 1);
		$this->server->on('method:UNLOCK', [$this, 'fakeUnlockProvider'], 1);
		$server->on('propFind', [$this, 'propFind']);
		$server->on('validateTokens', [$this, 'validateTokens']);
	}

	/**
	 * Indicate that we support LOCK and UNLOCK
	 *
	 * @param string $path
	 * @return string[]
	 */
	public function getHTTPMethods($path) {
		return [
			'LOCK',
			'UNLOCK',
		];
	}

	/**
	 * Indicate that we support locking
	 *
	 * @return integer[]
	 */
	function getFeatures() {
		return [2];
	}

	/**
	 * Return some dummy response for PROPFIND requests with regard to locking
	 *
	 * @param PropFind $propFind
	 * @param INode $node
	 * @return void
	 */
	function propFind(PropFind $propFind, INode $node) {
		$propFind->handle('{DAV:}supportedlock', function () {
			return new SupportedLock(true);
		});
		$propFind->handle('{DAV:}lockdiscovery', function () use ($propFind) {
			return new LockDiscovery([]);
		});
	}

	/**
	 * Mark a locking token always as valid
	 *
	 * @param RequestInterface $request
	 * @param array $conditions
	 */
	public function validateTokens(RequestInterface $request, &$conditions) {
		foreach($conditions as &$fileCondition) {
			if(isset($fileCondition['tokens'])) {
				foreach($fileCondition['tokens'] as &$token) {
					if(isset($token['token'])) {
						if(substr($token['token'], 0, 16) === 'opaquelocktoken:') {
							$token['validToken'] = true;
						}
					}
				}
			}
		}
	}

	/**
	 * Fakes a successful LOCK
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return bool
	 */
	public function fakeLockProvider(RequestInterface $request,
									 ResponseInterface $response) {

		$lockInfo = new LockInfo();
		$lockInfo->token = md5($request->getPath());
		$lockInfo->uri = $request->getPath();
		$lockInfo->depth = \Sabre\DAV\Server::DEPTH_INFINITY;
		$lockInfo->timeout = 1800;

		$body = $this->server->xml->write('{DAV:}prop', [
			'{DAV:}lockdiscovery' =>
					new LockDiscovery([$lockInfo])
		]);

		$response->setStatus(200);
		$response->setBody($body);

		return false;
	}

	/**
	 * Fakes a successful LOCK
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return bool
	 */
	public function fakeUnlockProvider(RequestInterface $request,
									 ResponseInterface $response) {
		$response->setStatus(204);
		$response->setHeader('Content-Length', '0');
		return false;
	}
}
