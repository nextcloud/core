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

use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Files\Node;
use OCP\Files\NotFoundException;

/**
 * Class Watcher
 *
 * @package OC\Preview
 *
 * Class that will watch filesystem activity and remove previews as needed.
 */
class Watcher {
	/** @var IAppData */
	private $appData;

	/**
	 * Watcher constructor.
	 *
	 * @param IAppData $appData
	 */
	public function __construct(IAppData $appData) {
		$this->appData = $appData;
	}

	public function postWrite(Node $node) {
		$this->deleteNode($node);
	}

	protected function deleteNode(Node $node) {
		// We only handle files
		if ($node instanceof Folder) {
			return;
		}

		try {
			$folder = $this->appData->getFolder((string)$node->getId());
			$folder->delete();
		} catch (NotFoundException $e) {
			//Nothing to do
		}
	}

	public function versionRollback(array $data) {
		if (isset($data['node'])) {
			$this->deleteNode($data['node']);
		}
	}
}
