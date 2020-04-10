<?php
/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCP\DirectEditing;

use OCP\Files\File;

/**
 * @since 18.0.0
 */
abstract class ACreateEmpty {

	/**
	 * Unique id for the creator to filter templates
	 *
	 * e.g. document/spreadsheet/presentation
	 *
	 * @since 18.0.0
	 * @return string
	 */
	abstract public function getId(): string;

	/**
	 * Descriptive name for the create action
	 *
	 * e.g Create a new document
	 *
	 * @since 18.0.0
	 * @return string
	 */
	abstract public function getName(): string;

	/**
	 * Default file extension for the new file
	 *
	 * @since 18.0.0
	 * @return string
	 */
	abstract public function getExtension(): string;

	/**
	 * Mimetype of the resulting created file
	 *
	 * @since 18.0.0
	 * @return string
	 */
	abstract public function getMimetype(): string;

	/**
	 * Add content when creating empty files
	 *
	 * @since 18.0.0
	 * @param File $file
	 */
	public function create(File $file, string $creatorId = null, string $templateId = null): void {

	}
}
