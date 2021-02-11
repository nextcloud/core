<?php

declare(strict_types=1);

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

use OCP\AppFramework\Http\Response;
use OCP\Files\NotPermittedException;
use RuntimeException;

/**
 * Interface IManager
 *
 * @since 18.0.0
 */
interface IManager {

	/**
	 * Register a new editor
	 *
	 * @since 18.0.0
	 * @param IEditor $directEditor
	 */
	public function registerDirectEditor(IEditor $directEditor): void;

	/**
	 * Open the editing page for a provided token
	 *
	 * @since 18.0.0
	 * @param string $token
	 * @return Response
	 */
	public function edit(string $token): Response;

	/**
	 * Create a new token based on the file path and editor details
	 *
	 * @since 18.0.0
	 * @param string $path
	 * @param string $editorId
	 * @param string $creatorId
	 * @param null $templateId
	 * @return string
	 * @throws NotPermittedException
	 * @throws RuntimeException
	 */
	public function create(string $path, string $editorId, string $creatorId, $templateId = null): string;

	/**
	 * Get the token details for a given token
	 *
	 * @since 18.0.0
	 * @param string $token
	 * @return IToken
	 */
	public function getToken(string $token): IToken;

	/**
	 * Cleanup expired tokens
	 *
	 * @since 18.0.0
	 * @return int number of deleted tokens
	 */
	public function cleanup(): int;

	/**
	 * Check if direct editing is enabled
	 *
	 * @since 20.0.0
	 * @return bool
	 */
	public function isEnabled(): bool;
}
