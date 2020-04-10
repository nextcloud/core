<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\DB\QueryBuilder;

use OC\DB\QueryBuilder\Literal;
use Test\TestCase;

/**
 * @group DB
 */
class ExpressionBuilderDBTest extends TestCase {
	/** @var \Doctrine\DBAL\Connection|\OCP\IDBConnection */
	protected $connection;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
	}

	public function likeProvider() {
		$connection = \OC::$server->getDatabaseConnection();

		return [
			['foo', 'bar', false],
			['foo', 'foo', true],
			['foo', 'f%', true],
			['foo', '%o', true],
			['foo', '%', true],
			['foo', 'fo_', true],
			['foo', 'foo_', false],
			['foo', $connection->escapeLikeParameter('fo_'), false],
			['foo', $connection->escapeLikeParameter('f%'), false],
		];
	}

	/**
	 * @dataProvider likeProvider
	 *
	 * @param string $param1
	 * @param string $param2
	 * @param boolean $match
	 */
	public function testLike($param1, $param2, $match) {
		$query = $this->connection->getQueryBuilder();

		$query->select(new Literal('1'))
			->from('users')
			->where($query->expr()->like($query->createNamedParameter($param1), $query->createNamedParameter($param2)));

		$this->assertEquals($match, $query->execute()->fetchColumn());
	}

	public function ilikeProvider() {
		$connection = \OC::$server->getDatabaseConnection();

		return [
			['foo', 'bar', false],
			['foo', 'foo', true],
			['foo', 'Foo', true],
			['foo', 'f%', true],
			['foo', '%o', true],
			['foo', '%', true],
			['foo', 'fo_', true],
			['foo', 'foo_', false],
			['foo', $connection->escapeLikeParameter('fo_'), false],
			['foo', $connection->escapeLikeParameter('f%'), false],
		];
	}

	/**
	 * @dataProvider ilikeProvider
	 *
	 * @param string $param1
	 * @param string $param2
	 * @param boolean $match
	 */
	public function testILike($param1, $param2, $match) {
		$query = $this->connection->getQueryBuilder();

		$query->select(new Literal('1'))
			->from('users')
			->where($query->expr()->iLike($query->createNamedParameter($param1), $query->createNamedParameter($param2)));

		$this->assertEquals($match, $query->execute()->fetchColumn());
	}
}
