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

use Doctrine\DBAL\Query\Expression\ExpressionBuilder as DoctrineExpressionBuilder;
use OC\DB\QueryBuilder\ExpressionBuilder\ExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Test\TestCase;

/**
 * Class ExpressionBuilderTest
 *
 * @group DB
 *
 * @package Test\DB\QueryBuilder
 */
class ExpressionBuilderTest extends TestCase {
	/** @var ExpressionBuilder */
	protected $expressionBuilder;

	/** @var DoctrineExpressionBuilder */
	protected $doctrineExpressionBuilder;

	/** @var \Doctrine\DBAL\Connection|\OCP\IDBConnection */
	protected $connection;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();

		$queryBuilder = $this->createMock(IQueryBuilder::class);

		$this->expressionBuilder = new ExpressionBuilder($this->connection, $queryBuilder);

		$this->doctrineExpressionBuilder = new DoctrineExpressionBuilder($this->connection);
	}

	public function dataComparison() {
		$valueSets = $this->dataComparisons();
		$comparisonOperators = ['=', '<>', '<', '>', '<=', '>='];

		$testSets = [];
		foreach ($comparisonOperators as $operator) {
			foreach ($valueSets as $values) {
				$testSets[] = array_merge([$operator], $values);
			}
		}
		return $testSets;
	}

	/**
	 * @dataProvider dataComparison
	 *
	 * @param string $comparison
	 * @param mixed $input1
	 * @param bool $isInput1Literal
	 * @param mixed $input2
	 * @param bool $isInput2Literal
	 */
	public function testComparison($comparison, $input1, $isInput1Literal, $input2, $isInput2Literal) {
		list($doctrineInput1, $ocInput1) = $this->helpWithLiteral($input1, $isInput1Literal);
		list($doctrineInput2, $ocInput2) = $this->helpWithLiteral($input2, $isInput2Literal);

		$this->assertEquals(
			$this->doctrineExpressionBuilder->comparison($doctrineInput1, $comparison, $doctrineInput2),
			$this->expressionBuilder->comparison($ocInput1, $comparison, $ocInput2)
		);
	}

	public function dataComparisons() {
		return [
			['value', false, 'value', false],
			['value', false, 'value', true],
			['value', true, 'value', false],
			['value', true, 'value', true],
		];
	}

	/**
	 * @dataProvider dataComparisons
	 *
	 * @param mixed $input1
	 * @param bool $isInput1Literal
	 * @param mixed $input2
	 * @param bool $isInput2Literal
	 */
	public function testEquals($input1, $isInput1Literal, $input2, $isInput2Literal) {
		list($doctrineInput1, $ocInput1) = $this->helpWithLiteral($input1, $isInput1Literal);
		list($doctrineInput2, $ocInput2) = $this->helpWithLiteral($input2, $isInput2Literal);

		$this->assertEquals(
			$this->doctrineExpressionBuilder->eq($doctrineInput1, $doctrineInput2),
			$this->expressionBuilder->eq($ocInput1, $ocInput2)
		);
	}

	/**
	 * @dataProvider dataComparisons
	 *
	 * @param mixed $input1
	 * @param bool $isInput1Literal
	 * @param mixed $input2
	 * @param bool $isInput2Literal
	 */
	public function testNotEquals($input1, $isInput1Literal, $input2, $isInput2Literal) {
		list($doctrineInput1, $ocInput1) = $this->helpWithLiteral($input1, $isInput1Literal);
		list($doctrineInput2, $ocInput2) = $this->helpWithLiteral($input2, $isInput2Literal);

		$this->assertEquals(
			$this->doctrineExpressionBuilder->neq($doctrineInput1, $doctrineInput2),
			$this->expressionBuilder->neq($ocInput1, $ocInput2)
		);
	}

	/**
	 * @dataProvider dataComparisons
	 *
	 * @param mixed $input1
	 * @param bool $isInput1Literal
	 * @param mixed $input2
	 * @param bool $isInput2Literal
	 */
	public function testLowerThan($input1, $isInput1Literal, $input2, $isInput2Literal) {
		list($doctrineInput1, $ocInput1) = $this->helpWithLiteral($input1, $isInput1Literal);
		list($doctrineInput2, $ocInput2) = $this->helpWithLiteral($input2, $isInput2Literal);

		$this->assertEquals(
			$this->doctrineExpressionBuilder->lt($doctrineInput1, $doctrineInput2),
			$this->expressionBuilder->lt($ocInput1, $ocInput2)
		);
	}

	/**
	 * @dataProvider dataComparisons
	 *
	 * @param mixed $input1
	 * @param bool $isInput1Literal
	 * @param mixed $input2
	 * @param bool $isInput2Literal
	 */
	public function testLowerThanEquals($input1, $isInput1Literal, $input2, $isInput2Literal) {
		list($doctrineInput1, $ocInput1) = $this->helpWithLiteral($input1, $isInput1Literal);
		list($doctrineInput2, $ocInput2) = $this->helpWithLiteral($input2, $isInput2Literal);

		$this->assertEquals(
			$this->doctrineExpressionBuilder->lte($doctrineInput1, $doctrineInput2),
			$this->expressionBuilder->lte($ocInput1, $ocInput2)
		);
	}

	/**
	 * @dataProvider dataComparisons
	 *
	 * @param mixed $input1
	 * @param bool $isInput1Literal
	 * @param mixed $input2
	 * @param bool $isInput2Literal
	 */
	public function testGreaterThan($input1, $isInput1Literal, $input2, $isInput2Literal) {
		list($doctrineInput1, $ocInput1) = $this->helpWithLiteral($input1, $isInput1Literal);
		list($doctrineInput2, $ocInput2) = $this->helpWithLiteral($input2, $isInput2Literal);

		$this->assertEquals(
			$this->doctrineExpressionBuilder->gt($doctrineInput1, $doctrineInput2),
			$this->expressionBuilder->gt($ocInput1, $ocInput2)
		);
	}

	/**
	 * @dataProvider dataComparisons
	 *
	 * @param mixed $input1
	 * @param bool $isInput1Literal
	 * @param mixed $input2
	 * @param bool $isInput2Literal
	 */
	public function testGreaterThanEquals($input1, $isInput1Literal, $input2, $isInput2Literal) {
		list($doctrineInput1, $ocInput1) = $this->helpWithLiteral($input1, $isInput1Literal);
		list($doctrineInput2, $ocInput2) = $this->helpWithLiteral($input2, $isInput2Literal);

		$this->assertEquals(
			$this->doctrineExpressionBuilder->gte($doctrineInput1, $doctrineInput2),
			$this->expressionBuilder->gte($ocInput1, $ocInput2)
		);
	}

	public function testIsNull() {
		$this->assertEquals(
			$this->doctrineExpressionBuilder->isNull('`test`'),
			$this->expressionBuilder->isNull('test')
		);
	}

	public function testIsNotNull() {
		$this->assertEquals(
			$this->doctrineExpressionBuilder->isNotNull('`test`'),
			$this->expressionBuilder->isNotNull('test')
		);
	}

	public function dataLike() {
		return [
			['value', false],
			['value', true],
		];
	}

	/**
	 * @dataProvider dataLike
	 *
	 * @param mixed $input
	 * @param bool $isLiteral
	 */
	public function testLike($input, $isLiteral) {
		list($doctrineInput, $ocInput) = $this->helpWithLiteral($input, $isLiteral);

		$this->assertEquals(
			$this->doctrineExpressionBuilder->like('`test`', $doctrineInput),
			$this->expressionBuilder->like('test', $ocInput)
		);
	}

	/**
	 * @dataProvider dataLike
	 *
	 * @param mixed $input
	 * @param bool $isLiteral
	 */
	public function testNotLike($input, $isLiteral) {
		list($doctrineInput, $ocInput) = $this->helpWithLiteral($input, $isLiteral);

		$this->assertEquals(
			$this->doctrineExpressionBuilder->notLike('`test`', $doctrineInput),
			$this->expressionBuilder->notLike('test', $ocInput)
		);
	}

	public function dataIn() {
		return [
			['value', false],
			['value', true],
			[['value'], false],
			[['value'], true],
		];
	}

	/**
	 * @dataProvider dataIn
	 *
	 * @param mixed $input
	 * @param bool $isLiteral
	 */
	public function testIn($input, $isLiteral) {
		list($doctrineInput, $ocInput) = $this->helpWithLiteral($input, $isLiteral);

		$this->assertEquals(
			$this->doctrineExpressionBuilder->in('`test`', $doctrineInput),
			$this->expressionBuilder->in('test', $ocInput)
		);
	}

	/**
	 * @dataProvider dataIn
	 *
	 * @param mixed $input
	 * @param bool $isLiteral
	 */
	public function testNotIn($input, $isLiteral) {
		list($doctrineInput, $ocInput) = $this->helpWithLiteral($input, $isLiteral);

		$this->assertEquals(
			$this->doctrineExpressionBuilder->notIn('`test`', $doctrineInput),
			$this->expressionBuilder->notIn('test', $ocInput)
		);
	}

	protected function helpWithLiteral($input, $isLiteral) {
		if ($isLiteral) {
			if (is_array($input)) {
				$doctrineInput = array_map(function ($ident) {
					return $this->doctrineExpressionBuilder->literal($ident);
				}, $input);
				$ocInput = array_map(function ($ident) {
					return $this->expressionBuilder->literal($ident);
				}, $input);
			} else {
				$doctrineInput = $this->doctrineExpressionBuilder->literal($input);
				$ocInput = $this->expressionBuilder->literal($input);
			}
		} else {
			if (is_array($input)) {
				$doctrineInput = array_map(function ($input) {
					return '`' . $input . '`';
				}, $input);
				$ocInput = $input;
			} else {
				$doctrineInput = '`' . $input . '`';
				$ocInput = $input;
			}
		}

		return [$doctrineInput, $ocInput];
	}

	public function dataLiteral() {
		return [
			['value', null],
			['1', null],
			[1, null],
			[1, 'string'],
			[1, 'integer'],
			[1, IQueryBuilder::PARAM_INT],
		];
	}

	/**
	 * @dataProvider dataLiteral
	 *
	 * @param mixed $input
	 * @param string|null $type
	 */
	public function testLiteral($input, $type) {
		/** @var \OC\DB\QueryBuilder\Literal $actual */
		$actual = $this->expressionBuilder->literal($input, $type);

		$this->assertInstanceOf('\OC\DB\QueryBuilder\Literal', $actual);
		$this->assertEquals(
			$this->doctrineExpressionBuilder->literal($input, $type),
			$actual->__toString()
		);
	}

	public function dataClobComparisons() {
		return [
			['eq', '5', IQueryBuilder::PARAM_STR, false, 3],
			['eq', '5', IQueryBuilder::PARAM_STR, true, 1],
			['neq', '5', IQueryBuilder::PARAM_STR, false, 8],
			['neq', '5', IQueryBuilder::PARAM_STR, true, 6],
			['lt', '5', IQueryBuilder::PARAM_STR, false, 3],
			['lt', '5', IQueryBuilder::PARAM_STR, true, 1],
			['lte', '5', IQueryBuilder::PARAM_STR, false, 6],
			['lte', '5', IQueryBuilder::PARAM_STR, true, 4],
			['gt', '5', IQueryBuilder::PARAM_STR, false, 5],
			['gt', '5', IQueryBuilder::PARAM_STR, true, 1],
			['gte', '5', IQueryBuilder::PARAM_STR, false, 8],
			['gte', '5', IQueryBuilder::PARAM_STR, true, 4],
			['like', '%5%', IQueryBuilder::PARAM_STR, false, 3],
			['like', '%5%', IQueryBuilder::PARAM_STR, true, 1],
			['like', 'under_%', IQueryBuilder::PARAM_STR, false, 2],
			['like', 'under\_%', IQueryBuilder::PARAM_STR, false, 1],
			['notLike', '%5%', IQueryBuilder::PARAM_STR, false, 8],
			['notLike', '%5%', IQueryBuilder::PARAM_STR, true, 6],
			['in', ['5'], IQueryBuilder::PARAM_STR_ARRAY, false, 3],
			['in', ['5'], IQueryBuilder::PARAM_STR_ARRAY, true, 1],
			['notIn', ['5'], IQueryBuilder::PARAM_STR_ARRAY, false, 8],
			['notIn', ['5'], IQueryBuilder::PARAM_STR_ARRAY, true, 6],
		];
	}

	/**
	 * @dataProvider dataClobComparisons
	 * @param string $function
	 * @param mixed $value
	 * @param mixed $type
	 * @param bool $compareKeyToValue
	 * @param int $expected
	 */
	public function testClobComparisons($function, $value, $type, $compareKeyToValue, $expected) {
		$appId = $this->getUniqueID('testing');
		$this->createConfig($appId, 1, 4);
		$this->createConfig($appId, 2, 5);
		$this->createConfig($appId, 3, 6);
		$this->createConfig($appId, 4, 4);
		$this->createConfig($appId, 5, 5);
		$this->createConfig($appId, 6, 6);
		$this->createConfig($appId, 7, 4);
		$this->createConfig($appId, 8, 5);
		$this->createConfig($appId, 9, 6);
		$this->createConfig($appId, 10, 'under_score');
		$this->createConfig($appId, 11, 'underscore');

		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->count('*', 'count'))
			->from('appconfig')
			->where($query->expr()->eq('appid', $query->createNamedParameter($appId)))
			->andWhere(call_user_func([$query->expr(), $function], 'configvalue', $query->createNamedParameter($value, $type), IQueryBuilder::PARAM_STR));

		if ($compareKeyToValue) {
			$query->andWhere(call_user_func([$query->expr(), $function], 'configkey', 'configvalue', IQueryBuilder::PARAM_STR));
		}

		$result = $query->execute();

		$this->assertEquals(['count' => $expected], $result->fetch());
		$result->closeCursor();

		$query = $this->connection->getQueryBuilder();
		$query->delete('appconfig')
			->where($query->expr()->eq('appid', $query->createNamedParameter($appId)))
			->execute();
	}

	protected function createConfig($appId, $key, $value) {
		$query = $this->connection->getQueryBuilder();
		$query->insert('appconfig')
			->values([
				'appid' => $query->createNamedParameter($appId),
				'configkey' => $query->createNamedParameter((string) $key),
				'configvalue' => $query->createNamedParameter((string) $value),
			])
			->execute();
	}
}
