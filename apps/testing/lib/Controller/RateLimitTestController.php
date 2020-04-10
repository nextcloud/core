<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Testing\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;

class RateLimitTestController extends Controller {
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @UserRateThrottle(limit=5, period=100)
	 * @AnonRateThrottle(limit=1, period=100)
	 *
	 * @return JSONResponse
	 */
	public function userAndAnonProtected() {
		return new JSONResponse();
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @AnonRateThrottle(limit=1, period=10)
	 *
	 * @return JSONResponse
	 */
	public function onlyAnonProtected() {
		return new JSONResponse();
	}
}
