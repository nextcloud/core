<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OC\Files\Node;

use OCP\Files\GenericFileException;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;

class File extends Node implements \OCP\Files\File {
	/**
	 * Creates a Folder that represents a non-existing path
	 *
	 * @param string $path path
	 * @return string non-existing node class
	 */
	protected function createNonExistingNode($path) {
		return new NonExistingFile($this->root, $this->view, $path);
	}

	/**
	 * @return string
	 * @throws NotPermittedException
	 * @throws LockedException
	 */
	public function getContent() {
		if ($this->checkPermissions(\OCP\Constants::PERMISSION_READ)) {
			/**
			 * @var \OC\Files\Storage\Storage $storage;
			 */
			return $this->view->file_get_contents($this->path);
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * @param string|resource $data
	 * @throws NotPermittedException
	 * @throws \OCP\Files\GenericFileException
	 * @throws LockedException
	 */
	public function putContent($data) {
		if ($this->checkPermissions(\OCP\Constants::PERMISSION_UPDATE)) {
			$this->sendHooks(['preWrite']);
			if ($this->view->file_put_contents($this->path, $data) === false) {
				throw new GenericFileException('file_put_contents failed');
			}
			$this->fileInfo = null;
			$this->sendHooks(['postWrite']);
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * @param string $mode
	 * @return resource
	 * @throws NotPermittedException
	 * @throws LockedException
	 */
	public function fopen($mode) {
		$preHooks = [];
		$postHooks = [];
		$requiredPermissions = \OCP\Constants::PERMISSION_READ;
		switch ($mode) {
			case 'r+':
			case 'rb+':
			case 'w+':
			case 'wb+':
			case 'x+':
			case 'xb+':
			case 'a+':
			case 'ab+':
			case 'w':
			case 'wb':
			case 'x':
			case 'xb':
			case 'a':
			case 'ab':
				$preHooks[] = 'preWrite';
				$postHooks[] = 'postWrite';
				$requiredPermissions |= \OCP\Constants::PERMISSION_UPDATE;
				break;
		}

		if ($this->checkPermissions($requiredPermissions)) {
			$this->sendHooks($preHooks);
			$result = $this->view->fopen($this->path, $mode);
			$this->sendHooks($postHooks);
			return $result;
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * @throws NotPermittedException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function delete() {
		if ($this->checkPermissions(\OCP\Constants::PERMISSION_DELETE)) {
			$this->sendHooks(['preDelete']);
			$fileInfo = $this->getFileInfo();
			$this->view->unlink($this->path);
			$nonExisting = new NonExistingFile($this->root, $this->view, $this->path, $fileInfo);
			$this->sendHooks(['postDelete'], [$nonExisting]);
			$this->exists = false;
			$this->fileInfo = null;
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * @param string $type
	 * @param bool $raw
	 * @return string
	 */
	public function hash($type, $raw = false) {
		return $this->view->hash($type, $this->path, $raw);
	}

	/**
	 * @inheritdoc
	 */
	public function getChecksum() {
		return $this->getFileInfo()->getChecksum();
	}

	public function getExtension(): string {
		return $this->getFileInfo()->getExtension();
	}
}
