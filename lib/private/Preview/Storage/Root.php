<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Preview\Storage;

use OC\Files\AppData\AppData;
use OC\SystemConfig;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

class Root extends AppData {
	private $isMultibucketPreviewDistributionEnabled = false;
	public function __construct(IRootFolder $rootFolder, SystemConfig $systemConfig) {
		parent::__construct($rootFolder, $systemConfig, 'preview');

		$this->isMultibucketPreviewDistributionEnabled = $systemConfig->getValue('objectstore.multibucket.preview-distribution', false) === true;
	}


	public function getFolder(string $name): ISimpleFolder {
		$internalFolder = self::getInternalFolder($name);

		try {
			return parent::getFolder($internalFolder);
		} catch (NotFoundException $e) {
			/*
			 * The new folder structure is not found.
			 * Lets try the old one
			 */
		}

		try {
			return parent::getFolder($name);
		} catch (NotFoundException $e) {
			/*
			 * The old folder structure is not found.
			 * Lets try the multibucket fallback if available
			 */
			if ($this->isMultibucketPreviewDistributionEnabled) {
				return parent::getFolder('old-multibucket/' . $internalFolder);
			}

			// when there is no further fallback just throw the exception
			throw $e;
		}
	}

	public function newFolder(string $name): ISimpleFolder {
		$internalFolder = self::getInternalFolder($name);
		return parent::newFolder($internalFolder);
	}

	/*
	 * Do not allow directory listing on this special root
	 * since it gets to big and time consuming
	 */
	public function getDirectoryListing(): array {
		return [];
	}

	public static function getInternalFolder(string $name): string {
		return implode('/', str_split(substr(md5($name), 0, 7))) . '/' . $name;
	}
}
