<?php

declare(strict_types=1);

/**
 * @copyright 2016 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Files\AppData;

use OC\SystemConfig;
use OCP\Files\IRootFolder;

class Factory {

	/** @var IRootFolder */
	private $rootFolder;

	/** @var SystemConfig */
	private $config;

	private $folders = [];

	public function __construct(IRootFolder $rootFolder,
								SystemConfig $systemConfig) {

		$this->rootFolder = $rootFolder;
		$this->config = $systemConfig;
	}

	/**
	 * @param string $appId
	 * @return AppData
	 */
	public function get(string $appId): AppData {
		if (!isset($this->folders[$appId])) {
			$this->folders[$appId] = new AppData($this->rootFolder, $this->config, $appId);
		}
		return $this->folders[$appId];
	}
}
