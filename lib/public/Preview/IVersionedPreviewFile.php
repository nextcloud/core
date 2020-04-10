<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCP\Preview;

/**
 * Marks files that should keep multiple preview "versions" for the same file id
 *
 * Examples of this are files where the storage backend provides versioning, for those
 * files, we dont have fileids for the different versions but still need to be able to generate
 * previews for all versions
 *
 * @since 17.0.0
 */
interface IVersionedPreviewFile {
	/**
	 * @return string
	 * @since 17.0.0
	 */
	public function getPreviewVersion(): string;
}
