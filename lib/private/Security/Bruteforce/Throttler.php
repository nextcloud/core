<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Johannes Riedel <joeried@users.noreply.github.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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
use OCP\Security\Bruteforce\MaxDelayReached;

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
	public const LOGIN_ACTION = 'login';
	public const MAX_DELAY = 25;
	public const MAX_DELAY_MS = 25000; // in milliseconds
	public const MAX_ATTEMPTS = 10;

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
	private function getCutoff(int $expire): \DateInterval {
		$d1 = new \DateTime();
		$d2 = clone $d1;
		$d2->sub(new \DateInterval('PT' . $expire . 'S'));
		return $d2->diff($d1);
	}

	/**
	 *  Calculate the cut off timestamp
	 *
	 * @param float $maxAgeHours
	 * @return int
	 */
	private function getCutoffTimestamp(float $maxAgeHours = 12.0): int {
		return (new \DateTime())
			->sub($this->getCutoff((int) ($maxAgeHours * 3600)))
			->getTimestamp();
	}

	/**
	 * Register a failed attempt to bruteforce a security control
	 *
	 * @param string $action
	 * @param string $ip
	 * @param array $metadata Optional metadata logged to the database
	 */
	public function registerAttempt(string $action,
									string $ip,
									array $metadata = []): void {
		// No need to log if the bruteforce protection is disabled
		if ($this->config->getSystemValue('auth.bruteforce.protection.enabled', true) === false) {
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
		foreach ($values as $column => $value) {
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
	private function isIPWhitelisted(string $ip): bool {
		if ($this->config->getSystemValue('auth.bruteforce.protection.enabled', true) === false) {
			return true;
		}

		$keys = $this->config->getAppKeys('bruteForce');
		$keys = array_filter($keys, function ($key) {
			return 0 === strpos($key, 'whitelist_');
		});

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$type = 4;
		} elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
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
			for ($i = 0; $i < $mask; $i++) {
				$part = ord($addr[(int)($i / 8)]);
				$orig = ord($ip[(int)($i / 8)]);

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
	 * @param float $maxAgeHours
	 * @return int
	 */
	public function getAttempts(string $ip, string $action = '', float $maxAgeHours = 12): int {
		if ($maxAgeHours > 48) {
			$this->logger->error('Bruteforce has to use less than 48 hours');
			$maxAgeHours = 48;
		}

		if ($ip === '') {
			return 0;
		}

		$ipAddress = new IpAddress($ip);
		if ($this->isIPWhitelisted((string)$ipAddress)) {
			return 0;
		}

		$cutoffTime = $this->getCutoffTimestamp($maxAgeHours);

		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'attempts'))
			->from('bruteforce_attempts')
			->where($qb->expr()->gt('occurred', $qb->createNamedParameter($cutoffTime)))
			->andWhere($qb->expr()->eq('subnet', $qb->createNamedParameter($ipAddress->getSubnet())));

		if ($action !== '') {
			$qb->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action)));
		}

		$result = $qb->execute();
		$row = $result->fetch();
		$result->closeCursor();

		return (int) $row['attempts'];
	}

	/**
	 * Get the throttling delay (in milliseconds)
	 *
	 * @param string $ip
	 * @param string $action optionally filter by action
	 * @return int
	 */
	public function getDelay(string $ip, string $action = ''): int {
		$attempts = $this->getAttempts($ip, $action);
		if ($attempts === 0) {
			return 0;
		}

		$firstDelay = 0.1;
		if ($attempts > self::MAX_ATTEMPTS) {
			// Don't ever overflow. Just assume the maxDelay time:s
			return self::MAX_DELAY_MS;
		}

		$delay = $firstDelay * 2 ** $attempts;
		if ($delay > self::MAX_DELAY) {
			return self::MAX_DELAY_MS;
		}
		return (int) \ceil($delay * 1000);
	}

	/**
	 * Reset the throttling delay for an IP address, action and metadata
	 *
	 * @param string $ip
	 * @param string $action
	 * @param array $metadata
	 */
	public function resetDelay(string $ip, string $action, array $metadata): void {
		$ipAddress = new IpAddress($ip);
		if ($this->isIPWhitelisted((string)$ipAddress)) {
			return;
		}

		$cutoffTime = $this->getCutoffTimestamp();

		$qb = $this->db->getQueryBuilder();
		$qb->delete('bruteforce_attempts')
			->where($qb->expr()->gt('occurred', $qb->createNamedParameter($cutoffTime)))
			->andWhere($qb->expr()->eq('subnet', $qb->createNamedParameter($ipAddress->getSubnet())))
			->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action)))
			->andWhere($qb->expr()->eq('metadata', $qb->createNamedParameter(json_encode($metadata))));

		$qb->execute();
	}

	/**
	 * Reset the throttling delay for an IP address
	 *
	 * @param string $ip
	 */
	public function resetDelayForIP($ip) {
		$cutoffTime = $this->getCutoffTimestamp();

		$qb = $this->db->getQueryBuilder();
		$qb->delete('bruteforce_attempts')
			->where($qb->expr()->gt('occurred', $qb->createNamedParameter($cutoffTime)))
			->andWhere($qb->expr()->eq('ip', $qb->createNamedParameter($ip)));

		$qb->execute();
	}

	/**
	 * Will sleep for the defined amount of time
	 *
	 * @param string $ip
	 * @param string $action optionally filter by action
	 * @return int the time spent sleeping
	 */
	public function sleepDelay(string $ip, string $action = ''): int {
		$delay = $this->getDelay($ip, $action);
		usleep($delay * 1000);
		return $delay;
	}

	/**
	 * Will sleep for the defined amount of time unless maximum was reached in the last 30 minutes
	 * In this case a "429 Too Many Request" exception is thrown
	 *
	 * @param string $ip
	 * @param string $action optionally filter by action
	 * @return int the time spent sleeping
	 * @throws MaxDelayReached when reached the maximum
	 */
	public function sleepDelayOrThrowOnMax(string $ip, string $action = ''): int {
		$delay = $this->getDelay($ip, $action);
		if (($delay === self::MAX_DELAY_MS) && $this->getAttempts($ip, $action, 0.5) > self::MAX_ATTEMPTS) {
			// If the ip made too many attempts within the last 30 mins we don't execute anymore
			throw new MaxDelayReached('Reached maximum delay');
		}
		usleep($delay * 1000);
		return $delay;
	}
}
