<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP;

use OCP\Files\File;
use OCP\Files\NotFoundException;

/**
 * This class provides avatar functionality
 * @since 6.0.0
 */
interface IAvatar {

	/**
	 * get the users avatar
	 * @param int $size size in px of the avatar, avatars are square, defaults to 64, -1 can be used to not scale the image
	 * @return boolean|\OCP\IImage containing the avatar or false if there's no image
	 * @since 6.0.0 - size of -1 was added in 9.0.0
	 */
	public function get($size = 64);

	/**
	 * Check if an avatar exists for the user
	 *
	 * @return bool
	 * @since 8.1.0
	 */
	public function exists();

	/**
	 * Check if the avatar of a user is a custom uploaded one
	 *
	 * @return bool
	 * @since 14.0.0
	 */
	public function isCustomAvatar(): bool;

	/**
	 * sets the users avatar
	 * @param \OCP\IImage|resource|string $data An image object, imagedata or path to set a new avatar
	 * @throws \Exception if the provided file is not a jpg or png image
	 * @throws \Exception if the provided image is not valid
	 * @throws \OC\NotSquareException if the image is not square
	 * @return void
	 * @since 6.0.0
	 */
	public function set($data);

	/**
	 * remove the users avatar
	 * @return void
	 * @since 6.0.0
	 */
	public function remove();

	/**
	 * Get the file of the avatar
	 * @param int $size -1 can be used to not scale the image
	 * @return File
	 * @throws NotFoundException
	 * @since 9.0.0
	 */
	public function getFile($size);

	/**
	 * @param string $text
	 * @return Color Object containting r g b int in the range [0, 255]
	 * @since 14.0.0
	 */
	public function avatarBackgroundColor(string $text);

	/**
	 * Handle a changed user
	 * @since 13.0.0
	 */
	public function userChanged($feature, $oldValue, $newValue);
}
