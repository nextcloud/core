<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Johannes Ernst <jernst@indiecomputing.com>
 * @author Johannes Koenig <mail@jokoenig.de>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OC\Security;

use OC\AppFramework\Http\Request;
use OCP\IConfig;

/**
 * Class TrustedDomain
 *
 * @package OC\Security
 */
class TrustedDomainHelper {
	/** @var IConfig */
	private $config;

	/**
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * Strips a potential port from a domain (in format domain:port)
	 * @param string $host
	 * @return string $host without appended port
	 */
	private function getDomainWithoutPort($host) {
		$pos = strrpos($host, ':');
		if ($pos !== false) {
			$port = substr($host, $pos + 1);
			if (is_numeric($port)) {
				$host = substr($host, 0, $pos);
			}
		}
		return $host;
	}

	/**
	 * Checks whether a domain is considered as trusted from the list
	 * of trusted domains. If no trusted domains have been configured, returns
	 * true.
	 * This is used to prevent Host Header Poisoning.
	 * @param string $domainWithPort
	 * @return bool true if the given domain is trusted or if no trusted domains
	 * have been configured
	 */
	public function isTrustedDomain($domainWithPort) {
		// overwritehost is always trusted
		if ($this->config->getSystemValue('overwritehost') !== '') {
			return true;
		}

		$domain = $this->getDomainWithoutPort($domainWithPort);

		// Read trusted domains from config
		$trustedList = $this->config->getSystemValue('trusted_domains', []);
		if (!is_array($trustedList)) {
			return false;
		}

		// Always allow access from localhost
		if (preg_match(Request::REGEX_LOCALHOST, $domain) === 1) {
			return true;
		}
		// Reject misformed domains in any case
		if (strpos($domain,'-') === 0 || strpos($domain,'..') !== false) {
			return false;
		}
		// Match, allowing for * wildcards
		foreach ($trustedList as $trusted) {
			if (gettype($trusted) !== 'string') {
				break;
			}
			$regex = '/^' . implode('[-\.a-zA-Z0-9]*', array_map(function ($v) { return preg_quote($v, '/'); }, explode('*', $trusted))) . '$/i';
			if (preg_match($regex, $domain) || preg_match($regex, $domainWithPort)) {
				return true;
			}
		}
		return false;
	}
}
