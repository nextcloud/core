<?php
/**
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\DB\QueryBuilder;

use OC\DB\QueryBuilder\Literal;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Test\TestCase;

/**
 * Class FunctionBuilderTest
 *
 * @group DB
 *
 * @package Test\DB\QueryBuilder
 */
class FunctionBuilderTest extends TestCase {
	/** @var \Doctrine\DBAL\Connection|\OCP\IDBConnection */
	protected $connection;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
	}

	public function testConcat() {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->concat($query->createNamedParameter('foo'), new Literal("'bar'")));
		$query->from('appconfig')
			->setMaxResults(1);

		$this->assertEquals('foobar', $query->execute()->fetchColumn());
	}

	public function testMd5() {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->md5($query->createNamedParameter('foobar')));
		$query->from('appconfig')
			->setMaxResults(1);

		$this->assertEquals(md5('foobar'), $query->execute()->fetchColumn());
	}

	public function testSubstring() {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->substring($query->createNamedParameter('foobar'), new Literal(2), $query->createNamedParameter(2)));
		$query->from('appconfig')
			->setMaxResults(1);

		$this->assertEquals('oo', $query->execute()->fetchColumn());
	}

	public function testSubstringNoLength() {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->substring($query->createNamedParameter('foobar'), new Literal(2)));
		$query->from('appconfig')
			->setMaxResults(1);

		$this->assertEquals('oobar', $query->execute()->fetchColumn());
	}

	public function testLower() {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->lower($query->createNamedParameter('FooBar')));
		$query->from('appconfig')
			->setMaxResults(1);

		$this->assertEquals('foobar', $query->execute()->fetchColumn());
	}

	public function testAdd() {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->add($query->createNamedParameter(2, IQueryBuilder::PARAM_INT), new Literal(1)));
		$query->from('appconfig')
			->setMaxResults(1);

		$this->assertEquals(3, $query->execute()->fetchColumn());
	}

	public function testSubtract() {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->subtract($query->createNamedParameter(2, IQueryBuilder::PARAM_INT), new Literal(1)));
		$query->from('appconfig')
			->setMaxResults(1);

		$this->assertEquals(1, $query->execute()->fetchColumn());
	}

	public function testCount() {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->count('appid'));
		$query->from('appconfig')
			->setMaxResults(1);

		$this->assertGreaterThan(1, $query->execute()->fetchColumn());
	}

	private function setUpMinMax($value) {
		$query = $this->connection->getQueryBuilder();

		$query->insert('appconfig')
			->values([
				'appid' => $query->createNamedParameter('minmax'),
				'configkey' => $query->createNamedParameter(uniqid()),
				'configvalue' => $query->createNamedParameter((string)$value),
			]);
		$query->execute();
	}

	private function clearMinMax() {
		$query = $this->connection->getQueryBuilder();

		$query->delete('appconfig')
			->where($query->expr()->eq('appid', $query->createNamedParameter('minmax')));
		$query->execute();
	}

	public function testMaxEmpty() {
		$this->clearMinMax();

		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->max($query->expr()->castColumn('configvalue', IQueryBuilder::PARAM_INT)));
		$query->from('appconfig')
			->where($query->expr()->eq('appid', $query->createNamedParameter('minmax')))
			->setMaxResults(1);

		$this->assertEquals(null, $query->execute()->fetchColumn());
	}

	public function testMinEmpty() {
		$this->clearMinMax();

		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->min($query->expr()->castColumn('configvalue', IQueryBuilder::PARAM_INT)));
		$query->from('appconfig')
			->where($query->expr()->eq('appid', $query->createNamedParameter('minmax')))
			->setMaxResults(1);

		$this->assertEquals(null, $query->execute()->fetchColumn());
	}

	public function testMax() {
		$this->clearMinMax();
		$this->setUpMinMax(10);
		$this->setUpMinMax(11);
		$this->setUpMinMax(20);

		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->max($query->expr()->castColumn('configvalue', IQueryBuilder::PARAM_INT)));
		$query->from('appconfig')
			->where($query->expr()->eq('appid', $query->createNamedParameter('minmax')))
			->setMaxResults(1);

		$this->assertEquals(20, $query->execute()->fetchColumn());
	}

	public function testMin() {
		$this->clearMinMax();
		$this->setUpMinMax(10);
		$this->setUpMinMax(11);
		$this->setUpMinMax(20);

		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->min($query->expr()->castColumn('configvalue', IQueryBuilder::PARAM_INT)));
		$query->from('appconfig')
			->where($query->expr()->eq('appid', $query->createNamedParameter('minmax')))
			->setMaxResults(1);

		$this->assertEquals(10, $query->execute()->fetchColumn());
	}

	public function testGreatest() {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->greatest($query->createNamedParameter(2, IQueryBuilder::PARAM_INT), new Literal(1)));
		$query->from('appconfig')
			->setMaxResults(1);

		$this->assertEquals(2, $query->execute()->fetchColumn());
	}

	public function testLeast() {
		$query = $this->connection->getQueryBuilder();

		$query->select($query->func()->least($query->createNamedParameter(2, IQueryBuilder::PARAM_INT), new Literal(1)));
		$query->from('appconfig')
			->setMaxResults(1);

		$this->assertEquals(1, $query->execute()->fetchColumn());
	}
}
