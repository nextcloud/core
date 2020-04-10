<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OC\Preview;

use OC\SystemConfig;
use OCP\Files\IRootFolder;
use OCP\Files\Node;

class WatcherConnector {

	/** @var IRootFolder */
	private $root;

	/** @var SystemConfig */
	private $config;

	/**
	 * WatcherConnector constructor.
	 *
	 * @param IRootFolder $root
	 * @param SystemConfig $config
	 */
	public function __construct(IRootFolder $root,
								SystemConfig $config) {
		$this->root = $root;
		$this->config = $config;
	}

	/**
	 * @return Watcher
	 */
	private function getWatcher(): Watcher {
		return \OC::$server->query(Watcher::class);
	}

	public function connectWatcher() {
		// Do not connect if we are not setup yet!
		if ($this->config->getValue('instanceid', null) !== null) {
			$this->root->listen('\OC\Files', 'postWrite', function (Node $node) {
				$this->getWatcher()->postWrite($node);
			});

			\OC_Hook::connect('\OCP\Versions', 'rollback', $this->getWatcher(), 'versionRollback');
		}
	}
}
