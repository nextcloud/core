<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Felix Moeller <mail@felixmoeller.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author scambra <sergio@entrecables.com>
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

use OCA\DAV\Upload\FutureFile;
use OCP\Files\StorageNotAvailableException;
use Sabre\DAV\Exception\InsufficientStorage;
use Sabre\DAV\Exception\ServiceUnavailable;
use Sabre\DAV\INode;

/**
 * This plugin check user quota and deny creating files when they exceeds the quota.
 *
 * @author Sergio Cambra
 * @copyright Copyright (C) 2012 entreCables S.L. All rights reserved.
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class QuotaPlugin extends \Sabre\DAV\ServerPlugin {

	/** @var \OC\Files\View */
	private $view;

	/**
	 * Reference to main server object
	 *
	 * @var \Sabre\DAV\Server
	 */
	private $server;

	/**
	 * @param \OC\Files\View $view
	 */
	public function __construct($view) {
		$this->view = $view;
	}

	/**
	 * This initializes the plugin.
	 *
	 * This function is called by \Sabre\DAV\Server, after
	 * addPlugin is called.
	 *
	 * This method should set up the requires event subscriptions.
	 *
	 * @param \Sabre\DAV\Server $server
	 * @return void
	 */
	public function initialize(\Sabre\DAV\Server $server) {
		$this->server = $server;

		$server->on('beforeWriteContent', [$this, 'beforeWriteContent'], 10);
		$server->on('beforeCreateFile', [$this, 'beforeCreateFile'], 10);
		$server->on('beforeMove', [$this, 'beforeMove'], 10);
	}

	/**
	 * Check quota before creating file
	 *
	 * @param string $uri target file URI
	 * @param resource $data data
	 * @param INode $parent Sabre Node
	 * @param bool $modified modified
	 */
	public function beforeCreateFile($uri, $data, INode $parent, $modified) {
		if (!$parent instanceof Node) {
			return;
		}

		return $this->checkQuota($parent->getPath() . '/' . basename($uri));
	}

	/**
	 * Check quota before writing content
	 *
	 * @param string $uri target file URI
	 * @param INode $node Sabre Node
	 * @param resource $data data
	 * @param bool $modified modified
	 */
	public function beforeWriteContent($uri, INode $node, $data, $modified) {
		if (!$node instanceof Node) {
			return;
		}

		return $this->checkQuota($node->getPath());
	}

	/**
	 * Check if we're moving a Futurefile in which case we need to check
	 * the quota on the target destination.
	 *
	 * @param string $source source path
	 * @param string $destination destination path
	 */
	public function beforeMove($source, $destination) {
		$sourceNode = $this->server->tree->getNodeForPath($source);
		if (!$sourceNode instanceof FutureFile) {
			return;
		}

		// get target node for proper path conversion
		if ($this->server->tree->nodeExists($destination)) {
			$destinationNode = $this->server->tree->getNodeForPath($destination);
			$path = $destinationNode->getPath();
		} else {
			$parentNode = $this->server->tree->getNodeForPath(dirname($destination));
			$path = $parentNode->getPath();
		}

		return $this->checkQuota($path, $sourceNode->getSize());
	}


	/**
	 * This method is called before any HTTP method and validates there is enough free space to store the file
	 *
	 * @param string $path relative to the users home
	 * @param int $length
	 * @throws InsufficientStorage
	 * @return bool
	 */
	public function checkQuota($path, $length = null) {
		if ($length === null) {
			$length = $this->getLength();
		}

		if ($length) {
			list($parentPath, $newName) = \Sabre\Uri\split($path);
			if (is_null($parentPath)) {
				$parentPath = '';
			}
			$req = $this->server->httpRequest;
			if ($req->getHeader('OC-Chunked')) {
				$info = \OC_FileChunking::decodeName($newName);
				$chunkHandler = $this->getFileChunking($info);
				// subtract the already uploaded size to see whether
				// there is still enough space for the remaining chunks
				$length -= $chunkHandler->getCurrentSize();
				// use target file name for free space check in case of shared files
				$path = rtrim($parentPath, '/') . '/' . $info['name'];
			}
			$freeSpace = $this->getFreeSpace($path);
			if ($freeSpace >= 0 && $length > $freeSpace) {
				if (isset($chunkHandler)) {
					$chunkHandler->cleanup();
				}
				throw new InsufficientStorage("Insufficient space in $path, $length required, $freeSpace available");
			}
		}
		return true;
	}

	public function getFileChunking($info) {
		// FIXME: need a factory for better mocking support
		return new \OC_FileChunking($info);
	}

	public function getLength() {
		$req = $this->server->httpRequest;
		$length = $req->getHeader('X-Expected-Entity-Length');
		if (!is_numeric($length)) {
			$length = $req->getHeader('Content-Length');
			$length = is_numeric($length) ? $length : null;
		}

		$ocLength = $req->getHeader('OC-Total-Length');
		if (is_numeric($length) && is_numeric($ocLength)) {
			return max($length, $ocLength);
		}

		return $length;
	}

	/**
	 * @param string $uri
	 * @return mixed
	 * @throws ServiceUnavailable
	 */
	public function getFreeSpace($uri) {
		try {
			$freeSpace = $this->view->free_space(ltrim($uri, '/'));
			return $freeSpace;
		} catch (StorageNotAvailableException $e) {
			throw new ServiceUnavailable($e->getMessage());
		}
	}
}
