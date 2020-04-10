<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

class Utils {

	/**
	 * Waits at most $timeout seconds for the given condition to be true,
	 * checking it again every $timeoutStep seconds.
	 *
	 * Note that the timeout is no longer taken into account when a condition is
	 * met; that is, true will be returned if the condition is met before the
	 * timeout expires, but also if it is met exactly when the timeout expires.
	 * For example, even if the timeout is set to 0, the condition will be
	 * checked at least once, and true will be returned in that case if the
	 * condition was met.
	 *
	 * @param \Closure $conditionCallback the condition to wait for, as a
	 *        function that returns a boolean.
	 * @param float $timeout the number of seconds (decimals allowed) to wait at
	 *        most for the condition to be true.
	 * @param float $timeoutStep the number of seconds (decimals allowed) to
	 *        wait before checking the condition again.
	 * @return boolean true if the condition is met before (or exactly when) the
	 *         timeout expires, false otherwise.
	 */
	public static function waitFor($conditionCallback, $timeout, $timeoutStep) {
		$elapsedTime = 0;
		$conditionMet = false;

		while (!($conditionMet = $conditionCallback()) && $elapsedTime < $timeout) {
			usleep($timeoutStep * 1000000);

			$elapsedTime += $timeoutStep;
		}

		return $conditionMet;
	}

	/**
	 * Waits at most $timeout seconds for the server at the given URL to be up,
	 * checking it again every $timeoutStep seconds.
	 *
	 * Note that it does not verify whether the URL returns a valid HTTP status
	 * or not; it simply checks that the server at the given URL is accessible.
	 *
	 * @param string $url the URL for the server to check.
	 * @param float $timeout the number of seconds (decimals allowed) to wait at
	 *        most for the server.
	 * @param float $timeoutStep the number of seconds (decimals allowed) to
	 *        wait before checking the server again; by default, 0.5 seconds.
	 * @return boolean true if the server was found, false otherwise.
	 */
	public static function waitForServer($url, $timeout, $timeoutStep = 0.5) {
		$isServerUpCallback = function () use ($url) {
			$curlHandle = curl_init($url);

			// Returning the transfer as the result of curl_exec prevents the
			// transfer from being written to the output.
			curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);

			$transfer = curl_exec($curlHandle);

			curl_close($curlHandle);

			return $transfer !== false;
		};
		return self::waitFor($isServerUpCallback, $timeout, $timeoutStep);
	}

}
