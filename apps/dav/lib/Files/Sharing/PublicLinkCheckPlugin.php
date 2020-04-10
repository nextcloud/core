<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\DAV\Files\Sharing;

use OCP\Files\FileInfo;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * Verify that the public link share is valid
 */
class PublicLinkCheckPlugin extends ServerPlugin {
	/**
	 * @var FileInfo
	 */
	private $fileInfo;

	/**
	 * @param FileInfo $fileInfo
	 */
	public function setFileInfo($fileInfo) {
		$this->fileInfo = $fileInfo;
	}

	/**
	 * This initializes the plugin.
	 *
	 * @param \Sabre\DAV\Server $server Sabre server
	 *
	 * @return void
	 */
	public function initialize(\Sabre\DAV\Server $server) {
		$server->on('beforeMethod:*', [$this, 'beforeMethod']);
	}

	public function beforeMethod(RequestInterface $request, ResponseInterface $response) {
		// verify that the owner didn't have his share permissions revoked
		if ($this->fileInfo && !$this->fileInfo->isShareable()) {
			throw new NotFound();
		}
	}
}
