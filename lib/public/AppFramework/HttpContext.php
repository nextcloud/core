<?php
/**
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP\AppFramework;

use OCP\AppFramework\Http\Response;
use OCP\IRequest;

/**
 * Class representing context available in middlewares during request processing
 * @since 17.0.0
 */
class HttpContext {
	/** @var IRequest */
	public $request;

	/**
	 * Construct HttpCOntext based in IRequest
	 * @param IRequest $request
	 * @since 17.0.0
	 */
	public function __construct(IRequest $request = null) {
		$this->request = $request;
	}
}
