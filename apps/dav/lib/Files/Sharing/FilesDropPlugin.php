<?php
/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\Files\Sharing;

use OC\Files\View;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * Make sure that the destination is writable
 */
class FilesDropPlugin extends ServerPlugin {

	/** @var View */
	private $view;

	/** @var bool */
	private $enabled = false;

	/**
	 * @param View $view
	 */
	public function setView($view) {
		$this->view = $view;
	}

	public function enable() {
		$this->enabled = true;
	}


	/**
	 * This initializes the plugin.
	 *
	 * @param \Sabre\DAV\Server $server Sabre server
	 *
	 * @return void
	 * @throws MethodNotAllowed
	 */
	public function initialize(\Sabre\DAV\Server $server) {
		$server->on('beforeMethod:*', [$this, 'beforeMethod'], 999);
		$this->enabled = false;
	}

	public function beforeMethod(RequestInterface $request, ResponseInterface $response) {

		if (!$this->enabled) {
			return;
		}

		if ($request->getMethod() !== 'PUT') {
			throw new MethodNotAllowed('Only PUT is allowed on files drop');
		}

		$path = explode('/', $request->getPath());
		$path = array_pop($path);

		$newName = \OC_Helper::buildNotExistingFileNameForView('/', $path, $this->view);
		$url = $request->getBaseUrl() . $newName;
		$request->setUrl($url);
	}
}
