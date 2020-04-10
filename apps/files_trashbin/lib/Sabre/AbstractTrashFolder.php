<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

use OCA\Files_Trashbin\Trash\ITrashItem;
use OCP\Files\FileInfo;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

abstract class AbstractTrashFolder extends AbstractTrash implements ICollection, ITrash {
	public function getChildren(): array {
		$entries = $this->trashManager->listTrashFolder($this->data);

		$children = array_map(function (ITrashItem $entry) {
			if ($entry->getType() === FileInfo::TYPE_FOLDER) {
				return new TrashFolderFolder($this->trashManager, $entry);
			}
			return new TrashFolderFile($this->trashManager, $entry);
		}, $entries);

		return $children;
	}

	public function getChild($name): ITrash {
		$entries = $this->getChildren();

		foreach ($entries as $entry) {
			if ($entry->getName() === $name) {
				return $entry;
			}
		}

		throw new NotFound();
	}

	public function childExists($name): bool {
		try {
			$this->getChild($name);
			return true;
		} catch (NotFound $e) {
			return false;
		}
	}

	public function setName($name) {
		throw new Forbidden();
	}

	public function createFile($name, $data = null) {
		throw new Forbidden();
	}

	public function createDirectory($name) {
		throw new Forbidden();
	}
}
