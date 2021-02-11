<?php

declare(strict_types=1);

/**
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
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

namespace Test\DB\Exception;

use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Driver\Exception as TheDriverException;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\DBAL\Exception\DatabaseObjectExistsException;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\ServerException;
use Doctrine\DBAL\Exception\SyntaxErrorException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OC\DB\Exceptions\DbalException;

class DbalExceptionTest extends \Test\TestCase {

	/** @var TheDriverException */
	protected $driverException;

	protected function setUp(): void {
		parent::setUp();
		$this->driverException = $this->createMock(TheDriverException::class);
	}

	/**
	 * @dataProvider dataDriverException
	 * @param string $class
	 * @param int $reason
	 */
	public function testDriverException(string $class, int $reason): void {
		$result = DbalException::wrap(new $class($this->driverException, null));
		$this->assertSame($reason, $result->getReason());
	}

	public function dataDriverException(): array {
		return [
			[ForeignKeyConstraintViolationException::class, DbalException::REASON_FOREIGN_KEY_VIOLATION],
			[NotNullConstraintViolationException::class, DbalException::REASON_NOT_NULL_CONSTRAINT_VIOLATION],
			[UniqueConstraintViolationException::class, DbalException::REASON_UNIQUE_CONSTRAINT_VIOLATION],
			[ConstraintViolationException::class, DbalException::REASON_CONSTRAINT_VIOLATION],
			[DatabaseObjectExistsException::class, DbalException::REASON_DATABASE_OBJECT_EXISTS],
			[DatabaseObjectNotFoundException::class, DbalException::REASON_DATABASE_OBJECT_NOT_FOUND],
			[DeadlockException::class, DbalException::REASON_DEADLOCK],
			[InvalidFieldNameException::class, DbalException::REASON_INVALID_FIELD_NAME],
			[NonUniqueFieldNameException::class, DbalException::REASON_NON_UNIQUE_FIELD_NAME],
			[SyntaxErrorException::class, DbalException::REASON_SYNTAX_ERROR],
			[ServerException::class, DbalException::REASON_SERVER],
			[DriverException::class, DbalException::REASON_DRIVER],
		];
	}

	public function testConnectionException(): void {
		$result = DbalException::wrap(ConnectionException::noActiveTransaction());
		$this->assertSame(DbalException::REASON_CONNECTION_LOST, $result->getReason());
	}

	public function testInvalidArgumentException(): void {
		$result = DbalException::wrap(InvalidArgumentException::fromEmptyCriteria());
		$this->assertSame(DbalException::REASON_INVALID_ARGUMENT, $result->getReason());
	}
}
