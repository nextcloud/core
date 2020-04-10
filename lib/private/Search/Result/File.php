<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andrew Brown <andrew@casabrown.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Search\Result;

use OCP\Files\FileInfo;
use OCP\Files\Folder;

/**
 * A found file
 */
class File extends \OCP\Search\Result {

	/**
	 * Type name; translated in templates
	 * @var string
	 */
	public $type = 'file';

	/**
	 * Path to file
	 * @var string
	 */
	public $path;

	/**
	 * Size, in bytes
	 * @var int
	 */
	public $size;

	/**
	 * Date modified, in human readable form
	 * @var string
	 */
	public $modified;

	/**
	 * File mime type
	 * @var string
	 */
	public $mime_type;

	/**
	 * File permissions:
	 *
	 * @var string
	 */
	public $permissions;

	/**
	 * Create a new file search result
	 * @param FileInfo $data file data given by provider
	 */
	public function __construct(FileInfo $data) {

		$path = $this->getRelativePath($data->getPath());

		$info = pathinfo($path);
		$this->id = $data->getId();
		$this->name = $info['basename'];
		$this->link = \OC::$server->getURLGenerator()->linkToRoute(
			'files.view.index',
			[
				'dir' => $info['dirname'],
				'scrollto' => $info['basename'],
			]
		);
		$this->permissions = $data->getPermissions();
		$this->path = $path;
		$this->size = $data->getSize();
		$this->modified = $data->getMtime();
		$this->mime_type = $data->getMimetype();
	}

	/**
	 * @var Folder $userFolderCache
	 */
	static protected $userFolderCache = null;

	/**
	 * converts a path relative to the users files folder
	 * eg /user/files/foo.txt -> /foo.txt
	 * @param string $path
	 * @return string relative path
	 */
	protected function getRelativePath($path) {
		if (!isset(self::$userFolderCache)) {
			$user = \OC::$server->getUserSession()->getUser()->getUID();
			self::$userFolderCache = \OC::$server->getUserFolder($user);
		}
		return self::$userFolderCache->getRelativePath($path);
	}

}
