<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\DB\QueryBuilder;

use OCP\DB\QueryBuilder\ICompositeExpression;

class CompositeExpression implements ICompositeExpression, \Countable {
	/** @var \Doctrine\DBAL\Query\Expression\CompositeExpression */
	protected $compositeExpression;

	/**
	 * Constructor.
	 *
	 * @param \Doctrine\DBAL\Query\Expression\CompositeExpression $compositeExpression
	 */
	public function __construct(\Doctrine\DBAL\Query\Expression\CompositeExpression $compositeExpression) {
		$this->compositeExpression = $compositeExpression;
	}

	/**
	 * Adds multiple parts to composite expression.
	 *
	 * @param array $parts
	 *
	 * @return \OCP\DB\QueryBuilder\ICompositeExpression
	 */
	public function addMultiple(array $parts = []) {
		$this->compositeExpression->addMultiple($parts);

		return $this;
	}

	/**
	 * Adds an expression to composite expression.
	 *
	 * @param mixed $part
	 *
	 * @return \OCP\DB\QueryBuilder\ICompositeExpression
	 */
	public function add($part) {
		$this->compositeExpression->add($part);

		return $this;
	}

	/**
	 * Retrieves the amount of expressions on composite expression.
	 *
	 * @return integer
	 */
	public function count() {
		return $this->compositeExpression->count();
	}

	/**
	 * Returns the type of this composite expression (AND/OR).
	 *
	 * @return string
	 */
	public function getType() {
		return $this->compositeExpression->getType();
	}

	/**
	 * Retrieves the string representation of this composite expression.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return (string) $this->compositeExpression;
	}
}
