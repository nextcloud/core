<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\AdminAudit\Actions;

/**
 * Class Files logs the actions to files
 *
 * @package OCA\AdminAudit\Actions
 */
class Files extends Action {
	/**
	 * Logs file read actions
	 *
	 * @param array $params
	 */
	public function read(array $params) {
		$this->log(
			'File accessed: "%s"',
			$params,
			[
				'path',
			]
		);
	}

	/**
	 * Logs rename actions of files
	 *
	 * @param array $params
	 */
	public function rename(array $params) {
		$this->log(
			'File renamed: "%s" to "%s"',
			$params,
			[
				'oldpath',
				'newpath',
			]
		);
	}

	/**
	 * Logs creation of files
	 *
	 * @param array $params
	 */
	public function create(array $params) {
		if ($params['path'] === '/' || $params['path'] === '' || $params['path'] === null) {
			return;
		}

		$this->log(
			'File created: "%s"',
			$params,
			[
				'path',
			]
		);
	}

	/**
	 * Logs copying of files
	 *
	 * @param array $params
	 */
	public function copy(array $params) {
		$this->log(
			'File copied: "%s" to "%s"',
			$params,
			[
				'oldpath',
				'newpath',
			]
		);
	}

	/**
	 * Logs writing of files
	 *
	 * @param array $params
	 */
	public function write(array $params) {
		if ($params['path'] === '/' || $params['path'] === '' || $params['path'] === null) {
			return;
		}

		$this->log(
			'File written to: "%s"',
			$params,
			[
				'path',
			]
		);
	}

	/**
	 * Logs update of files
	 *
	 * @param array $params
	 */
	public function update(array $params) {
		$this->log(
			'File updated: "%s"',
			$params,
			[
				'path',
			]
		);
	}

	/**
	 * Logs deletions of files
	 *
	 * @param array $params
	 */
	public function delete(array $params) {
		$this->log(
			'File deleted: "%s"',
			$params,
			[
				'path',
			]
		);
	}

	/**
	 * Logs preview access to a file
	 *
	 * @param array $params
	 */
	public function preview(array $params) {
		$this->log(
			'Preview accessed: "%s" (width: "%s", height: "%s" crop: "%s", mode: "%s")',
			$params,
			[
				'path',
				'width',
				'height',
				'crop',
				'mode'
			]
		);
	}
}
