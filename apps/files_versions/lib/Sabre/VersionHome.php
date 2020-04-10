<?php
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

namespace OCA\Files_Versions\Sabre;

use OC\User\NoUserException;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\ICollection;

class VersionHome implements ICollection {

	/** @var array */
	private $principalInfo;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserManager */
	private $userManager;

	/** @var IVersionManager */
	private $versionManager;

	public function __construct(array $principalInfo, IRootFolder $rootFolder, IUserManager $userManager, IVersionManager $versionManager) {
		$this->principalInfo = $principalInfo;
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
		$this->versionManager = $versionManager;
	}

	private function getUser() {
		list(, $name) = \Sabre\Uri\split($this->principalInfo['uri']);
		$user = $this->userManager->get($name);
		if (!$user) {
			throw new NoUserException();
		}
		return $user;
	}

	public function delete() {
		throw new Forbidden();
	}

	public function getName(): string {
		return $this->getUser()->getUID();
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

	public function getChild($name) {
		$user = $this->getUser();

		if ($name === 'versions') {
			return new VersionRoot($user, $this->rootFolder, $this->versionManager);
		}
		if ($name === 'restore') {
			return new RestoreFolder();
		}
	}

	public function getChildren() {
		$user = $this->getUser();

		return [
			new VersionRoot($user, $this->rootFolder, $this->versionManager),
			new RestoreFolder(),
		];
	}

	public function childExists($name) {
		return $name === 'versions' || $name === 'restore';
	}

	public function getLastModified() {
		return 0;
	}
}
