<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_Trashbin\Sabre;

use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\IUser;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

class TrashHome implements ICollection {
	/** @var ITrashManager */
	private $trashManager;

	/** @var array */
	private $principalInfo;

	/** @var IUser */
	private $user;

	public function __construct(
		array $principalInfo,
		ITrashManager $trashManager,
		IUser $user
	) {
		$this->principalInfo = $principalInfo;
		$this->trashManager = $trashManager;
		$this->user = $user;
	}

	public function delete() {
		throw new Forbidden();
	}

	public function getName(): string {
		list(, $name) = \Sabre\Uri\split($this->principalInfo['uri']);
		return $name;
	}

	public function setName($name) {
		throw new Forbidden('Permission denied to rename this trashbin');
	}

	public function createFile($name, $data = null) {
		throw new Forbidden('Not allowed to create files in the trashbin');
	}

	public function createDirectory($name) {
		throw new Forbidden('Not allowed to create folders in the trashbin');
	}

	public function getChild($name) {
		if ($name === 'restore') {
			return new RestoreFolder();
		}
		if ($name === 'trash') {
			return new TrashRoot($this->user, $this->trashManager);
		}

		throw new NotFound();
	}

	public function getChildren(): array {
		return [
			new RestoreFolder(),
			new TrashRoot($this->user, $this->trashManager)
		];
	}

	public function childExists($name): bool {
		return $name === 'restore' || $name === 'trash';
	}

	public function getLastModified(): int {
		return 0;
	}
}
