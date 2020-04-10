<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCA\Files_External\Lib;

use OC\Files\Storage\Wrapper\PermissionsMask;
use OCP\Constants;

/**
 * Wrap Storage in PermissionsMask for session ephemeral use
 */
class SessionStorageWrapper extends PermissionsMask {

	/**
	 * @param array $arguments ['storage' => $storage]
	 */
	public function __construct($arguments) {
		// disable sharing permission
		$arguments['mask'] = Constants::PERMISSION_ALL & ~Constants::PERMISSION_SHARE;
		parent::__construct($arguments);
	}

}
