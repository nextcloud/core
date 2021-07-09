<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
namespace OCA\DAV\Upload;

use OC\Files\Filesystem;
use OC\Files\View;
use OCA\DAV\Connector\Sabre\Directory;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\ICollection;

class UploadHome implements ICollection {

	/** @var array */
	private $principalInfo;
	/** @var CleanupService */
	private $cleanupService;

	public function __construct(array $principalInfo, CleanupService $cleanupService) {
		$this->principalInfo = $principalInfo;
		$this->cleanupService = $cleanupService;
	}

	public function createFile($name, $data = null) {
		throw new Forbidden('Permission denied to create file (filename ' . $name . ')');
	}

	public function createDirectory($name) {
		$this->impl()->createDirectory($name);

		// Add a cleanup job
		$this->cleanupService->addJob($name);
	}

	public function getChild($name): UploadFolder {
		return new UploadFolder($this->impl()->getChild($name), $this->cleanupService, $this->getStorage());
	}

	public function getChildren(): array {
		return array_map(function ($node) {
			return new UploadFolder($node, $this->cleanupService, $this->getStorage());
		}, $this->impl()->getChildren());
	}

	public function childExists($name): bool {
		return !is_null($this->getChild($name));
	}

	public function delete() {
		$this->impl()->delete();
	}

	public function getName() {
		[,$name] = \Sabre\Uri\split($this->principalInfo['uri']);
		return $name;
	}

	public function setName($name) {
		throw new Forbidden('Permission denied to rename this folder');
	}

	public function getLastModified() {
		return $this->impl()->getLastModified();
	}

	/**
	 * @return Directory
	 */
	private function impl() {
		$view = $this->getView();
		$rootInfo = $view->getFileInfo('');
		return new Directory($view, $rootInfo);
	}

	private function getView() {
		$rootView = new View();
		$user = \OC::$server->getUserSession()->getUser();
		Filesystem::initMountPoints($user->getUID());
		if (!$rootView->file_exists('/' . $user->getUID() . '/uploads')) {
			$rootView->mkdir('/' . $user->getUID() . '/uploads');
		}
		return new View('/' . $user->getUID() . '/uploads');
	}

	private function getStorage() {
		$view = $this->getView();
		$storage = $view->getFileInfo('')->getStorage();
		return $storage;
	}
}
