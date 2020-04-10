<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Piotr Mrówczyński <mrow4a@yahoo.com>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Diagnostics;

use OC\Cache\CappedMemoryCache;
use OCP\Diagnostics\IQueryLogger;

class QueryLogger implements IQueryLogger {
	/**
	 * @var \OC\Diagnostics\Query
	 */
	protected $activeQuery;

	/**
	 * @var CappedMemoryCache
	 */
	protected $queries;

	/**
	 * QueryLogger constructor.
	 */
	public function __construct() {
		$this->queries = new CappedMemoryCache(1024);
	}


	/**
	 * @var bool - Module needs to be activated by some app
	 */
	private $activated = false;

	/**
	 * @inheritdoc
	 */
	public function startQuery($sql, array $params = null, array $types = null) {
		if ($this->activated) {
			$this->activeQuery = new Query($sql, $params, microtime(true), $this->getStack());
		}
	}

	private function getStack() {
		$stack = debug_backtrace();
		array_shift($stack);
		array_shift($stack);
		array_shift($stack);
		return $stack;
	}

	/**
	 * @inheritdoc
	 */
	public function stopQuery() {
		if ($this->activated && $this->activeQuery) {
			$this->activeQuery->end(microtime(true));
			$this->queries[] = $this->activeQuery;
			$this->activeQuery = null;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getQueries() {
		return $this->queries->getData();
	}

	/**
	 * @inheritdoc
	 */
	public function activate() {
		$this->activated = true;
	}
}
