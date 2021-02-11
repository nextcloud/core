<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCP\Http\WellKnown;

use OCP\IRequest;

/**
 * The context object for \OCP\Http\IWellKnownHandler::handle
 *
 * Objects of this type will transport any optional information, e.g. the request
 * object through which the app well known handler can obtain URL parameters
 *
 * @since 21.0.0
 */
interface IRequestContext {

	/**
	 * @return IRequest
	 *
	 * @since 21.0.0
	 */
	public function getHttpRequest(): IRequest;
}
