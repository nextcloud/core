<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author J0WI <J0WI@users.noreply.github.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OC\Share;

use OCP\ILogger;

class SearchResultSorter {
	private $search;
	private $encoding;
	private $key;
	private $log;

	/**
	 * @param string $search the search term as was given by the user
	 * @param string $key the array key containing the value that should be compared
	 * against
	 * @param string $encoding optional, encoding to use, defaults to UTF-8
	 * @param ILogger $log optional
	 */
	public function __construct($search, $key, ILogger $log = null, $encoding = 'UTF-8') {
		$this->encoding = $encoding;
		$this->key = $key;
		$this->log = $log;
		$this->search = mb_strtolower($search, $this->encoding);
	}

	/**
	 * User and Group names matching the search term at the beginning shall appear
	 * on top of the share dialog. Following entries in alphabetical order.
	 * Callback function for usort. https://www.php.net/usort
	 */
	public function sort($a, $b) {
		if (!isset($a[$this->key]) || !isset($b[$this->key])) {
			if (!is_null($this->log)) {
				$this->log->error('Sharing dialogue: cannot sort due to ' .
								  'missing array key', ['app' => 'core']);
			}
			return 0;
		}
		$nameA = mb_strtolower($a[$this->key], $this->encoding);
		$nameB = mb_strtolower($b[$this->key], $this->encoding);
		$i = mb_strpos($nameA, $this->search, 0, $this->encoding);
		$j = mb_strpos($nameB, $this->search, 0, $this->encoding);

		if ($i === $j || $i > 0 && $j > 0) {
			return strcmp(mb_strtolower($nameA, $this->encoding),
						  mb_strtolower($nameB, $this->encoding));
		} elseif ($i === 0) {
			return -1;
		} else {
			return 1;
		}
	}
}
