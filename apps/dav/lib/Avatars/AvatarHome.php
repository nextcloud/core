<?php
/**
 * @copyright Copyright (c) 2016, ownCloud GmbH
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\DAV\Avatars;

use OCP\IAvatarManager;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;
use Sabre\Uri;

class AvatarHome implements ICollection {

	/** @var array */
	private $principalInfo;
	/** @var IAvatarManager */
	private $avatarManager;

	/**
	 * AvatarHome constructor.
	 *
	 * @param array $principalInfo
	 * @param IAvatarManager $avatarManager
	 */
	public function __construct($principalInfo, IAvatarManager $avatarManager) {
		$this->principalInfo = $principalInfo;
		$this->avatarManager = $avatarManager;
	}

	public function createFile($name, $data = null) {
		throw new Forbidden('Permission denied to create a file');
	}

	public function createDirectory($name) {
		throw new Forbidden('Permission denied to create a folder');
	}

	public function getChild($name) {
		$elements = pathinfo($name);
		$ext = isset($elements['extension']) ? $elements['extension'] : '';
		$size = (int)(isset($elements['filename']) ? $elements['filename'] : '64');
		if (!in_array($ext, ['jpeg', 'png'], true)) {
			throw new MethodNotAllowed('File format not allowed');
		}
		if ($size <= 0 || $size > 1024) {
			throw new MethodNotAllowed('Invalid image size');
		}
		$avatar = $this->avatarManager->getAvatar($this->getName());
		if (!$avatar->exists()) {
			throw new NotFound();
		}
		return new AvatarNode($size, $ext, $avatar);
	}

	public function getChildren() {
		try {
			return [
				$this->getChild('96.jpeg')
			];
		} catch(NotFound $exception) {
			return [];
		}
	}

	public function childExists($name) {
		try {
			$ret = $this->getChild($name);
			return $ret !== null;
		} catch (NotFound $ex) {
			return false;
		} catch (MethodNotAllowed $ex) {
			return false;
		}
	}

	public function delete() {
		throw new Forbidden('Permission denied to delete this folder');
	}

	public function getName() {
		list(,$name) = Uri\split($this->principalInfo['uri']);
		return $name;
	}

	public function setName($name) {
		throw new Forbidden('Permission denied to rename this folder');
	}

	/**
	 * Returns the last modification time, as a unix timestamp
	 *
	 * @return int|null
	 */
	public function getLastModified() {
		return null;
	}


}
