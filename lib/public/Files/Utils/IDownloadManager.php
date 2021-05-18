<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\Files\Utils;

use OCP\Files\NotFoundException;
use RuntimeException;

/**
 * Interface IDownloadManager
 *
 * The usual process is to prepare a file download via POST request. Follow
 * up with a GET request providing the token for best browser integration.
 * Announcing the download via POST enables to hide the payload in the
 * request body rather then the URL, which also lifts limitations on
 * data length.
 *
 * @package OCP\Files
 *
 * @since 22.0.0
 */
interface IDownloadManager {

	/**
	 * Register download data and receive a token to access it later on.
	 *
	 * The provided data will be returned on retrieve() again. The structure
	 * is up to the consumer, it is not being processed, but only stored by
	 * the manager.
	 *
	 * @throws RuntimeException
	 * @since 22.0.0
	 */
	public function register(array $data): string;

	/**
	 * Retrieves the download data for the provided token.
	 *
	 * @throws NotFoundException
	 * @since 22.0.0
	 */
	public function retrieve(string $token): array;
}
