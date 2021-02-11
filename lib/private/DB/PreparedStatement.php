<?php

declare(strict_types=1);

/*
 * @copyright 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
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
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;
use OCP\DB\IPreparedStatement;
use OCP\DB\IResult;
use PDO;

/**
 * Adapts our public API to what doctrine/dbal exposed with 2.6
 *
 * The old dbal statement had stateful methods e.g. to fetch data from an executed
 * prepared statement. To provide backwards compatibility to apps we need to make
 * this class stateful. As soon as those now deprecated exposed methods are gone,
 * we can limit the API of this adapter to the methods that map to the direct dbal
 * methods without much magic.
 */
class PreparedStatement implements IPreparedStatement {

	/** @var Statement */
	private $statement;

	/** @var IResult|null */
	private $result;

	public function __construct(Statement $statement) {
		$this->statement = $statement;
	}

	public function closeCursor(): bool {
		$this->getResult()->closeCursor();

		return true;
	}

	public function fetch(int $fetchMode = PDO::FETCH_ASSOC) {
		return $this->getResult()->fetch($fetchMode);
	}

	public function fetchAll(int $fetchMode = PDO::FETCH_ASSOC): array {
		return $this->getResult()->fetchAll($fetchMode);
	}

	public function fetchColumn() {
		return $this->getResult()->fetchOne();
	}

	public function fetchOne() {
		return $this->getResult()->fetchOne();
	}

	public function bindValue($param, $value, $type = ParameterType::STRING): bool {
		return $this->statement->bindValue($param, $value, $type);
	}

	public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool {
		return $this->statement->bindParam($param, $variable, $type, $length);
	}

	public function execute($params = null): IResult {
		return ($this->result = new ResultAdapter($this->statement->execute($params)));
	}

	public function rowCount(): int {
		return $this->getResult()->rowCount();
	}

	private function getResult(): IResult {
		if ($this->result !== null) {
			return $this->result;
		}

		throw new Exception("You have to execute the prepared statement before accessing the results");
	}
}
