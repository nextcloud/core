<?php
/**
 * @copyright Copyright (c) 2016 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\Files\SimpleFS;

use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

/**
 * Interface ISimpleRoot
 *
 * @package OCP\Files\SimpleFS
 * @since 11.0.0
 */
interface ISimpleRoot {
	/**
	 * Get the folder with name $name
	 *
	 * @param string $name
	 * @return ISimpleFolder
	 * @throws NotFoundException
	 * @throws \RuntimeException
	 * @since 11.0.0
	 */
	public function getFolder(string $name): ISimpleFolder;

	/**
	 * Get all the Folders
	 *
	 * @return ISimpleFolder[]
	 * @throws NotFoundException
	 * @throws \RuntimeException
	 * @since 11.0.0
	 */
	public function getDirectoryListing(): array;

	/**
	 * Create a new folder named $name
	 *
	 * @param string $name
	 * @return ISimpleFolder
	 * @throws NotPermittedException
	 * @throws \RuntimeException
	 * @since 11.0.0
	 */
	public function newFolder(string $name): ISimpleFolder;
}
