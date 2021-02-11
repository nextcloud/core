<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author AW-UC <git@a-wesemann.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OC;

use OCP\ILogger;

class NaturalSort {
	private static $instance;
	private $collator;
	private $cache = [];

	/**
	 * Instantiate a new \OC\NaturalSort instance.
	 * @param object $injectedCollator
	 */
	public function __construct($injectedCollator = null) {
		// inject an instance of \Collator('en_US') to force using the php5-intl Collator
		// or inject an instance of \OC\NaturalSort_DefaultCollator to force using Owncloud's default collator
		if (isset($injectedCollator)) {
			$this->collator = $injectedCollator;
			\OCP\Util::writeLog('core', 'forced use of '.get_class($injectedCollator), ILogger::DEBUG);
		}
	}

	/**
	 * Split the given string in chunks of numbers and strings
	 * @param string $t string
	 * @return array of strings and number chunks
	 */
	private function naturalSortChunkify($t) {
		// Adapted and ported to PHP from
		// http://my.opera.com/GreyWyvern/blog/show.dml/1671288
		if (isset($this->cache[$t])) {
			return $this->cache[$t];
		}
		$tz = [];
		$x = 0;
		$y = -1;
		$n = null;

		while (isset($t[$x])) {
			$c = $t[$x];
			// only include the dot in strings
			$m = ((!$n && $c === '.') || ($c >= '0' && $c <= '9'));
			if ($m !== $n) {
				// next chunk
				$y++;
				$tz[$y] = '';
				$n = $m;
			}
			$tz[$y] .= $c;
			$x++;
		}
		$this->cache[$t] = $tz;
		return $tz;
	}

	/**
	 * Returns the string collator
	 * @return \Collator string collator
	 */
	private function getCollator() {
		if (!isset($this->collator)) {
			// looks like the default is en_US_POSIX which yields wrong sorting with
			// German umlauts, so using en_US instead
			if (class_exists('Collator')) {
				$this->collator = new \Collator('en_US');
			} else {
				$this->collator = new \OC\NaturalSort_DefaultCollator();
			}
		}
		return $this->collator;
	}

	/**
	 * Compare two strings to provide a natural sort
	 * @param string $a first string to compare
	 * @param string $b second string to compare
	 * @return int -1 if $b comes before $a, 1 if $a comes before $b
	 * or 0 if the strings are identical
	 */
	public function compare($a, $b) {
		// Needed because PHP doesn't sort correctly when numbers are enclosed in
		// parenthesis, even with NUMERIC_COLLATION enabled.
		// For example it gave ["test (2).txt", "test.txt"]
		// instead of ["test.txt", "test (2).txt"]
		$aa = self::naturalSortChunkify($a);
		$bb = self::naturalSortChunkify($b);

		for ($x = 0; isset($aa[$x]) && isset($bb[$x]); $x++) {
			$aChunk = $aa[$x];
			$bChunk = $bb[$x];
			if ($aChunk !== $bChunk) {
				// test first character (character comparison, not number comparison)
				if ($aChunk[0] >= '0' && $aChunk[0] <= '9' && $bChunk[0] >= '0' && $bChunk[0] <= '9') {
					$aNum = (int)$aChunk;
					$bNum = (int)$bChunk;
					return $aNum - $bNum;
				}
				return self::getCollator()->compare($aChunk, $bChunk);
			}
		}
		return count($aa) - count($bb);
	}

	/**
	 * Returns a singleton
	 * @return \OC\NaturalSort instance
	 */
	public static function getInstance() {
		if (!isset(self::$instance)) {
			self::$instance = new \OC\NaturalSort();
		}
		return self::$instance;
	}
}
