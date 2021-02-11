<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2012 Bernhard Posselt dev@bernhard-posselt.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\AppFramework\Db;

use OCP\DB\IPreparedStatement;
use OCP\DB\IResult;

/**
 * Simple utility class for testing mappers
 */
abstract class MapperTestUtility extends \Test\TestCase {
	protected $db;
	private $statement;
	private $queryAt;
	private $prepareAt;
	private $fetchAt;
	private $iterators;


	/**
	 * Run this function before the actual test to either set or initialize the
	 * db. After this the db can be accessed by using $this->db
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->db = $this->getMockBuilder(
			'\OCP\IDBConnection')
			->disableOriginalConstructor()
			->getMock();

		$this->statement = $this->createMock(IPreparedStatement::class);
		$this->queryAt = 0;
		$this->prepareAt = 0;
		$this->iterators = [];
		$this->fetchAt = 0;
	}

	/**
	 * Checks if an array is associative
	 * @param array $array
	 * @return bool true if associative
	 */
	private function isAssocArray(array $array) {
		return array_values($array) !== $array;
	}

	/**
	 * Returns the correct PDO constant based on the value type
	 * @param $value
	 * @return int PDO constant
	 */
	private function getPDOType($value) {
		switch (gettype($value)) {
			case 'integer':
				return \PDO::PARAM_INT;
			case 'boolean':
				return \PDO::PARAM_BOOL;
			default:
				return \PDO::PARAM_STR;
		}
	}

	/**
	 * Create mocks and set expected results for database queries
	 * @param string $sql the sql query that you expect to receive
	 * @param array $arguments the expected arguments for the prepare query
	 * method
	 * @param array $returnRows the rows that should be returned for the result
	 * of the database query. If not provided, it wont be assumed that fetch
	 * will be called on the result
	 */
	protected function setMapperResult($sql, $arguments = [], $returnRows = [],
		$limit = null, $offset = null, $expectClose = false) {
		if ($limit === null && $offset === null) {
			$this->db->expects($this->at($this->prepareAt))
				->method('prepare')
				->with($this->equalTo($sql))
				->will(($this->returnValue($this->statement)));
		} elseif ($limit !== null && $offset === null) {
			$this->db->expects($this->at($this->prepareAt))
				->method('prepare')
				->with($this->equalTo($sql), $this->equalTo($limit))
				->will(($this->returnValue($this->statement)));
		} elseif ($limit === null && $offset !== null) {
			$this->db->expects($this->at($this->prepareAt))
				->method('prepare')
				->with($this->equalTo($sql),
					$this->equalTo(null),
					$this->equalTo($offset))
				->will(($this->returnValue($this->statement)));
		} else {
			$this->db->expects($this->at($this->prepareAt))
				->method('prepare')
				->with($this->equalTo($sql),
					$this->equalTo($limit),
					$this->equalTo($offset))
				->will(($this->returnValue($this->statement)));
		}

		$this->iterators[] = new ArgumentIterator($returnRows);

		$iterators = $this->iterators;
		$fetchAt = $this->fetchAt;

		$this->statement->expects($this->any())
			->method('fetch')
			->willReturnCallback(
				function () use ($iterators, $fetchAt) {
					$iterator = $iterators[$fetchAt];
					$result = $iterator->next();

					if ($result === false) {
						$fetchAt++;
					}

					$this->queryAt++;

					return $result;
				}
			);

		if ($this->isAssocArray($arguments)) {
			foreach ($arguments as $key => $argument) {
				$pdoConstant = $this->getPDOType($argument);
				$this->statement->expects($this->at($this->queryAt))
					->method('bindValue')
					->with($this->equalTo($key),
						$this->equalTo($argument),
						$this->equalTo($pdoConstant));
				$this->queryAt++;
			}
		} else {
			$index = 1;
			foreach ($arguments as $argument) {
				$pdoConstant = $this->getPDOType($argument);
				$this->statement->expects($this->at($this->queryAt))
					->method('bindValue')
					->with($this->equalTo($index),
						$this->equalTo($argument),
						$this->equalTo($pdoConstant));
				$index++;
				$this->queryAt++;
			}
		}

		$this->statement->expects($this->at($this->queryAt))
			->method('execute')
			->willReturnCallback(function ($sql, $p = null, $o = null, $s = null) {
				return $this->createMock(IResult::class);
			});
		$this->queryAt++;



		if ($expectClose) {
			$closing = $this->at($this->queryAt);
		} else {
			$closing = $this->any();
		}
		$this->statement->expects($closing)->method('closeCursor');
		$this->queryAt++;

		$this->prepareAt++;
		$this->fetchAt++;
	}
}


class ArgumentIterator {
	private $arguments;

	public function __construct($arguments) {
		$this->arguments = $arguments;
	}

	public function next() {
		$result = array_shift($this->arguments);
		if ($result === null) {
			return false;
		} else {
			return $result;
		}
	}
}
