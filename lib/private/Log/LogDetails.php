<?php
/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OC\Log;

use OC\SystemConfig;

abstract class LogDetails {

	/** @var SystemConfig */
	private $config;

	public function __construct(SystemConfig $config) {
		$this->config = $config;
	}

	public function logDetails(string $app, $message, int $level): array {
		// default to ISO8601
		$format = $this->config->getValue('logdateformat', \DateTime::ATOM);
		$logTimeZone = $this->config->getValue('logtimezone', 'UTC');
		try {
			$timezone = new \DateTimeZone($logTimeZone);
		} catch (\Exception $e) {
			$timezone = new \DateTimeZone('UTC');
		}
		$time = \DateTime::createFromFormat("U.u", number_format(microtime(true), 4, ".", ""));
		if ($time === false) {
			$time = new \DateTime(null, $timezone);
		} else {
			// apply timezone if $time is created from UNIX timestamp
			$time->setTimezone($timezone);
		}
		$request = \OC::$server->getRequest();
		$reqId = $request->getId();
		$remoteAddr = $request->getRemoteAddress();
		// remove username/passwords from URLs before writing the to the log file
		$time = $time->format($format);
		$url = ($request->getRequestUri() !== '') ? $request->getRequestUri() : '--';
		$method = is_string($request->getMethod()) ? $request->getMethod() : '--';
		if($this->config->getValue('installed', false)) {
			$user = \OC_User::getUser() ? \OC_User::getUser() : '--';
		} else {
			$user = '--';
		}
		$userAgent = $request->getHeader('User-Agent');
		if ($userAgent === '') {
			$userAgent = '--';
		}
		$version = $this->config->getValue('version', '');
		$entry = compact(
			'reqId',
			'level',
			'time',
			'remoteAddr',
			'user',
			'app',
			'method',
			'url',
			'message',
			'userAgent',
			'version'
		);

		if(is_array($message) && !array_key_exists('Exception', $message)) {
			// Exception messages should stay as they are,
			// anything else modern is split to 'message' (string) and
			// data (array) fields
			$shortMessage = $message['message'] ?? '(no message provided)';
			$entry['data'] = $message;
			$entry['message'] = $shortMessage;
		}

		return $entry;
	}

	public function logDetailsAsJSON(string $app, $message, int $level): string {
		$entry = $this->logDetails($app, $message, $level);
		// PHP's json_encode only accept proper UTF-8 strings, loop over all
		// elements to ensure that they are properly UTF-8 compliant or convert
		// them manually.
		foreach($entry as $key => $value) {
			if(is_string($value)) {
				$testEncode = json_encode($value, JSON_UNESCAPED_SLASHES);
				if($testEncode === false) {
					$entry[$key] = utf8_encode($value);
				}
			}
		}
		return json_encode($entry, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES);
	}
}
