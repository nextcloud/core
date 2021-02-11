<?php

declare(strict_types=1);

/*
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

namespace OC\DB;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use OC\DB\Exceptions\DbalException;
use OCP\DB\IPreparedStatement;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Adapts the public API to our internal DBAL connection wrapper
 */
class ConnectionAdapter implements IDBConnection {

	/** @var Connection */
	private $inner;

	public function __construct(Connection $inner) {
		$this->inner = $inner;
	}

	public function getQueryBuilder(): IQueryBuilder {
		return $this->inner->getQueryBuilder();
	}

	public function prepare($sql, $limit = null, $offset = null): IPreparedStatement {
		try {
			return new PreparedStatement(
				$this->inner->prepare($sql, $limit, $offset)
			);
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function executeQuery(string $sql, array $params = [], $types = []): IResult {
		try {
			return new ResultAdapter(
				$this->inner->executeQuery($sql, $params, $types)
			);
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function executeUpdate(string $sql, array $params = [], array $types = []): int {
		try {
			return $this->inner->executeUpdate($sql, $params, $types);
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function executeStatement($sql, array $params = [], array $types = []): int {
		try {
			return $this->inner->executeStatement($sql, $params, $types);
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function lastInsertId(string $table): int {
		try {
			return (int)$this->inner->lastInsertId($table);
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function insertIfNotExist(string $table, array $input, array $compare = null) {
		try {
			return $this->inner->insertIfNotExist($table, $input, $compare);
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function insertIgnoreConflict(string $table, array $values): int {
		try {
			return $this->inner->insertIgnoreConflict($table, $values);
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function setValues($table, array $keys, array $values, array $updatePreconditionValues = []): int {
		try {
			return $this->inner->setValues($table, $keys, $values, $updatePreconditionValues);
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function lockTable($tableName): void {
		try {
			$this->inner->lockTable($tableName);
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function unlockTable(): void {
		try {
			$this->inner->unlockTable();
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function beginTransaction(): void {
		try {
			$this->inner->beginTransaction();
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function inTransaction(): bool {
		return $this->inner->inTransaction();
	}

	public function commit(): void {
		try {
			$this->inner->commit();
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function rollBack(): void {
		try {
			$this->inner->rollBack();
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function getError(): string {
		return $this->inner->getError();
	}

	public function errorCode() {
		return $this->inner->errorCode();
	}

	public function errorInfo() {
		return $this->inner->errorInfo();
	}

	public function connect(): bool {
		try {
			return $this->inner->connect();
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function close(): void {
		$this->inner->close();
	}

	public function quote($input, $type = IQueryBuilder::PARAM_STR) {
		return $this->inner->quote($input, $type);
	}

	/**
	 * @todo we are leaking a 3rdparty type here
	 */
	public function getDatabasePlatform(): AbstractPlatform {
		return $this->inner->getDatabasePlatform();
	}

	public function dropTable(string $table): void {
		try {
			$this->inner->dropTable($table);
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function tableExists(string $table): bool {
		try {
			return $this->inner->tableExists($table);
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function escapeLikeParameter(string $param): string {
		return $this->inner->escapeLikeParameter($param);
	}

	public function supports4ByteText(): bool {
		return $this->inner->supports4ByteText();
	}

	/**
	 * @todo leaks a 3rdparty type
	 */
	public function createSchema(): Schema {
		try {
			return $this->inner->createSchema();
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function migrateToSchema(Schema $toSchema): void {
		try {
			$this->inner->migrateToSchema($toSchema);
		} catch (Exception $e) {
			throw DbalException::wrap($e);
		}
	}

	public function getInner(): Connection {
		return $this->inner;
	}
}
