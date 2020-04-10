<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_Trashbin\Sabre;

use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\ICollection;
use Sabre\DAV\IMoveTarget;
use Sabre\DAV\INode;

class RestoreFolder implements ICollection, IMoveTarget {
	public function createFile($name, $data = null) {
		throw new Forbidden();
	}

	public function createDirectory($name) {
		throw new Forbidden();
	}

	public function getChild($name) {
		return null;
	}

	public function delete() {
		throw new Forbidden();
	}

	public function getName() {
		return 'restore';
	}

	public function setName($name) {
		throw new Forbidden();
	}

	public function getLastModified(): int {
		return 0;
	}

	public function getChildren(): array {
		return [];
	}

	public function childExists($name): bool {
		return false;
	}

	public function moveInto($targetName, $sourcePath, INode $sourceNode): bool {
		if (!($sourceNode instanceof ITrash)) {
			return false;
		}

		return $sourceNode->restore();
	}

}
