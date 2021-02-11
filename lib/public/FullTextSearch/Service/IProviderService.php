<?php

declare(strict_types=1);

/**
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
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

namespace OCP\FullTextSearch\Service;

/**
 * Interface IProviderService
 *
 * @since 15.0.0
 *
 */
interface IProviderService {


	/**
	 * Check if the provider $providerId is already indexed.
	 *
	 * @since 15.0.0
	 *
	 * @param string $providerId
	 *
	 * @return bool
	 */
	public function isProviderIndexed(string $providerId);


	/**
	 * Add the Javascript API in the navigation page of an app.
	 *
	 * @since 15.0.0
	 */
	public function addJavascriptAPI();
}
