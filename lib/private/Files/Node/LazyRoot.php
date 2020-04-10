<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

use OCP\Files\IRootFolder;

/**
 * Class LazyRoot
 *
 * This is a lazy wrapper around the root. So only
 * once it is needed this will get initialized.
 *
 * @package OC\Files\Node
 */
class LazyRoot implements IRootFolder {
	/** @var \Closure */
	private $rootFolderClosure;

	/** @var IRootFolder */
	private $rootFolder;

	/**
	 * LazyRoot constructor.
	 *
	 * @param \Closure $rootFolderClosure
	 */
	public function __construct(\Closure $rootFolderClosure) {
		$this->rootFolderClosure = $rootFolderClosure;
	}

	/**
	 * Magic method to first get the real rootFolder and then
	 * call $method with $args on it
	 *
	 * @param $method
	 * @param $args
	 * @return mixed
	 */
	public function __call($method, $args) {
		if ($this->rootFolder === null) {
			$this->rootFolder = call_user_func($this->rootFolderClosure);
		}

		return call_user_func_array([$this->rootFolder, $method], $args);
	}

	/**
	 * @inheritDoc
	 */
	public function getUser() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function listen($scope, $method, callable $callback) {
		$this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function removeListener($scope = null, $method = null, callable $callback = null) {
		$this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function emit($scope, $method, $arguments = []) {
		$this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function mount($storage, $mountPoint, $arguments = []) {
		$this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getMount($mountPoint) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getMountsIn($mountPoint) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getMountByStorageId($storageId) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getMountByNumericStorageId($numericId) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function unMount($mount) {
		$this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function get($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function rename($targetPath) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function delete() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function copy($targetPath) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function touch($mtime = null) {
		$this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getStorage() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getPath() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getInternalPath() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getId() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function stat() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getMTime() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getSize($includeMounts = true) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getEtag() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getPermissions() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function isReadable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function isUpdateable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function isDeletable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function isShareable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getParent() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getUserFolder($userId) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getMimetype() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getMimePart() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function isEncrypted() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getType() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function isShared() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function isMounted() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getMountPoint() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getOwner() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getChecksum() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getExtension(): string {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getFullPath($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getRelativePath($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function isSubNode($node) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getDirectoryListing() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function nodeExists($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function newFolder($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function newFile($path, $content = null) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function search($query) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function searchByMime($mimetype) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function searchByTag($tag, $userId) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getById($id) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getFreeSpace() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function isCreatable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getNonExistingName($name) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function move($targetPath) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function lock($type) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function changeLock($targetType) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function unlock($type) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getRecent($limit, $offset = 0) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getCreationTime(): int {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	/**
	 * @inheritDoc
	 */
	public function getUploadTime(): int {
		return $this->__call(__FUNCTION__, func_get_args());
	}
}
