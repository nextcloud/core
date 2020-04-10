<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

namespace OCA\DAV\Upload;

use OCA\DAV\Connector\Sabre\Directory;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\ICollection;

class UploadFolder implements ICollection {

	/** @var Directory */
	private $node;
	/** @var CleanupService */
	private $cleanupService;

	function __construct(Directory $node, CleanupService $cleanupService) {
		$this->node = $node;
		$this->cleanupService = $cleanupService;
	}

	function createFile($name, $data = null) {
		// TODO: verify name - should be a simple number
		$this->node->createFile($name, $data);
	}

	function createDirectory($name) {
		throw new Forbidden('Permission denied to create file (filename ' . $name . ')');
	}

	function getChild($name) {
		if ($name === '.file') {
			return new FutureFile($this->node, '.file');
		}
		return $this->node->getChild($name);
	}

	function getChildren() {
		$children = $this->node->getChildren();
		$children[] = new FutureFile($this->node, '.file');
		return $children;
	}

	function childExists($name) {
		if ($name === '.file') {
			return true;
		}
		return $this->node->childExists($name);
	}

	function delete() {
		$this->node->delete();

		// Background cleanup job is not needed anymore
		$this->cleanupService->removeJob($this->getName());
	}

	function getName() {
		return $this->node->getName();
	}

	function setName($name) {
		throw new Forbidden('Permission denied to rename this folder');
	}

	function getLastModified() {
		return $this->node->getLastModified();
	}
}
