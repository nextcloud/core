<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace Test\DB;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;

/**
 * Class SchemaWrapperTest
 *
 * @group DB
 *
 * @package Test\DB
 */
class SchemaWrapperTest extends \Test\TestCase {
	/** @var \Doctrine\DBAL\Connection $connection */
	private $connection;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->get(Connection::class);
	}

	public function testGetTableNames(): void {
		$schema = new SchemaWrapper($this->connection);
		self::assertContains('oc_share', $schema->getTableNames());
	}

	public function testGetTableNamesWithoutPrefix(): void {
		$schema = new SchemaWrapper($this->connection);
		self::assertContains('share', $schema->getTableNamesWithoutPrefix());
	}
}
