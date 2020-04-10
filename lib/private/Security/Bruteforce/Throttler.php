<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Mark Berezovsky <xpnf@yandex.ru>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Security\Bruteforce;

use OC\Security\Normalizer\IpAddress;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\ILogger;

/**
 * Class Throttler implements the bruteforce protection for security actions in
 * Nextcloud.
 *
 * It is working by logging invalid login attempts to the database and slowing
 * down all login attempts from the same subnet. The max delay is 30 seconds and
 * the starting delay are 200 milliseconds. (after the first failed login)
 *
 * This is based on Paragonie's AirBrake for Airship CMS. You can find the original
 * code at https://github.com/paragonie/airship/blob/7e5bad7e3c0fbbf324c11f963fd1f80e59762606/src/Engine/Security/AirBrake.php
 *
 * @package OC\Security\Bruteforce
 */
class Throttler {
	const LOGIN_ACTION = 'login';

	/** @var IDBConnection */
	private $db;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var ILogger */
	private $logger;
	/** @var IConfig */
	private $config;

	/**
	 * @param IDBConnection $db
	 * @param ITimeFactory $timeFactory
	 * @param ILogger $logger
	 * @param IConfig $config
	 */
	public function __construct(IDBConnection $db,
								ITimeFactory $timeFactory,
								ILogger $logger,
								IConfig $config) {
		$this->db = $db;
		$this->timeFactory = $timeFactory;
		$this->logger = $logger;
		$this->config = $config;
	}

	/**
	 * Convert a number of seconds into the appropriate DateInterval
	 *
	 * @param int $expire
	 * @return \DateInterval
	 */
	private function getCutoff($expire) {
		$d1 = new \DateTime();
		$d2 = clone $d1;
		$d2->sub(new \DateInterval('PT' . $expire . 'S'));
		return $d2->diff($d1);
	}

	/**
	 * Register a failed attempt to bruteforce a security control
	 *
	 * @param string $action
	 * @param string $ip
	 * @param array $metadata Optional metadata logged to the database
	 * @suppress SqlInjectionChecker
	 */
	public function registerAttempt($action,
									$ip,
									array $metadata = []) {
		// No need to log if the bruteforce protection is disabled
		if($this->config->getSystemValue('auth.bruteforce.protection.enabled', true) === false) {
			return;
		}

		$ipAddress = new IpAddress($ip);
		$values = [
			'action' => $action,
			'occurred' => $this->timeFactory->getTime(),
			'ip' => (string)$ipAddress,
			'subnet' => $ipAddress->getSubnet(),
			'metadata' => json_encode($metadata),
		];

		$this->logger->notice(
			sprintf(
				'Bruteforce attempt from "%s" detected for action "%s".',
				$ip,
				$action
			),
			[
				'app' => 'core',
			]
		);

		$qb = $this->db->getQueryBuilder();
		$qb->insert('bruteforce_attempts');
		foreach($values as $column => $value) {
			$qb->setValue($column, $qb->createNamedParameter($value));
		}
		$qb->execute();
	}

	/**
	 * Check if the IP is whitelisted
	 *
	 * @param string $ip
	 * @return bool
	 */
	private function isIPWhitelisted($ip) {
		if($this->config->getSystemValue('auth.bruteforce.protection.enabled', true) === false) {
			return true;
		}

		$keys = $this->config->getAppKeys('bruteForce');
		$keys = array_filter($keys, function ($key) {
			$regex = '/^whitelist_/S';
			return preg_match($regex, $key) === 1;
		});

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$type = 4;
		} else if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			$type = 6;
		} else {
			return false;
		}

		$ip = inet_pton($ip);

		foreach ($keys as $key) {
			$cidr = $this->config->getAppValue('bruteForce', $key, null);

			$cx = explode('/', $cidr);
			$addr = $cx[0];
			$mask = (int)$cx[1];

			// Do not compare ipv4 to ipv6
			if (($type === 4 && !filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) ||
				($type === 6 && !filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))) {
				continue;
			}

			$addr = inet_pton($addr);

			$valid = true;
			for($i = 0; $i < $mask; $i++) {
				$part = ord($addr[(int)($i/8)]);
				$orig = ord($ip[(int)($i/8)]);

				$bitmask = 1 << (7 - ($i % 8));

				$part = $part & $bitmask;
				$orig = $orig & $bitmask;

				if ($part !== $orig) {
					$valid = false;
					break;
				}
			}

			if ($valid === true) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Get the throttling delay (in milliseconds)
	 *
	 * @param string $ip
	 * @param string $action optionally filter by action
	 * @return int
	 */
	public function getDelay($ip, $action = '') {
		$ipAddress = new IpAddress($ip);
		if ($this->isIPWhitelisted((string)$ipAddress)) {
			return 0;
		}

		$cutoffTime = (new \DateTime())
			->sub($this->getCutoff(43200))
			->getTimestamp();

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('bruteforce_attempts')
			->where($qb->expr()->gt('occurred', $qb->createNamedParameter($cutoffTime)))
			->andWhere($qb->expr()->eq('subnet', $qb->createNamedParameter($ipAddress->getSubnet())));

		if ($action !== '') {
			$qb->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action)));
		}

		$attempts = count($qb->execute()->fetchAll());

		if ($attempts === 0) {
			return 0;
		}

		$maxDelay = 25;
		$firstDelay = 0.1;
		if ($attempts > (8 * PHP_INT_SIZE - 1))  {
			// Don't ever overflow. Just assume the maxDelay time:s
			$firstDelay = $maxDelay;
		} else {
			$firstDelay *= pow(2, $attempts);
			if ($firstDelay > $maxDelay) {
				$firstDelay = $maxDelay;
			}
		}
		return (int) \ceil($firstDelay * 1000);
	}

	/**
	 * Reset the throttling delay for an IP address, action and metadata
	 *
	 * @param string $ip
	 * @param string $action
	 * @param string $metadata
	 */
	public function resetDelay($ip, $action, $metadata) {
		$ipAddress = new IpAddress($ip);
		if ($this->isIPWhitelisted((string)$ipAddress)) {
			return;
		}

		$cutoffTime = (new \DateTime())
			->sub($this->getCutoff(43200))
			->getTimestamp();

		$qb = $this->db->getQueryBuilder();
		$qb->delete('bruteforce_attempts')
			->where($qb->expr()->gt('occurred', $qb->createNamedParameter($cutoffTime)))
			->andWhere($qb->expr()->eq('subnet', $qb->createNamedParameter($ipAddress->getSubnet())))
			->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action)))
			->andWhere($qb->expr()->eq('metadata', $qb->createNamedParameter(json_encode($metadata))));

		$qb->execute();
	}

	/**
	 * Will sleep for the defined amount of time
	 *
	 * @param string $ip
	 * @param string $action optionally filter by action
	 * @return int the time spent sleeping
	 */
	public function sleepDelay($ip, $action = '') {
		$delay = $this->getDelay($ip, $action);
		usleep($delay * 1000);
		return $delay;
	}
}
