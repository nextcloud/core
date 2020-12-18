<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
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

namespace OCP;

/**
 * Generic exception thrown when an AvatarProvider can not perform an action
 *
 * @since 21.0.0
 */
class AvatarProviderException extends \RuntimeException {

	/**
	 * @param string $message
	 * @param int $code
	 * @param \Exception $previous
	 */
	public function __construct(string $message = "", int $code = 0, \Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}
