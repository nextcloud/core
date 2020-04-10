<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\DB\QueryBuilder\FunctionBuilder;

use OC\DB\QueryBuilder\QueryFunction;
use OC\DB\QueryBuilder\QuoteHelper;
use OCP\DB\QueryBuilder\IFunctionBuilder;

class FunctionBuilder implements IFunctionBuilder {
	/** @var QuoteHelper */
	protected $helper;

	/**
	 * ExpressionBuilder constructor.
	 *
	 * @param QuoteHelper $helper
	 */
	public function __construct(QuoteHelper $helper) {
		$this->helper = $helper;
	}

	public function md5($input) {
		return new QueryFunction('MD5(' . $this->helper->quoteColumnName($input) . ')');
	}

	public function concat($x, $y) {
		return new QueryFunction('CONCAT(' . $this->helper->quoteColumnName($x) . ', ' . $this->helper->quoteColumnName($y) . ')');
	}

	public function substring($input, $start, $length = null) {
		if ($length) {
			return new QueryFunction('SUBSTR(' . $this->helper->quoteColumnName($input) . ', ' . $this->helper->quoteColumnName($start) . ', ' . $this->helper->quoteColumnName($length) . ')');
		} else {
			return new QueryFunction('SUBSTR(' . $this->helper->quoteColumnName($input) . ', ' . $this->helper->quoteColumnName($start) . ')');
		}
	}

	public function sum($field) {
		return new QueryFunction('SUM(' . $this->helper->quoteColumnName($field) . ')');
	}

	public function lower($field) {
		return new QueryFunction('LOWER(' . $this->helper->quoteColumnName($field) . ')');
	}

	public function add($x, $y) {
		return new QueryFunction($this->helper->quoteColumnName($x) . ' + ' . $this->helper->quoteColumnName($y));
	}

	public function subtract($x, $y) {
		return new QueryFunction($this->helper->quoteColumnName($x) . ' - ' . $this->helper->quoteColumnName($y));
	}

	public function count($count = '', $alias = '') {
		$alias = $alias ? (' AS ' . $this->helper->quoteColumnName($alias)) : '';
		$quotedName = $count === '' ? '*' : $this->helper->quoteColumnName($count);
		return new QueryFunction('COUNT(' . $quotedName . ')' . $alias);
	}

	public function max($field) {
		return new QueryFunction('MAX(' . $this->helper->quoteColumnName($field) . ')');
	}

	public function min($field) {
		return new QueryFunction('MIN(' . $this->helper->quoteColumnName($field) . ')');
	}

	public function greatest($x, $y) {
		return new QueryFunction('GREATEST(' . $this->helper->quoteColumnName($x) . ', ' . $this->helper->quoteColumnName($y) . ')');
	}

	public function least($x, $y) {
		return new QueryFunction('LEAST(' . $this->helper->quoteColumnName($x) . ', ' . $this->helper->quoteColumnName($y) . ')');
	}
}
