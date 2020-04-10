<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Template;

class TemplateFileLocator {
	protected $dirs;
	private $path;

	/**
	 * @param string[] $dirs
	 */
	public function __construct($dirs) {
		$this->dirs = $dirs;
	}

	/**
	 * @param string $template
	 * @return string
	 * @throws \Exception
	 */
	public function find($template) {
		if ($template === '') {
			throw new \InvalidArgumentException('Empty template name');
		}

		foreach($this->dirs as $dir) {
			$file = $dir.$template.'.php';
			if (is_file($file)) {
				$this->path = $dir;
				return $file;
			}
		}
		throw new \Exception('template file not found: template:'.$template);
	}

	public function getPath() {
		return $this->path;
	}
}
