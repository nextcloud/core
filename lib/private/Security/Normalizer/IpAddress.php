<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Konrad Bucheli <kb@open.ch>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OC\Security\Normalizer;

/**
 * Class IpAddress is used for normalizing IPv4 and IPv6 addresses in security
 * relevant contexts in Nextcloud.
 *
 * @package OC\Security\Normalizer
 */
class IpAddress {
	/** @var string */
	private $ip;

	/**
	 * @param string $ip IP to normalized
	 */
	public function __construct(string $ip) {
		$this->ip = $ip;
	}

	/**
	 * Return the given subnet for an IPv4 address and mask bits
	 *
	 * @param string $ip
	 * @param int $maskBits
	 * @return string
	 */
	private function getIPv4Subnet(string $ip, int $maskBits = 32): string {
		$binary = \inet_pton($ip);
		for ($i = 32; $i > $maskBits; $i -= 8) {
			$j = \intdiv($i, 8) - 1;
			$k = (int) \min(8, $i - $maskBits);
			$mask = (0xff - ((2 ** $k) - 1));
			$int = \unpack('C', $binary[$j]);
			$binary[$j] = \pack('C', $int[1] & $mask);
		}
		return \inet_ntop($binary).'/'.$maskBits;
	}

	/**
	 * Return the given subnet for an IPv6 address and mask bits
	 *
	 * @param string $ip
	 * @param int $maskBits
	 * @return string
	 */
	private function getIPv6Subnet(string $ip, int $maskBits = 48): string {
		if ($ip[0] === '[' && $ip[-1] === ']') { // If IP is with brackets, for example [::1]
			$ip = substr($ip, 1, strlen($ip) - 2);
		}
		$pos = strpos($ip, '%'); // if there is an explicit interface added to the IP, e.g. fe80::ae2d:d1e7:fe1e:9a8d%enp2s0
		if ($pos !== false) {
			$ip = substr($ip, 0, $pos-1);
		}
		$binary = \inet_pton($ip);
		for ($i = 128; $i > $maskBits; $i -= 8) {
			$j = \intdiv($i, 8) - 1;
			$k = (int) \min(8, $i - $maskBits);
			$mask = (0xff - ((2 ** $k) - 1));
			$int = \unpack('C', $binary[$j]);
			$binary[$j] = \pack('C', $int[1] & $mask);
		}
		return \inet_ntop($binary).'/'.$maskBits;
	}

	/**
	 * Gets either the /32 (IPv4) or the /128 (IPv6) subnet of an IP address
	 *
	 * @return string
	 */
	public function getSubnet(): string {
		if (\preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $this->ip)) {
			return $this->getIPv4Subnet(
				$this->ip,
				32
			);
		}
		return $this->getIPv6Subnet(
			$this->ip,
			128
		);
	}

	/**
	 * Returns the specified IP address
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->ip;
	}
}
