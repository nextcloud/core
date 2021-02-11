<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Http;

class CookieHelper {
	public const SAMESITE_NONE = 0;
	public const SAMESITE_LAX = 1;
	public const SAMESITE_STRICT = 2;

	public static function setCookie(string $name,
									 string $value = '',
									 int $maxAge = 0,
									 string $path = '',
									 string $domain = '',
									 bool $secure = false,
									 bool $httponly = false,
									 int $samesite = self::SAMESITE_NONE) {
		$header = sprintf(
			'Set-Cookie: %s=%s',
			$name,
			rawurlencode($value)
		);

		if ($path !== '') {
			$header .= sprintf('; Path=%s', $path);
		}

		if ($domain !== '') {
			$header .= sprintf('; Domain=%s', $domain);
		}

		if ($maxAge > 0) {
			$header .= sprintf('; Max-Age=%d', $maxAge);
		}

		if ($secure) {
			$header .= '; Secure';
		}

		if ($httponly) {
			$header .= '; HttpOnly';
		}

		if ($samesite === self::SAMESITE_LAX) {
			$header .= '; SameSite=Lax';
		} elseif ($samesite === self::SAMESITE_STRICT) {
			$header .= '; SameSite=Strict';
		}

		header($header, false);
	}
}
