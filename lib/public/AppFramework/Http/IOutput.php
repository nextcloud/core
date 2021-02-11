<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
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

namespace OCP\AppFramework\Http;

/**
 * Very thin wrapper class to make output testable
 * @since 8.1.0
 */
interface IOutput {

	/**
	 * @param string $out
	 * @since 8.1.0
	 */
	public function setOutput($out);

	/**
	 * @param string|resource $path or file handle
	 *
	 * @return bool false if an error occurred
	 * @since 8.1.0
	 */
	public function setReadfile($path);

	/**
	 * @param string $header
	 * @since 8.1.0
	 */
	public function setHeader($header);

	/**
	 * @return int returns the current http response code
	 * @since 8.1.0
	 */
	public function getHttpResponseCode();

	/**
	 * @param int $code sets the http status code
	 * @since 8.1.0
	 */
	public function setHttpResponseCode($code);

	/**
	 * @param string $name
	 * @param string $value
	 * @param int $expire
	 * @param string $path
	 * @param string $domain
	 * @param bool $secure
	 * @param bool $httpOnly
	 * @param string $sameSite (added in 20)
	 * @since 8.1.0
	 */
	public function setCookie($name, $value, $expire, $path, $domain, $secure, $httpOnly, $sameSite = 'Lax');
}
