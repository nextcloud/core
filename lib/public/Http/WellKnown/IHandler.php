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

/**
 * Interface for an app handler that reacts to requests to Nextcloud's well
 * known URLs, e.g. to a WebFinger
 *
 * @ref https://tools.ietf.org/html/rfc5785
 *
 * @since 21.0.0
 */
interface IHandler {

	/**
	 * @param string $service the name of the well known service, e.g. 'webfinger'
	 * @param IRequestContext $context
	 * @param IResponse|null $previousResponse the response of the previous handler, if any
	 *
	 * @return IResponse|null a response object if the request could be handled, null otherwise
	 *
	 * @since 21.0.0
	 */
	public function handle(string $service, IRequestContext $context, ?IResponse $previousResponse): ?IResponse;
}
