<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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

namespace OCA\WorkflowEngine\Check;

use OCP\IL10N;
use OCP\IRequest;

class RequestURL extends AbstractStringCheck {

	/** @var string */
	protected $url;

	/** @var IRequest */
	protected $request;

	/**
	 * @param IL10N $l
	 * @param IRequest $request
	 */
	public function __construct(IL10N $l, IRequest $request) {
		parent::__construct($l);
		$this->request = $request;
	}

	/**
	 * @param string $operator
	 * @param string $value
	 * @return bool
	 */
	public function executeCheck($operator, $value) {
		$actualValue = $this->getActualValue();
		if (in_array($operator, ['is', '!is'])) {
			switch ($value) {
				case 'webdav':
					if ($operator === 'is') {
						return $this->isWebDAVRequest();
					} else {
						return !$this->isWebDAVRequest();
					}
			}
		}
		return $this->executeStringCheck($operator, $value, $actualValue);
	}

	/**
	 * @return string
	 */
	protected function getActualValue() {
		if ($this->url !== null) {
			return $this->url;
		}

		$this->url = $this->request->getServerProtocol() . '://';// E.g. http(s) + ://
		$this->url .= $this->request->getServerHost();// E.g. localhost
		$this->url .= $this->request->getScriptName();// E.g. /nextcloud/index.php
		$this->url .= $this->request->getPathInfo();// E.g. /apps/files_texteditor/ajax/loadfile

		return $this->url; // E.g. https://localhost/nextcloud/index.php/apps/files_texteditor/ajax/loadfile
	}

	/**
	 * @return bool
	 */
	protected function isWebDAVRequest() {
		return substr($this->request->getScriptName(), 0 - strlen('/remote.php')) === '/remote.php' && (
			$this->request->getPathInfo() === '/webdav' ||
			strpos($this->request->getPathInfo(), '/webdav/') === 0 ||
			$this->request->getPathInfo() === '/dav/files' ||
			strpos($this->request->getPathInfo(), '/dav/files/') === 0
		);
	}
}
