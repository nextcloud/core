<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCP\Files\Template;

use OCP\Files\File;

/**
 * @since 21.0.0
 */
interface ICustomTemplateProvider {
	/**
	 * Return a list of additional templates that the template provider is offering
	 *
	 * @return File[]
	 * @since 21.0.0
	 */
	public function getCustomTemplates(string $mimetype): array;

	/**
	 * Return the file for a given template id
	 *
	 * @param string $template identifier of the template
	 * @return File
	 * @since 21.0.0
	 */
	public function getCustomTemplate(string $template): File;
}
