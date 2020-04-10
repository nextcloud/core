<?php
/**
 *
 *
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

namespace OC\AppFramework\Middleware\Security\Exceptions;

use OCP\AppFramework\Http;

/**
 * Class StrictCookieMissingException is thrown when the strict cookie has not
 * been sent with the request but is required.
 *
 * @package OC\AppFramework\Middleware\Security\Exceptions
 */
class StrictCookieMissingException extends SecurityException {
	public function __construct() {
		parent::__construct('Strict Cookie has not been found in request.', Http::STATUS_PRECONDITION_FAILED);
	}
}
