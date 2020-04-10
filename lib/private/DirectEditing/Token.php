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

namespace OC\DirectEditing;

use OCP\DirectEditing\IToken;
use OCP\Files\File;

class Token implements IToken {

	/** @var Manager */
	private $manager;
	private $data;

	public function __construct(Manager $manager, $data) {
		$this->manager = $manager;
		$this->data = $data;
	}

	public function extend(): void {
		$this->manager->refreshToken($this->data['token']);
	}

	public function invalidate(): void {
		$this->manager->invalidateToken($this->data['token']);
	}

	public function getFile(): File {
		if ($this->data['share_id'] !== null) {
			return $this->manager->getShareForToken($this->data['share_id']);
		}
		return $this->manager->getFileForToken($this->data['user_id'], $this->data['file_id'], $this->data['file_path']);
	}

	public function getToken(): string {
		return $this->data['token'];
	}

	public function useTokenScope(): void {
		$this->manager->invokeTokenScope($this->data['user_id']);
	}

	public function hasBeenAccessed(): bool {
		return (bool) $this->data['accessed'];
	}

	public function getEditor(): string {
		return $this->data['editor_id'];
	}

	public function getUser(): string {
		return $this->data['user_id'];
	}

}
