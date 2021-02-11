<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Fabrizio Steiner <fabrizio.steiner@gmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP\Security;

/**
 * Class SecureRandom provides a wrapper around the random_int function to generate
 * secure random strings. For PHP 7 the native CSPRNG is used, older versions do
 * use a fallback.
 *
 * Usage:
 * \OC::$server->getSecureRandom()->generate(10);
 *
 * @since 8.0.0
 */
interface ISecureRandom {

	/**
	 * Flags for characters that can be used for <code>generate($length, $characters)</code>
	 */
	public const CHAR_UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	public const CHAR_LOWER = 'abcdefghijklmnopqrstuvwxyz';
	public const CHAR_DIGITS = '0123456789';
	public const CHAR_SYMBOLS = '!\"#$%&\\\'()*+,-./:;<=>?@[\]^_`{|}~';

	/**
	 * Characters that can be used for <code>generate($length, $characters)</code>, to
	 * generate human readable random strings. Lower- and upper-case characters and digits
	 * are included. Characters which are ambiguous are excluded, such as I, l, and 1 and so on.
	 */
	public const CHAR_HUMAN_READABLE = 'abcdefgijkmnopqrstwxyzABCDEFGHJKLMNPQRSTWXYZ23456789';

	/**
	 * Generate a random string of specified length.
	 * @param int $length The length of the generated string
	 * @param string $characters An optional list of characters to use if no character list is
	 * 							specified all valid base64 characters are used.
	 * @return string
	 * @since 8.0.0
	 */
	public function generate(int $length,
							 string $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'): string;
}
