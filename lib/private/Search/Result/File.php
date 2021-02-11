<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andrew Brown <andrew@casabrown.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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
use OCP\IPreview;
use OCP\IUserSession;

/**
 * A found file
 * @deprecated 20.0.0
 */
class File extends \OCP\Search\Result {

	/**
	 * Type name; translated in templates
	 * @var string
	 * @deprecated 20.0.0
	 */
	public $type = 'file';

	/**
	 * Path to file
	 * @var string
	 * @deprecated 20.0.0
	 */
	public $path;

	/**
	 * Size, in bytes
	 * @var int
	 * @deprecated 20.0.0
	 */
	public $size;

	/**
	 * Date modified, in human readable form
	 * @var string
	 * @deprecated 20.0.0
	 */
	public $modified;

	/**
	 * File mime type
	 * @var string
	 * @deprecated 20.0.0
	 */
	public $mime_type;

	/**
	 * File permissions:
	 *
	 * @var string
	 * @deprecated 20.0.0
	 */
	public $permissions;

	/**
	 * Has a preview
	 *
	 * @var string
	 * @deprecated 20.0.0
	 */
	public $has_preview;

	/**
	 * Create a new file search result
	 * @param FileInfo $data file data given by provider
	 * @deprecated 20.0.0
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
		$this->has_preview = $this->hasPreview($data);
	}

	/**
	 * @var Folder $userFolderCache
	 * @deprecated 20.0.0
	 */
	protected static $userFolderCache = null;

	/**
	 * converts a path relative to the users files folder
	 * eg /user/files/foo.txt -> /foo.txt
	 * @param string $path
	 * @return string relative path
	 * @deprecated 20.0.0
	 */
	protected function getRelativePath($path) {
		if (!isset(self::$userFolderCache)) {
			$userSession = \OC::$server->get(IUserSession::class);
			$userID = $userSession->getUser()->getUID();
			self::$userFolderCache = \OC::$server->getUserFolder($userID);
		}
		return self::$userFolderCache->getRelativePath($path);
	}

	/**
	 * Is the preview available
	 * @param FileInfo $data
	 * @return bool
	 * @deprecated 20.0.0
	 */
	protected function hasPreview($data) {
		$previewManager = \OC::$server->get(IPreview::class);
		return $previewManager->isAvailable($data);
	}
}
