<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
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
use Sabre\DAV\IFile;

/**
 * Class FutureFile
 *
 * The FutureFile is a SabreDav IFile which connects the chunked upload directory
 * with the AssemblyStream, who does the final assembly job
 *
 * @package OCA\DAV\Upload
 */
class FutureFile implements \Sabre\DAV\IFile {

	/** @var Directory */
	private $root;
	/** @var string */
	private $name;

	/**
	 * @param Directory $root
	 * @param string $name
	 */
	public function __construct(Directory $root, $name) {
		$this->root = $root;
		$this->name = $name;
	}

	/**
	 * @inheritdoc
	 */
	public function put($data) {
		throw new Forbidden('Permission denied to put into this file');
	}

	/**
	 * @inheritdoc
	 */
	public function get() {
		$nodes = $this->root->getChildren();
		return AssemblyStream::wrap($nodes);
	}

	public function getPath() {
		return $this->root->getFileInfo()->getInternalPath() . '/.file';
	}

	/**
	 * @inheritdoc
	 */
	public function getContentType() {
		return 'application/octet-stream';
	}

	/**
	 * @inheritdoc
	 */
	public function getETag() {
		return $this->root->getETag();
	}

	/**
	 * @inheritdoc
	 */
	public function getSize() {
		$children = $this->root->getChildren();
		$sizes = array_map(function ($node) {
			/** @var IFile $node */
			return $node->getSize();
		}, $children);

		return array_sum($sizes);
	}

	/**
	 * @inheritdoc
	 */
	public function delete() {
		$this->root->delete();
	}

	/**
	 * @inheritdoc
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @inheritdoc
	 */
	public function setName($name) {
		throw new Forbidden('Permission denied to rename this file');
	}

	/**
	 * @inheritdoc
	 */
	public function getLastModified() {
		return $this->root->getLastModified();
	}
}
