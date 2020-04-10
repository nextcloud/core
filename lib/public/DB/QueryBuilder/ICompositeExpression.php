<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCP\DB\QueryBuilder;

/**
 * This class provides a wrapper around Doctrine's CompositeExpression
 * @since 8.2.0
 */
interface ICompositeExpression {
	/**
	 * Adds multiple parts to composite expression.
	 *
	 * @param array $parts
	 *
	 * @return ICompositeExpression
	 * @since 8.2.0
	 */
	public function addMultiple(array $parts = []);

	/**
	 * Adds an expression to composite expression.
	 *
	 * @param mixed $part
	 *
	 * @return ICompositeExpression
	 * @since 8.2.0
	 */
	public function add($part);

	/**
	 * Retrieves the amount of expressions on composite expression.
	 *
	 * @return integer
	 * @since 8.2.0
	 */
	public function count();

	/**
	 * Returns the type of this composite expression (AND/OR).
	 *
	 * @return string
	 * @since 8.2.0
	 */
	public function getType();
}
