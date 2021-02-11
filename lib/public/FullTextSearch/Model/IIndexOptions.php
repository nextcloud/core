<?php

declare(strict_types=1);

/**
 * @copyright 2018
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

namespace OCP\FullTextSearch\Model;

/**
 * Interface IIndexOptions
 *
 * IndexOptions are created in FullTextSearch when an admin initiate an index
 * from the command line:
 *
 * ./occ fulltextsearch:index "{\"option1\": \"value\", \"option2\": true}"
 *
 * @since 15.0.0
 *
 */
interface IIndexOptions {


	/**
	 * Get the value (as a string) for an option.
	 *
	 * @since 15.0.0
	 *
	 * @param string $option
	 * @param string $default
	 *
	 * @return string
	 */
	public function getOption(string $option, string $default = ''): string;

	/**
	 * Get the value (as an array) for an option.
	 *
	 * @since 15.0.0
	 *
	 * @param string $option
	 * @param array $default
	 *
	 * @return array
	 */
	public function getOptionArray(string $option, array $default = []): array;

	/**
	 * Get the value (as an boolean) for an option.
	 *
	 * @since 15.0.0
	 *
	 * @param string $option
	 * @param bool $default
	 *
	 * @return bool
	 */
	public function getOptionBool(string $option, bool $default): bool;
}
