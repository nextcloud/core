<?php
/**
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace Test\DB;

use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

/**
 * Class OCPostgreSqlPlatformTest
 *
 * custom OCPostgreSqlPlatform behavior has been upstreamed, test is left to
 * ensure behavior stays correct.
 *
 * @group DB
 *
 * @package Test\DB
 */
class OCPostgreSqlPlatformTest extends \Test\TestCase {
	public function testAlterBigint() {
		$platform = new PostgreSQL100Platform();
		$sourceSchema = new Schema();
		$targetSchema = new Schema();

		$this->createTableAndColumn($sourceSchema, Types::INTEGER);
		$this->createTableAndColumn($targetSchema, Types::BIGINT);

		$comparator = new Comparator();
		$diff = $comparator->compare($sourceSchema, $targetSchema);
		$sqlStatements = $diff->toSql($platform);
		$this->assertContains(
			'ALTER TABLE poor_yorick ALTER id TYPE BIGINT',
			$sqlStatements,
			true
		);

		$this->assertNotContains(
			'ALTER TABLE poor_yorick ALTER id DROP DEFAULT',
			$sqlStatements,
			true
		);
	}

	protected function createTableAndColumn($schema, $type) {
		$table = $schema->createTable("poor_yorick");
		$table->addColumn('id', $type, [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
			'length' => 11,
		]);
	}
}
