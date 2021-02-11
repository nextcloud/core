<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Jacob Neplokh <me@jacobneplokh.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\CardDAV;

use OCP\Files\NotFoundException;
use Sabre\CardDAV\Card;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class ImageExportPlugin extends ServerPlugin {

	/** @var Server */
	protected $server;
	/** @var PhotoCache */
	private $cache;

	/**
	 * ImageExportPlugin constructor.
	 *
	 * @param PhotoCache $cache
	 */
	public function __construct(PhotoCache $cache) {
		$this->cache = $cache;
	}

	/**
	 * Initializes the plugin and registers event handlers
	 *
	 * @param Server $server
	 * @return void
	 */
	public function initialize(Server $server) {
		$this->server = $server;
		$this->server->on('method:GET', [$this, 'httpGet'], 90);
	}

	/**
	 * Intercepts GET requests on addressbook urls ending with ?photo.
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return bool
	 */
	public function httpGet(RequestInterface $request, ResponseInterface $response) {
		$queryParams = $request->getQueryParameters();
		// TODO: in addition to photo we should also add logo some point in time
		if (!array_key_exists('photo', $queryParams)) {
			return true;
		}

		$size = isset($queryParams['size']) ? (int)$queryParams['size'] : -1;

		$path = $request->getPath();
		$node = $this->server->tree->getNodeForPath($path);

		if (!($node instanceof Card)) {
			return true;
		}

		$this->server->transactionType = 'carddav-image-export';

		// Checking ACL, if available.
		if ($aclPlugin = $this->server->getPlugin('acl')) {
			/** @var \Sabre\DAVACL\Plugin $aclPlugin */
			$aclPlugin->checkPrivileges($path, '{DAV:}read');
		}

		// Fetch addressbook
		$addressbookpath = explode('/', $path);
		array_pop($addressbookpath);
		$addressbookpath = implode('/', $addressbookpath);
		/** @var AddressBook $addressbook */
		$addressbook = $this->server->tree->getNodeForPath($addressbookpath);

		$response->setHeader('Cache-Control', 'private, max-age=3600, must-revalidate');
		$response->setHeader('Etag', $node->getETag());
		$response->setHeader('Pragma', 'public');

		try {
			$file = $this->cache->get($addressbook->getResourceId(), $node->getName(), $size, $node);
			$response->setHeader('Content-Type', $file->getMimeType());
			$fileName = $node->getName() . '.' . PhotoCache::ALLOWED_CONTENT_TYPES[$file->getMimeType()];
			$response->setHeader('Content-Disposition', "attachment; filename=$fileName");
			$response->setStatus(200);

			$response->setBody($file->getContent());
		} catch (NotFoundException $e) {
			$response->setStatus(404);
		}

		return false;
	}
}
