<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

use OCP\DB\IPreparedStatement;

/**
 * small wrapper around \Doctrine\DBAL\Driver\Statement to make it behave, more like an MDB2 Statement
 *
 * @method boolean bindValue(mixed $param, mixed $value, integer $type = null);
 * @method string errorCode();
 * @method array errorInfo();
 * @method integer rowCount();
 * @method array fetchAll(integer $fetchMode = null);
 */
class OC_DB_StatementWrapper {
	/** @var IPreparedStatement */
	private $statement = null;

	/** @var bool */
	private $isManipulation = false;

	/** @var array */
	private $lastArguments = [];

	/**
	 * @param IPreparedStatement $statement
	 * @param boolean $isManipulation
	 */
	public function __construct(IPreparedStatement $statement, $isManipulation) {
		$this->statement = $statement;
		$this->isManipulation = $isManipulation;
	}

	/**
	 * pass all other function directly to the \Doctrine\DBAL\Driver\Statement
	 */
	public function __call($name,$arguments) {
		return call_user_func_array([$this->statement,$name], $arguments);
	}

	/**
	 * make execute return the result instead of a bool
	 *
	 * @param mixed[] $input
	 * @return \OC_DB_StatementWrapper|int|bool
	 * @deprecated
	 */
	public function execute($input = []) {
		$this->lastArguments = $input;
		try {
			if (count($input) > 0) {
				$result = $this->statement->execute($input);
			} else {
				$result = $this->statement->execute();
			}
		} catch (\Doctrine\DBAL\Exception $e) {
			return false;
		}

		if ($this->isManipulation) {
			return $this->statement->rowCount();
		}

		return $this;
	}

	/**
	 * provide an alias for fetch
	 *
	 * @return mixed
	 * @deprecated
	 */
	public function fetchRow() {
		return $this->statement->fetch();
	}

	/**
	 * Provide a simple fetchOne.
	 *
	 * fetch single column from the next row
	 * @return string
	 * @deprecated
	 */
	public function fetchOne() {
		return $this->statement->fetchOne();
	}

	/**
	 * Closes the cursor, enabling the statement to be executed again.
	 *
	 * @deprecated Use Result::free() instead.
	 */
	public function closeCursor(): void {
		$this->statement->closeCursor();
	}

	/**
	 * Binds a PHP variable to a corresponding named or question mark placeholder in the
	 * SQL statement that was use to prepare the statement.
	 *
	 * @param mixed $column Either the placeholder name or the 1-indexed placeholder index
	 * @param mixed $variable The variable to bind
	 * @param integer|null $type one of the  PDO::PARAM_* constants
	 * @param integer|null $length max length when using an OUT bind
	 * @return boolean
	 */
	public function bindParam($column, &$variable, $type = null, $length = null) {
		return $this->statement->bindParam($column, $variable, $type, $length);
	}
}
