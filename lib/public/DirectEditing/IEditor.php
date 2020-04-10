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

/**
 * @since 18.0.0
 */
interface IEditor {

	/**
	 * Return a unique identifier for the editor
	 *
	 * e.g. richdocuments
	 *
	 * @since 18.0.0
	 * @return string
	 */
	public function getId(): string;

	/**
	 * Return a readable name for the editor
	 *
	 * e.g. Collabora Online
	 *
	 * @since 18.0.0
	 * @return string
	 */
	public function getName(): string;

	/**
	 * A list of mimetypes that should open the editor by default
	 *
	 * @since 18.0.0
	 * @return string[]
	 */
	public function getMimetypes(): array;

	/**
	 * A list of mimetypes that can be opened in the editor optionally
	 *
	 * @since 18.0.0
	 * @return string[]
	 */
	public function getMimetypesOptional(): array;

	/**
	 * Return a list of file creation options to be presented to the user
	 *
	 * @since 18.0.0
	 * @return ACreateFromTemplate[]|ACreateEmpty[]
	 */
	public function getCreators(): array;

	/**
	 * Return if the view is able to securely view a file without downloading it to the browser
	 *
	 * @since 18.0.0
	 * @return bool
	 */
	public function isSecure(): bool;

	/**
	 * Return a template response for displaying the editor
	 *
	 * open can only be called once when the client requests the editor with a one-time-use token
	 * For handling editing and later requests, editors need to impelement their own token handling and take care of invalidation
	 *
	 * This behavior is similar to the current direct editing implementation in collabora where we generate a one-time token and switch over to the regular wopi token for the actual editing/saving process
	 *
	 * @since 18.0.0
	 * @return Response
	 */
	public function open(IToken $token): Response;
}
