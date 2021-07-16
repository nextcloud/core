<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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
namespace OCA\Files_Versions;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class Expiration {

	// how long do we keep files a version if no other value is defined in the config file (unit: days)
	public const NO_OBLIGATION = -1;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var string */
	private $retentionObligation;

	/** @var int */
	private $minAge;

	/** @var int */
	private $maxAge;

	/** @var bool */
	private $canPurgeToSaveSpace;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(IConfig $config, ITimeFactory $timeFactory, LoggerInterface $logger) {
		$this->timeFactory = $timeFactory;
		$this->logger = $logger;
		$this->retentionObligation = $config->getSystemValue('versions_retention_obligation', 'auto');

		if ($this->retentionObligation !== 'disabled') {
			$this->parseRetentionObligation();
		}
	}

	/**
	 * Is versions expiration enabled
	 * @return bool
	 */
	public function isEnabled(): bool {
		return $this->retentionObligation !== 'disabled';
	}

	/**
	 * Is default expiration active
	 */
	public function shouldAutoExpire(): bool {
		return $this->minAge === self::NO_OBLIGATION
				|| $this->maxAge === self::NO_OBLIGATION;
	}

	/**
	 * Check if given timestamp in expiration range
	 * @param int $timestamp
	 * @param bool $quotaExceeded
	 * @return bool
	 */
	public function isExpired(int $timestamp, bool $quotaExceeded = false): bool {
		// No expiration if disabled
		if (!$this->isEnabled()) {
			return false;
		}

		// Purge to save space (if allowed)
		if ($quotaExceeded && $this->canPurgeToSaveSpace) {
			return true;
		}

		$time = $this->timeFactory->getTime();
		// Never expire dates in future e.g. misconfiguration or negative time
		// adjustment
		if ($time < $timestamp) {
			return false;
		}

		// Purge as too old
		if ($this->maxAge !== self::NO_OBLIGATION) {
			$maxTimestamp = $time - ($this->maxAge * 86400);
			$isOlderThanMax = $timestamp < $maxTimestamp;
		} else {
			$isOlderThanMax = false;
		}

		if ($this->minAge !== self::NO_OBLIGATION) {
			// older than Min obligation and we are running out of quota?
			$minTimestamp = $time - ($this->minAge * 86400);
			$isMinReached = ($timestamp < $minTimestamp) && $quotaExceeded;
		} else {
			$isMinReached = false;
		}

		return $isOlderThanMax || $isMinReached;
	}

	/**
	 * Get maximal retention obligation as a timestamp
	 *
	 * @return int|false
	 */
	public function getMaxAgeAsTimestamp() {
		$maxAge = false;
		if ($this->isEnabled() && $this->maxAge !== self::NO_OBLIGATION) {
			$time = $this->timeFactory->getTime();
			$maxAge = $time - ($this->maxAge * 86400);
		}
		return $maxAge;
	}

	/**
	 * Read versions_retention_obligation, validate it
	 * and set private members accordingly
	 */
	private function parseRetentionObligation(): void {
		$splitValues = explode(',', $this->retentionObligation);
		if (!isset($splitValues[0])) {
			$minValue = 'auto';
		} else {
			$minValue = trim($splitValues[0]);
		}

		if (!isset($splitValues[1])) {
			$maxValue = 'auto';
		} else {
			$maxValue = trim($splitValues[1]);
		}

		$isValid = true;
		// Validate
		if (!ctype_digit($minValue) && $minValue !== 'auto') {
			$isValid = false;
			$this->logger->warning(
					$minValue . ' is not a valid value for minimal versions retention obligation. Check versions_retention_obligation in your config.php. Falling back to auto.',
					['app' => 'files_versions']
			);
		}

		if (!ctype_digit($maxValue) && $maxValue !== 'auto') {
			$isValid = false;
			$this->logger->warning(
					$maxValue . ' is not a valid value for maximal versions retention obligation. Check versions_retention_obligation in your config.php. Falling back to auto.',
					['app' => 'files_versions']
			);
		}

		if (!$isValid) {
			$minValue = 'auto';
			$maxValue = 'auto';
		}


		if ($minValue === 'auto' && $maxValue === 'auto') {
			// Default: Delete anytime if space needed
			$this->minAge = self::NO_OBLIGATION;
			$this->maxAge = self::NO_OBLIGATION;
			$this->canPurgeToSaveSpace = true;
		} elseif ($minValue !== 'auto' && $maxValue === 'auto') {
			// Keep for X days but delete anytime if space needed
			$this->minAge = (int)$minValue;
			$this->maxAge = self::NO_OBLIGATION;
			$this->canPurgeToSaveSpace = true;
		} elseif ($minValue === 'auto' && $maxValue !== 'auto') {
			// Delete anytime if space needed, Delete all older than max automatically
			$this->minAge = self::NO_OBLIGATION;
			$this->maxAge = (int)$maxValue;
			$this->canPurgeToSaveSpace = true;
		} elseif ($minValue !== 'auto' && $maxValue !== 'auto') {
			// Delete all older than max OR older than min if space needed

			// Max < Min as per https://github.com/owncloud/core/issues/16301
			if ($maxValue < $minValue) {
				$maxValue = $minValue;
			}

			$this->minAge = (int)$minValue;
			$this->maxAge = (int)$maxValue;
			$this->canPurgeToSaveSpace = false;
		}
	}
}
