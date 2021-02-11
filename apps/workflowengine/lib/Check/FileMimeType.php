<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\WorkflowEngine\Check;

use OCA\WorkflowEngine\Entity\File;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\Storage\IStorage;
use OCP\IL10N;
use OCP\IRequest;
use OCP\WorkflowEngine\IFileCheck;

class FileMimeType extends AbstractStringCheck implements IFileCheck {
	use TFileCheck {
		setFileInfo as _setFileInfo;
	}

	/** @var array */
	protected $mimeType;

	/** @var IRequest */
	protected $request;

	/** @var IMimeTypeDetector */
	protected $mimeTypeDetector;

	/**
	 * @param IL10N $l
	 * @param IRequest $request
	 * @param IMimeTypeDetector $mimeTypeDetector
	 */
	public function __construct(IL10N $l, IRequest $request, IMimeTypeDetector $mimeTypeDetector) {
		parent::__construct($l);
		$this->request = $request;
		$this->mimeTypeDetector = $mimeTypeDetector;
	}

	/**
	 * @param IStorage $storage
	 * @param string $path
	 * @param bool $isDir
	 */
	public function setFileInfo(IStorage $storage, string $path, bool $isDir = false): void {
		$this->_setFileInfo($storage, $path, $isDir);
		if (!isset($this->mimeType[$this->storage->getId()][$this->path])
			|| $this->mimeType[$this->storage->getId()][$this->path] === '') {
			if ($isDir) {
				$this->mimeType[$this->storage->getId()][$this->path] = 'httpd/unix-directory';
			} else {
				$this->mimeType[$this->storage->getId()][$this->path] = null;
			}
		}
	}

	/**
	 * The mimetype is only cached if the file exists. Otherwise files access
	 * control will cache "application/octet-stream" for all the target node on:
	 * rename, move, copy and all other methods which create a new item
	 *
	 * To check this:
	 * 1. Add an automated tagging rule which tags on mimetype NOT "httpd/unix-directory"
	 * 2. Add an access control rule which checks for any mimetype
	 * 3. Create a folder and rename it, the folder should not be tagged, but it is
	 *
	 * @param string $storageId
	 * @param string|null $path
	 * @param string $mimeType
	 * @return string
	 */
	protected function cacheAndReturnMimeType(string $storageId, ?string $path, string $mimeType): string {
		if ($path !== null && $this->storage->file_exists($path)) {
			$this->mimeType[$storageId][$path] = $mimeType;
		}

		return $mimeType;
	}

	/**
	 * Make sure that even though the content based check returns an application/octet-stream can still be checked based on mimetypemappings of their extension
	 *
	 * @param string $operator
	 * @param string $value
	 * @return bool
	 */
	public function executeCheck($operator, $value) {
		$actualValue = $this->getActualValue();
		$plainMimetypeResult = $this->executeStringCheck($operator, $value, $actualValue);
		if ($actualValue === 'httpd/unix-directory') {
			return $plainMimetypeResult;
		}
		$detectMimetypeBasedOnFilenameResult = $this->executeStringCheck($operator, $value, $this->mimeTypeDetector->detectPath($this->path));
		return $plainMimetypeResult || $detectMimetypeBasedOnFilenameResult;
	}

	/**
	 * @return string
	 */
	protected function getActualValue() {
		if ($this->mimeType[$this->storage->getId()][$this->path] !== null) {
			return $this->mimeType[$this->storage->getId()][$this->path];
		}

		if ($this->storage->is_dir($this->path)) {
			return $this->cacheAndReturnMimeType($this->storage->getId(), $this->path, 'httpd/unix-directory');
		}

		if ($this->storage->file_exists($this->path)) {
			$path = $this->storage->getLocalFile($this->path);
			$mimeType = $this->mimeTypeDetector->detectContent($path);
			return $this->cacheAndReturnMimeType($this->storage->getId(), $this->path, $mimeType);
		}

		if ($this->isWebDAVRequest() || $this->isPublicWebDAVRequest()) {
			// Creating a folder
			if ($this->request->getMethod() === 'MKCOL') {
				return 'httpd/unix-directory';
			}
		}

		// We do not cache this, as the file did not exist yet.
		// In case it does in the future, we will check with detectContent()
		// again to get the real mimetype of the content, rather than
		// guessing it from the path.
		return $this->mimeTypeDetector->detectPath($this->path);
	}

	/**
	 * @return bool
	 */
	protected function isWebDAVRequest() {
		return substr($this->request->getScriptName(), 0 - strlen('/remote.php')) === '/remote.php' && (
			$this->request->getPathInfo() === '/webdav' ||
			strpos($this->request->getPathInfo(), '/webdav/') === 0 ||
			$this->request->getPathInfo() === '/dav/files' ||
			strpos($this->request->getPathInfo(), '/dav/files/') === 0 ||
			$this->request->getPathInfo() === '/dav/uploads' ||
			strpos($this->request->getPathInfo(), '/dav/uploads/') === 0
		);
	}

	/**
	 * @return bool
	 */
	protected function isPublicWebDAVRequest() {
		return substr($this->request->getScriptName(), 0 - strlen('/public.php')) === '/public.php' && (
			$this->request->getPathInfo() === '/webdav' ||
			strpos($this->request->getPathInfo(), '/webdav/') === 0
		);
	}

	public function supportedEntities(): array {
		return [ File::class ];
	}

	public function isAvailableForScope(int $scope): bool {
		return true;
	}
}
