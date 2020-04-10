<?php
/**
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\Diagnostics;

use OC\Diagnostics\QueryLogger;
use Test\TestCase;

class QueryLoggerTest extends TestCase {

	/** @var \OC\Diagnostics\QueryLogger */
	private $logger;
	
	protected function setUp(): void {
		parent::setUp();

		$this->logger = new QueryLogger();
	}

	public function testQueryLogger() {
		// Module is not activated and this should not be logged
		$this->logger->startQuery("SELECT", ["testuser", "count"], ["string", "int"]);
		$this->logger->stopQuery();
		$queries = $this->logger->getQueries();
		$this->assertSame(0, sizeof($queries));

		// Activate module and log some query
		$this->logger->activate();
		$this->logger->startQuery("SELECT", ["testuser", "count"], ["string", "int"]);
		$this->logger->stopQuery();

		$queries = $this->logger->getQueries();
		$this->assertSame(1, sizeof($queries));

	}
}
