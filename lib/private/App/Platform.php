<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\App;

use OCP\IConfig;

/**
 * Class Platform
 *
 * This class basically abstracts any kind of information which can be retrieved from the underlying system.
 *
 * @package OC\App
 */
class Platform {

	/**
	 * @param IConfig $config
	 */
	function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * @return string
	 */
	public function getPhpVersion() {
		return phpversion();
	}

	/**
	 * @return int
	 */
	public function getIntSize() {
		return PHP_INT_SIZE;
	}

	/**
	 * @return string
	 */
	public function getOcVersion() {
		$v = \OCP\Util::getVersion();
		return implode('.', $v);
	}

	/**
	 * @return string
	 */
	public function getDatabase() {
		$dbType = $this->config->getSystemValue('dbtype', 'sqlite');
		if ($dbType === 'sqlite3') {
			$dbType = 'sqlite';
		}

		return $dbType;
	}

	/**
	 * @return string
	 */
	public function getOS() {
		return php_uname('s');
	}

	/**
	 * @param $command
	 * @return bool
	 */
	public function isCommandKnown($command) {
		$path = \OC_Helper::findBinaryPath($command);
		return ($path !== null);
	}

	public function getLibraryVersion($name) {
		$repo = new PlatformRepository();
		return $repo->findLibrary($name);
	}
}
