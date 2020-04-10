<?php
/**
 * @copyright Copyright (c) 2017, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\App\CodeChecker;

class LanguageParseChecker {

	/**
	 * @param string $appId
	 * @return array
	 */
	public function analyse($appId) {
		$appPath = \OC_App::getAppPath($appId);
		if ($appPath === false) {
			throw new \RuntimeException("No app with given id <$appId> known.");
		}

		if (!is_dir($appPath . '/l10n/')) {
			return [];
		}

		$errors = [];
		$directory = new \DirectoryIterator($appPath . '/l10n/');

		foreach ($directory as $file) {
			if ($file->getExtension() !== 'json') {
				continue;
			}

			$content = file_get_contents($file->getPathname());
			json_decode($content, true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				$errors[] = 'Invalid language file found: l10n/' . $file->getFilename() . ': ' . json_last_error_msg();
			}
		}

		return $errors;
	}
}
