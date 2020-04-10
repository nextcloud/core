<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use OC\DB\QueryBuilder\Literal;
use OC\DB\QueryBuilder\Parameter;
use OC\DB\QueryBuilder\QueryBuilder;
use OC\SystemConfig;
use OCP\IDBConnection;
use OCP\ILogger;

/**
 * Class QueryBuilderTest
 *
 * @group DB
 *
 * @package Test\DB\QueryBuilder
 */
class QueryBuilderTest extends \Test\TestCase {
	/** @var QueryBuilder */
	protected $queryBuilder;

	/** @var IDBConnection */
	protected $connection;

	/** @var SystemConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;

	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	protected $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->config = $this->createMock(SystemConfig::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->queryBuilder = new QueryBuilder($this->connection, $this->config, $this->logger);
	}

	protected function createTestingRows($appId = 'testFirstResult') {
		$qB = $this->connection->getQueryBuilder();
		for ($i = 1; $i < 10; $i++) {
			$qB->insert('*PREFIX*appconfig')
				->values([
					'appid' => $qB->expr()->literal($appId),
					'configkey' => $qB->expr()->literal('testing' . $i),
					'configvalue' => $qB->expr()->literal(100 - $i),
				])
				->execute();
		}
	}

	protected function getTestingRows(QueryBuilder $queryBuilder) {
		$queryBuilder->select('configvalue')
			->from('*PREFIX*appconfig')
			->where($queryBuilder->expr()->eq(
				'appid',
				$queryBuilder->expr()->literal('testFirstResult')
			))
			->orderBy('configkey', 'ASC');

		$query = $queryBuilder->execute();
		$rows = [];
		while ($row = $query->fetch()) {
			$rows[] = $row['configvalue'];
		}
		$query->closeCursor();

		return $rows;
	}

	protected function deleteTestingRows($appId = 'testFirstResult') {
		$qB = $this->connection->getQueryBuilder();

		$qB->delete('*PREFIX*appconfig')
			->where($qB->expr()->eq('appid', $qB->expr()->literal($appId)))
			->execute();
	}

	public function dataFirstResult() {
		return [
			[null, [99, 98, 97, 96, 95, 94, 93, 92, 91]],
			[0, [99, 98, 97, 96, 95, 94, 93, 92, 91]],
			[1, [98, 97, 96, 95, 94, 93, 92, 91]],
			[5, [94, 93, 92, 91]],
		];
	}

	/**
	 * @dataProvider dataFirstResult
	 *
	 * @param int $firstResult
	 * @param array $expectedSet
	 */
	public function testFirstResult($firstResult, $expectedSet) {
		$this->deleteTestingRows();
		$this->createTestingRows();

		if ($firstResult !== null) {
			$this->queryBuilder->setFirstResult($firstResult);

			// FIXME Remove this once Doctrine/DBAL is >2.5.1:
			// FIXME See https://github.com/doctrine/dbal/pull/782
			$this->queryBuilder->setMaxResults(100);
		}

		$this->assertSame(
			$firstResult,
			$this->queryBuilder->getFirstResult()
		);

		$rows = $this->getTestingRows($this->queryBuilder);

		$this->assertCount(sizeof($expectedSet), $rows);
		$this->assertEquals($expectedSet, $rows);

		$this->deleteTestingRows();
	}

	public function dataMaxResults() {
		return [
			[null, [99, 98, 97, 96, 95, 94, 93, 92, 91]],
			// Limit 0 gives mixed results: either all entries or none is returned
			//[0, []],
			[1, [99]],
			[5, [99, 98, 97, 96, 95]],
		];
	}

	/**
	 * @dataProvider dataMaxResults
	 *
	 * @param int $maxResult
	 * @param array $expectedSet
	 */
	public function testMaxResults($maxResult, $expectedSet) {
		$this->deleteTestingRows();
		$this->createTestingRows();

		if ($maxResult !== null) {
			$this->queryBuilder->setMaxResults($maxResult);
		}

		$this->assertSame(
			$maxResult,
			$this->queryBuilder->getMaxResults()
		);

		$rows = $this->getTestingRows($this->queryBuilder);

		$this->assertCount(sizeof($expectedSet), $rows);
		$this->assertEquals($expectedSet, $rows);

		$this->deleteTestingRows();
	}

	public function dataSelect() {
		$config = $this->createMock(SystemConfig::class);
		$logger = $this->createMock(ILogger::class);
		$queryBuilder = new QueryBuilder(\OC::$server->getDatabaseConnection(), $config, $logger);
		return [
			// select('column1')
			[['configvalue'], ['configvalue' => '99']],

			// select('column1', 'column2')
			[['configvalue', 'configkey'], ['configvalue' => '99', 'configkey' => 'testing1']],

			// select(['column1', 'column2'])
			[[['configvalue', 'configkey']], ['configvalue' => '99', 'configkey' => 'testing1']],

			// select(new Literal('column1'))
			[[$queryBuilder->expr()->literal('column1')], [], 'column1'],

			// select('column1', 'column2')
			[[$queryBuilder->expr()->literal('column1'), 'configkey'], ['configkey' => 'testing1'], 'column1'],

			// select(['column1', 'column2'])
			[[[$queryBuilder->expr()->literal('column1'), 'configkey']], ['configkey' => 'testing1'], 'column1'],
		];
	}

	/**
	 * @dataProvider dataSelect
	 *
	 * @param array $selectArguments
	 * @param array $expected
	 * @param string $expectedLiteral
	 */
	public function testSelect($selectArguments, $expected, $expectedLiteral = '') {
		$this->deleteTestingRows();
		$this->createTestingRows();

		call_user_func_array(
			[$this->queryBuilder, 'select'],
			$selectArguments
		);

		$this->queryBuilder->from('*PREFIX*appconfig')
			->where($this->queryBuilder->expr()->eq(
				'appid',
				$this->queryBuilder->expr()->literal('testFirstResult')
			))
			->orderBy('configkey', 'ASC')
			->setMaxResults(1);

		$query = $this->queryBuilder->execute();
		$row = $query->fetch();
		$query->closeCursor();

		foreach ($expected as $key => $value) {
			$this->assertArrayHasKey($key, $row);
			$this->assertEquals($value, $row[$key]);
			unset($row[$key]);
		}

		if ($expectedLiteral) {
			$this->assertEquals([$expectedLiteral], array_values($row));
		} else {
			$this->assertEmpty($row);
		}

		$this->deleteTestingRows();
	}

	public function dataSelectAlias() {
		$config = $this->createMock(SystemConfig::class);
		$logger = $this->createMock(ILogger::class);
		$queryBuilder = new QueryBuilder(\OC::$server->getDatabaseConnection(), $config, $logger);
		return [
			['configvalue', 'cv', ['cv' => '99']],
			[$queryBuilder->expr()->literal('column1'), 'thing', ['thing' => 'column1']],
		];
	}

	/**
	 * @dataProvider dataSelectAlias
	 *
	 * @param mixed $select
	 * @param array $alias
	 * @param array $expected
	 */
	public function testSelectAlias($select, $alias, $expected) {
		$this->deleteTestingRows();
		$this->createTestingRows();

		$this->queryBuilder->selectAlias($select, $alias);

		$this->queryBuilder->from('*PREFIX*appconfig')
			->where($this->queryBuilder->expr()->eq(
				'appid',
				$this->queryBuilder->expr()->literal('testFirstResult')
			))
			->orderBy('configkey', 'ASC')
			->setMaxResults(1);

		$query = $this->queryBuilder->execute();
		$row = $query->fetch();
		$query->closeCursor();

		$this->assertEquals(
			$expected,
			$row
		);

		$this->deleteTestingRows();
	}

	public function testSelectDistinct() {
		$this->deleteTestingRows('testFirstResult1');
		$this->deleteTestingRows('testFirstResult2');
		$this->createTestingRows('testFirstResult1');
		$this->createTestingRows('testFirstResult2');

		$this->queryBuilder->selectDistinct('appid');

		$this->queryBuilder->from('*PREFIX*appconfig')
			->where($this->queryBuilder->expr()->in(
				'appid',
				[$this->queryBuilder->expr()->literal('testFirstResult1'), $this->queryBuilder->expr()->literal('testFirstResult2')]
			))
			->orderBy('appid', 'DESC');

		$query = $this->queryBuilder->execute();
		$rows = $query->fetchAll();
		$query->closeCursor();

		$this->assertEquals(
			[['appid' => 'testFirstResult2'], ['appid' => 'testFirstResult1']],
			$rows
		);

		$this->deleteTestingRows('testFirstResult1');
		$this->deleteTestingRows('testFirstResult2');
	}

	public function dataAddSelect() {
		$config = $this->createMock(SystemConfig::class);
		$logger = $this->createMock(ILogger::class);
		$queryBuilder = new QueryBuilder(\OC::$server->getDatabaseConnection(), $config, $logger);
		return [
			// addSelect('column1')
			[['configvalue'], ['appid' => 'testFirstResult', 'configvalue' => '99']],

			// addSelect('column1', 'column2')
			[['configvalue', 'configkey'], ['appid' => 'testFirstResult', 'configvalue' => '99', 'configkey' => 'testing1']],

			// addSelect(['column1', 'column2'])
			[[['configvalue', 'configkey']], ['appid' => 'testFirstResult', 'configvalue' => '99', 'configkey' => 'testing1']],

			// select(new Literal('column1'))
			[[$queryBuilder->expr()->literal('column1')], ['appid' => 'testFirstResult'], 'column1'],

			// select('column1', 'column2')
			[[$queryBuilder->expr()->literal('column1'), 'configkey'], ['appid' => 'testFirstResult', 'configkey' => 'testing1'], 'column1'],

			// select(['column1', 'column2'])
			[[[$queryBuilder->expr()->literal('column1'), 'configkey']], ['appid' => 'testFirstResult', 'configkey' => 'testing1'], 'column1'],
		];
	}

	/**
	 * @dataProvider dataAddSelect
	 *
	 * @param array $selectArguments
	 * @param array $expected
	 * @param string $expectedLiteral
	 */
	public function testAddSelect($selectArguments, $expected, $expectedLiteral = '') {
		$this->deleteTestingRows();
		$this->createTestingRows();

		$this->queryBuilder->select('appid');

		call_user_func_array(
			[$this->queryBuilder, 'addSelect'],
			$selectArguments
		);

		$this->queryBuilder->from('*PREFIX*appconfig')
			->where($this->queryBuilder->expr()->eq(
				'appid',
				$this->queryBuilder->expr()->literal('testFirstResult')
			))
			->orderBy('configkey', 'ASC')
			->setMaxResults(1);

		$query = $this->queryBuilder->execute();
		$row = $query->fetch();
		$query->closeCursor();

		foreach ($expected as $key => $value) {
			$this->assertArrayHasKey($key, $row);
			$this->assertEquals($value, $row[$key]);
			unset($row[$key]);
		}

		if ($expectedLiteral) {
			$this->assertEquals([$expectedLiteral], array_values($row));
		} else {
			$this->assertEmpty($row);
		}

		$this->deleteTestingRows();
	}

	public function dataDelete() {
		return [
			['data', null, ['table' => '`*PREFIX*data`', 'alias' => null], '`*PREFIX*data`'],
			['data', 't', ['table' => '`*PREFIX*data`', 'alias' => 't'], '`*PREFIX*data` t'],
		];
	}

	/**
	 * @dataProvider dataDelete
	 *
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testDelete($tableName, $tableAlias, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->delete($tableName, $tableAlias);

		$this->assertSame(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('from')
		);

		$this->assertSame(
			'DELETE FROM ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataUpdate() {
		return [
			['data', null, ['table' => '`*PREFIX*data`', 'alias' => null], '`*PREFIX*data`'],
			['data', 't', ['table' => '`*PREFIX*data`', 'alias' => 't'], '`*PREFIX*data` t'],
		];
	}

	/**
	 * @dataProvider dataUpdate
	 *
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testUpdate($tableName, $tableAlias, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->update($tableName, $tableAlias);

		$this->assertSame(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('from')
		);

		$this->assertSame(
			'UPDATE ' . $expectedQuery . ' SET ',
			$this->queryBuilder->getSQL()
		);
	}

	public function dataInsert() {
		return [
			['data', ['table' => '`*PREFIX*data`'], '`*PREFIX*data`'],
		];
	}

	/**
	 * @dataProvider dataInsert
	 *
	 * @param string $tableName
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testInsert($tableName, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->insert($tableName);

		$this->assertSame(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('from')
		);

		$this->assertSame(
			'INSERT INTO ' . $expectedQuery . ' () VALUES()',
			$this->queryBuilder->getSQL()
		);
	}

	public function dataFrom() {
		return [
			['data', null, null, null, [['table' => '`*PREFIX*data`', 'alias' => null]], '`*PREFIX*data`'],
			['data', 't', null, null, [['table' => '`*PREFIX*data`', 'alias' => '`t`']], '`*PREFIX*data` `t`'],
			['data1', null, 'data2', null, [
				['table' => '`*PREFIX*data1`', 'alias' => null],
				['table' => '`*PREFIX*data2`', 'alias' => null]
			], '`*PREFIX*data1`, `*PREFIX*data2`'],
			['data', 't1', 'data', 't2', [
				['table' => '`*PREFIX*data`', 'alias' => '`t1`'],
				['table' => '`*PREFIX*data`', 'alias' => '`t2`']
			], '`*PREFIX*data` `t1`, `*PREFIX*data` `t2`'],
		];
	}

	/**
	 * @dataProvider dataFrom
	 *
	 * @param string $table1Name
	 * @param string $table1Alias
	 * @param string $table2Name
	 * @param string $table2Alias
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testFrom($table1Name, $table1Alias, $table2Name, $table2Alias, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->from($table1Name, $table1Alias);
		if ($table2Name !== null) {
			$this->queryBuilder->from($table2Name, $table2Alias);
		}

		$this->assertSame(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('from')
		);

		$this->assertSame(
			'SELECT  FROM ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataJoin() {
		return [
			[
				'd1', 'data2', null, null,
				['`d1`' => [['joinType' => 'inner', 'joinTable' => '`*PREFIX*data2`', 'joinAlias' => null, 'joinCondition' => null]]],
				'`*PREFIX*data1` `d1` INNER JOIN `*PREFIX*data2`  ON '
			],
			[
				'd1', 'data2', 'd2', null,
				['`d1`' => [['joinType' => 'inner', 'joinTable' => '`*PREFIX*data2`', 'joinAlias' => '`d2`', 'joinCondition' => null]]],
				'`*PREFIX*data1` `d1` INNER JOIN `*PREFIX*data2` `d2` ON '
			],
			[
				'd1', 'data2', 'd2', '`d1`.`field1` = `d2`.`field2`',
				['`d1`' => [['joinType' => 'inner', 'joinTable' => '`*PREFIX*data2`', 'joinAlias' => '`d2`', 'joinCondition' => '`d1`.`field1` = `d2`.`field2`']]],
				'`*PREFIX*data1` `d1` INNER JOIN `*PREFIX*data2` `d2` ON `d1`.`field1` = `d2`.`field2`'
			],

		];
	}

	/**
	 * @dataProvider dataJoin
	 *
	 * @param string $fromAlias
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param string $condition
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testJoin($fromAlias, $tableName, $tableAlias, $condition, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->from('data1', 'd1');
		$this->queryBuilder->join(
			$fromAlias,
			$tableName,
			$tableAlias,
			$condition
		);

		$this->assertSame(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('join')
		);

		$this->assertSame(
			'SELECT  FROM ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	/**
	 * @dataProvider dataJoin
	 *
	 * @param string $fromAlias
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param string $condition
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testInnerJoin($fromAlias, $tableName, $tableAlias, $condition, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->from('data1', 'd1');
		$this->queryBuilder->innerJoin(
			$fromAlias,
			$tableName,
			$tableAlias,
			$condition
		);

		$this->assertSame(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('join')
		);

		$this->assertSame(
			'SELECT  FROM ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataLeftJoin() {
		return [
			[
				'd1', 'data2', null, null,
				['`d1`' => [['joinType' => 'left', 'joinTable' => '`*PREFIX*data2`', 'joinAlias' => null, 'joinCondition' => null]]],
				'`*PREFIX*data1` `d1` LEFT JOIN `*PREFIX*data2`  ON '
			],
			[
				'd1', 'data2', 'd2', null,
				['`d1`' => [['joinType' => 'left', 'joinTable' => '`*PREFIX*data2`', 'joinAlias' => '`d2`', 'joinCondition' => null]]],
				'`*PREFIX*data1` `d1` LEFT JOIN `*PREFIX*data2` `d2` ON '
			],
			[
				'd1', 'data2', 'd2', '`d1`.`field1` = `d2`.`field2`',
				['`d1`' => [['joinType' => 'left', 'joinTable' => '`*PREFIX*data2`', 'joinAlias' => '`d2`', 'joinCondition' => '`d1`.`field1` = `d2`.`field2`']]],
				'`*PREFIX*data1` `d1` LEFT JOIN `*PREFIX*data2` `d2` ON `d1`.`field1` = `d2`.`field2`'
			],
		];
	}

	/**
	 * @dataProvider dataLeftJoin
	 *
	 * @param string $fromAlias
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param string $condition
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testLeftJoin($fromAlias, $tableName, $tableAlias, $condition, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->from('data1', 'd1');
		$this->queryBuilder->leftJoin(
			$fromAlias,
			$tableName,
			$tableAlias,
			$condition
		);

		$this->assertSame(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('join')
		);

		$this->assertSame(
			'SELECT  FROM ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataRightJoin() {
		return [
			[
				'd1', 'data2', null, null,
				['`d1`' => [['joinType' => 'right', 'joinTable' => '`*PREFIX*data2`', 'joinAlias' => null, 'joinCondition' => null]]],
				'`*PREFIX*data1` `d1` RIGHT JOIN `*PREFIX*data2`  ON '
			],
			[
				'd1', 'data2', 'd2', null,
				['`d1`' => [['joinType' => 'right', 'joinTable' => '`*PREFIX*data2`', 'joinAlias' => '`d2`', 'joinCondition' => null]]],
				'`*PREFIX*data1` `d1` RIGHT JOIN `*PREFIX*data2` `d2` ON '
			],
			[
				'd1', 'data2', 'd2', '`d1`.`field1` = `d2`.`field2`',
				['`d1`' => [['joinType' => 'right', 'joinTable' => '`*PREFIX*data2`', 'joinAlias' => '`d2`', 'joinCondition' => '`d1`.`field1` = `d2`.`field2`']]],
				'`*PREFIX*data1` `d1` RIGHT JOIN `*PREFIX*data2` `d2` ON `d1`.`field1` = `d2`.`field2`'
			],
		];
	}

	/**
	 * @dataProvider dataRightJoin
	 *
	 * @param string $fromAlias
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param string $condition
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testRightJoin($fromAlias, $tableName, $tableAlias, $condition, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->from('data1', 'd1');
		$this->queryBuilder->rightJoin(
			$fromAlias,
			$tableName,
			$tableAlias,
			$condition
		);

		$this->assertSame(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('join')
		);

		$this->assertSame(
			'SELECT  FROM ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataSet() {
		return [
			['column1', new Literal('value'), null, null, ['`column1` = value'], '`column1` = value'],
			['column1', new Parameter(':param'), null, null, ['`column1` = :param'], '`column1` = :param'],
			['column1', 'column2', null, null, ['`column1` = `column2`'], '`column1` = `column2`'],
			['column1', 'column2', 'column3', new Literal('value'), ['`column1` = `column2`', '`column3` = value'], '`column1` = `column2`, `column3` = value'],
		];
	}

	/**
	 * @dataProvider dataSet
	 *
	 * @param string $partOne1
	 * @param string $partOne2
	 * @param string $partTwo1
	 * @param string $partTwo2
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testSet($partOne1, $partOne2, $partTwo1, $partTwo2, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->update('data');
		$this->queryBuilder->set($partOne1, $partOne2);
		if ($partTwo1 !== null) {
			$this->queryBuilder->set($partTwo1, $partTwo2);
		}

		$this->assertSame(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('set')
		);

		$this->assertSame(
			'UPDATE `*PREFIX*data` SET ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataWhere() {
		return [
			[['where1'], new CompositeExpression('AND', ['where1']), 'where1'],
			[['where1', 'where2'], new CompositeExpression('AND', ['where1', 'where2']), '(where1) AND (where2)'],
		];
	}

	/**
	 * @dataProvider dataWhere
	 *
	 * @param array $whereArguments
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testWhere($whereArguments, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->select('column');
		call_user_func_array(
			[$this->queryBuilder, 'where'],
			$whereArguments
		);

		$this->assertEquals(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('where')
		);

		$this->assertSame(
			'SELECT `column` WHERE ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	/**
	 * @dataProvider dataWhere
	 *
	 * @param array $whereArguments
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testAndWhere($whereArguments, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->select('column');
		call_user_func_array(
			[$this->queryBuilder, 'andWhere'],
			$whereArguments
		);

		$this->assertEquals(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('where')
		);

		$this->assertSame(
			'SELECT `column` WHERE ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataOrWhere() {
		return [
			[['where1'], new CompositeExpression('OR', ['where1']), 'where1'],
			[['where1', 'where2'], new CompositeExpression('OR', ['where1', 'where2']), '(where1) OR (where2)'],
		];
	}

	/**
	 * @dataProvider dataOrWhere
	 *
	 * @param array $whereArguments
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testOrWhere($whereArguments, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->select('column');
		call_user_func_array(
			[$this->queryBuilder, 'orWhere'],
			$whereArguments
		);

		$this->assertEquals(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('where')
		);

		$this->assertSame(
			'SELECT `column` WHERE ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataGroupBy() {
		return [
			[['column1'], ['`column1`'], '`column1`'],
			[['column1', 'column2'], ['`column1`', '`column2`'], '`column1`, `column2`'],
		];
	}

	/**
	 * @dataProvider dataGroupBy
	 *
	 * @param array $groupByArguments
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testGroupBy($groupByArguments, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->select('column');
		call_user_func_array(
			[$this->queryBuilder, 'groupBy'],
			$groupByArguments
		);

		$this->assertEquals(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('groupBy')
		);

		$this->assertSame(
			'SELECT `column` GROUP BY ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataAddGroupBy() {
		return [
			[['column2'], ['`column1`', '`column2`'], '`column1`, `column2`'],
			[['column2', 'column3'], ['`column1`', '`column2`', '`column3`'], '`column1`, `column2`, `column3`'],
		];
	}

	/**
	 * @dataProvider dataAddGroupBy
	 *
	 * @param array $groupByArguments
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testAddGroupBy($groupByArguments, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->select('column');
		$this->queryBuilder->groupBy('column1');
		call_user_func_array(
			[$this->queryBuilder, 'addGroupBy'],
			$groupByArguments
		);

		$this->assertEquals(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('groupBy')
		);

		$this->assertSame(
			'SELECT `column` GROUP BY ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataSetValue() {
		return [
			['column', 'value', ['`column`' => 'value'], '(`column`) VALUES(value)'],
		];
	}

	/**
	 * @dataProvider dataSetValue
	 *
	 * @param string $column
	 * @param string $value
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testSetValue($column, $value, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->insert('data');
		$this->queryBuilder->setValue($column, $value);

		$this->assertEquals(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('values')
		);

		$this->assertSame(
			'INSERT INTO `*PREFIX*data` ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	/**
	 * @dataProvider dataSetValue
	 *
	 * @param string $column
	 * @param string $value
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testValues($column, $value, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->insert('data');
		$this->queryBuilder->values([
			$column => $value,
		]);

		$this->assertEquals(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('values')
		);

		$this->assertSame(
			'INSERT INTO `*PREFIX*data` ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataHaving() {
		return [
			[['condition1'], new CompositeExpression('AND', ['condition1']), 'HAVING condition1'],
			[['condition1', 'condition2'], new CompositeExpression('AND', ['condition1', 'condition2']), 'HAVING (condition1) AND (condition2)'],
			[
				[new CompositeExpression('OR', ['condition1', 'condition2'])],
				new CompositeExpression('OR', ['condition1', 'condition2']),
				'HAVING (condition1) OR (condition2)'
			],
			[
				[new CompositeExpression('AND', ['condition1', 'condition2'])],
				new CompositeExpression('AND', ['condition1', 'condition2']),
				'HAVING (condition1) AND (condition2)'
			],
		];
	}

	/**
	 * @dataProvider dataHaving
	 *
	 * @param array $havingArguments
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testHaving($havingArguments, $expectedQueryPart, $expectedQuery) {
		call_user_func_array(
			[$this->queryBuilder, 'having'],
			$havingArguments
		);

		$this->assertEquals(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('having')
		);

		$this->assertSame(
			'SELECT  ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataAndHaving() {
		return [
			[['condition2'], new CompositeExpression('AND', ['condition1', 'condition2']), 'HAVING (condition1) AND (condition2)'],
			[['condition2', 'condition3'], new CompositeExpression('AND', ['condition1', 'condition2', 'condition3']), 'HAVING (condition1) AND (condition2) AND (condition3)'],
			[
				[new CompositeExpression('OR', ['condition2', 'condition3'])],
				new CompositeExpression('AND', ['condition1', new CompositeExpression('OR', ['condition2', 'condition3'])]),
				'HAVING (condition1) AND ((condition2) OR (condition3))'
			],
			[
				[new CompositeExpression('AND', ['condition2', 'condition3'])],
				new CompositeExpression('AND', ['condition1', new CompositeExpression('AND', ['condition2', 'condition3'])]),
				'HAVING (condition1) AND ((condition2) AND (condition3))'
			],
		];
	}

	/**
	 * @dataProvider dataAndHaving
	 *
	 * @param array $havingArguments
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testAndHaving($havingArguments, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->having('condition1');
		call_user_func_array(
			[$this->queryBuilder, 'andHaving'],
			$havingArguments
		);

		$this->assertEquals(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('having')
		);

		$this->assertSame(
			'SELECT  ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataOrHaving() {
		return [
			[['condition2'], new CompositeExpression('OR', ['condition1', 'condition2']), 'HAVING (condition1) OR (condition2)'],
			[['condition2', 'condition3'], new CompositeExpression('OR', ['condition1', 'condition2', 'condition3']), 'HAVING (condition1) OR (condition2) OR (condition3)'],
			[
				[new CompositeExpression('OR', ['condition2', 'condition3'])],
				new CompositeExpression('OR', ['condition1', new CompositeExpression('OR', ['condition2', 'condition3'])]),
				'HAVING (condition1) OR ((condition2) OR (condition3))'
			],
			[
				[new CompositeExpression('AND', ['condition2', 'condition3'])],
				new CompositeExpression('OR', ['condition1', new CompositeExpression('AND', ['condition2', 'condition3'])]),
				'HAVING (condition1) OR ((condition2) AND (condition3))'
			],
		];
	}

	/**
	 * @dataProvider dataOrHaving
	 *
	 * @param array $havingArguments
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testOrHaving($havingArguments, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->having('condition1');
		call_user_func_array(
			[$this->queryBuilder, 'orHaving'],
			$havingArguments
		);

		$this->assertEquals(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('having')
		);

		$this->assertSame(
			'SELECT  ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataOrderBy() {
		return [
			['column', null, ['`column` ASC'], 'ORDER BY `column` ASC'],
			['column', 'ASC', ['`column` ASC'], 'ORDER BY `column` ASC'],
			['column', 'DESC', ['`column` DESC'], 'ORDER BY `column` DESC'],
		];
	}

	/**
	 * @dataProvider dataOrderBy
	 *
	 * @param string $sort
	 * @param string $order
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testOrderBy($sort, $order, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->orderBy($sort, $order);

		$this->assertEquals(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('orderBy')
		);

		$this->assertSame(
			'SELECT  ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function dataAddOrderBy() {
		return [
			['column2', null, null, ['`column1` ASC', '`column2` ASC'], 'ORDER BY `column1` ASC, `column2` ASC'],
			['column2', null, 'ASC', ['`column1` ASC', '`column2` ASC'], 'ORDER BY `column1` ASC, `column2` ASC'],
			['column2', null, 'DESC', ['`column1` DESC', '`column2` ASC'], 'ORDER BY `column1` DESC, `column2` ASC'],
			['column2', 'ASC', null, ['`column1` ASC', '`column2` ASC'], 'ORDER BY `column1` ASC, `column2` ASC'],
			['column2', 'ASC', 'ASC', ['`column1` ASC', '`column2` ASC'], 'ORDER BY `column1` ASC, `column2` ASC'],
			['column2', 'ASC', 'DESC', ['`column1` DESC', '`column2` ASC'], 'ORDER BY `column1` DESC, `column2` ASC'],
			['column2', 'DESC', null, ['`column1` ASC', '`column2` DESC'], 'ORDER BY `column1` ASC, `column2` DESC'],
			['column2', 'DESC', 'ASC', ['`column1` ASC', '`column2` DESC'], 'ORDER BY `column1` ASC, `column2` DESC'],
			['column2', 'DESC', 'DESC', ['`column1` DESC', '`column2` DESC'], 'ORDER BY `column1` DESC, `column2` DESC'],
		];
	}

	/**
	 * @dataProvider dataAddOrderBy
	 *
	 * @param string $sort2
	 * @param string $order2
	 * @param string $order1
	 * @param array $expectedQueryPart
	 * @param string $expectedQuery
	 */
	public function testAddOrderBy($sort2, $order2, $order1, $expectedQueryPart, $expectedQuery) {
		$this->queryBuilder->orderBy('column1', $order1);
		$this->queryBuilder->addOrderBy($sort2, $order2);

		$this->assertEquals(
			$expectedQueryPart,
			$this->queryBuilder->getQueryPart('orderBy')
		);

		$this->assertSame(
			'SELECT  ' . $expectedQuery,
			$this->queryBuilder->getSQL()
		);
	}

	public function testGetLastInsertId() {
		$qB = $this->connection->getQueryBuilder();

		try {
			$qB->getLastInsertId();
			$this->fail('getLastInsertId() should throw an exception, when being called before insert()');
		} catch (\BadMethodCallException $e) {
			$this->addToAssertionCount(1);
		}

		$qB->insert('properties')
			->values([
				'userid' => $qB->expr()->literal('testFirstResult'),
				'propertypath' => $qB->expr()->literal('testing'),
				'propertyname' => $qB->expr()->literal('testing'),
				'propertyvalue' => $qB->expr()->literal('testing'),
			])
			->execute();

		$actual = $qB->getLastInsertId();

		$this->assertNotNull($actual);
		$this->assertIsInt($actual);
		$this->assertEquals($this->connection->lastInsertId('*PREFIX*properties'), $actual);

		$qB->delete('properties')
			->where($qB->expr()->eq('userid', $qB->expr()->literal('testFirstResult')))
			->execute();

		try {
			$qB->getLastInsertId();
			$this->fail('getLastInsertId() should throw an exception, when being called after delete()');
		} catch (\BadMethodCallException $e) {
			$this->addToAssertionCount(1);
		}
	}

	public function dataGetTableName() {
		return [
			['*PREFIX*table', null, '`*PREFIX*table`'],
			['*PREFIX*table', true, '`*PREFIX*table`'],
			['*PREFIX*table', false, '`*PREFIX*table`'],

			['table', null, '`*PREFIX*table`'],
			['table', true, '`*PREFIX*table`'],
			['table', false, '`table`'],
		];
	}

	/**
	 * @dataProvider dataGetTableName
	 *
	 * @param string $tableName
	 * @param bool $automatic
	 * @param string $expected
	 */
	public function testGetTableName($tableName, $automatic, $expected) {
		if ($automatic !== null) {
			$this->queryBuilder->automaticTablePrefix($automatic);
		}

		$this->assertSame(
			$expected,
			$this->queryBuilder->getTableName($tableName)
		);
	}

	public function dataGetColumnName() {
		return [
			['column', '', '`column`'],
			['column', 'a', '`a`.`column`'],
		];
	}

	/**
	 * @dataProvider dataGetColumnName
	 * @param string $column
	 * @param string $prefix
	 * @param string $expected
	 */
	public function testGetColumnName($column, $prefix, $expected) {
		$this->assertSame(
			$expected,
			$this->queryBuilder->getColumnName($column, $prefix)
		);
	}

	public function testExecuteWithoutLogger() {
		$queryBuilder = $this->createMock(\Doctrine\DBAL\Query\QueryBuilder::class);
		$queryBuilder
			->expects($this->once())
			->method('execute')
			->willReturn(3);
		$this->logger
			->expects($this->never())
			->method('debug');
		$this->config
			->expects($this->once())
			->method('getValue')
			->with('log_query', false)
			->willReturn(false);

		$this->invokePrivate($this->queryBuilder, 'queryBuilder', [$queryBuilder]);
		$this->assertEquals(3, $this->queryBuilder->execute());
	}

	public function testExecuteWithLoggerAndNamedArray() {
		$queryBuilder = $this->createMock(\Doctrine\DBAL\Query\QueryBuilder::class);
		$queryBuilder
			->expects($this->at(0))
			->method('getParameters')
			->willReturn([
				'foo' => 'bar',
				'key' => 'value',
			]);
		$queryBuilder
			->expects($this->at(1))
			->method('getSQL')
			->willReturn('SELECT * FROM FOO WHERE BAR = ?');
		$queryBuilder
			->expects($this->once())
			->method('execute')
			->willReturn(3);
		$this->logger
			->expects($this->once())
			->method('debug')
			->with(
				'DB QueryBuilder: \'{query}\' with parameters: {params}',
				[
					'query' => 'SELECT * FROM FOO WHERE BAR = ?',
					'params' => 'foo => \'bar\', key => \'value\'',
					'app' => 'core',
				]
			);
		$this->config
			->expects($this->once())
			->method('getValue')
			->with('log_query', false)
			->willReturn(true);

		$this->invokePrivate($this->queryBuilder, 'queryBuilder', [$queryBuilder]);
		$this->assertEquals(3, $this->queryBuilder->execute());
	}

	public function testExecuteWithLoggerAndUnnamedArray() {
		$queryBuilder = $this->createMock(\Doctrine\DBAL\Query\QueryBuilder::class);
		$queryBuilder
			->expects($this->at(0))
			->method('getParameters')
			->willReturn(['Bar']);
		$queryBuilder
			->expects($this->at(1))
			->method('getSQL')
			->willReturn('SELECT * FROM FOO WHERE BAR = ?');
		$queryBuilder
			->expects($this->once())
			->method('execute')
			->willReturn(3);
		$this->logger
			->expects($this->once())
			->method('debug')
			->with(
				'DB QueryBuilder: \'{query}\' with parameters: {params}',
				[
					'query' => 'SELECT * FROM FOO WHERE BAR = ?',
					'params' => '0 => \'Bar\'',
					'app' => 'core',
				]
			);
		$this->config
			->expects($this->once())
			->method('getValue')
			->with('log_query', false)
			->willReturn(true);

		$this->invokePrivate($this->queryBuilder, 'queryBuilder', [$queryBuilder]);
		$this->assertEquals(3, $this->queryBuilder->execute());
	}

	public function testExecuteWithLoggerAndNoParams() {
		$queryBuilder = $this->createMock(\Doctrine\DBAL\Query\QueryBuilder::class);
		$queryBuilder
			->expects($this->at(0))
			->method('getParameters')
			->willReturn([]);
		$queryBuilder
			->expects($this->at(1))
			->method('getSQL')
			->willReturn('SELECT * FROM FOO WHERE BAR = ?');
		$queryBuilder
			->expects($this->once())
			->method('execute')
			->willReturn(3);
		$this->logger
			->expects($this->once())
			->method('debug')
			->with(
				'DB QueryBuilder: \'{query}\'',
				[
					'query' => 'SELECT * FROM FOO WHERE BAR = ?',
					'app' => 'core',
				]
			);
		$this->config
			->expects($this->once())
			->method('getValue')
			->with('log_query', false)
			->willReturn(true);

		$this->invokePrivate($this->queryBuilder, 'queryBuilder', [$queryBuilder]);
		$this->assertEquals(3, $this->queryBuilder->execute());
	}
}
