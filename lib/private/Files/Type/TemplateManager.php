<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OC\Files\Type;

/**
 * @deprecated 18.0.0
 */
class TemplateManager {
	protected $templates = [];

	public function registerTemplate($mimetype, $path) {
		$this->templates[$mimetype] = $path;
	}

	/**
	 * get the path of the template for a mimetype
	 *
	 * @deprecated 18.0.0
	 * @param string $mimetype
	 * @return string|null
	 */
	public function getTemplatePath($mimetype) {
		if (isset($this->templates[$mimetype])) {
			return $this->templates[$mimetype];
		} else {
			return null;
		}
	}

	/**
	 * get the template content for a mimetype
	 *
	 * @deprecated 18.0.0
	 * @param string $mimetype
	 * @return string
	 */
	public function getTemplate($mimetype) {
		$path = $this->getTemplatePath($mimetype);
		if ($path) {
			return file_get_contents($path);
		} else {
			return '';
		}
	}
}
