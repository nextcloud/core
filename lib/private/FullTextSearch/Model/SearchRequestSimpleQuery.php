<?php

declare(strict_types=1);

/**
 * @copyright 2018
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Maxence Lange <maxence@artificial-owl.com>
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

namespace OC\FullTextSearch\Model;

use JsonSerializable;
use OCP\FullTextSearch\Model\ISearchRequestSimpleQuery;

/**
 * @since 17.0.0
 *
 * Class SearchRequestSimpleQuery
 *
 * @package OC\FullTextSearch\Model
 */
final class SearchRequestSimpleQuery implements ISearchRequestSimpleQuery, JsonSerializable {


	/** @var int */
	private $type = 0;

	/** @var string */
	private $field = '';

	/** @var array */
	private $values = [];


	/**
	 * SearchRequestQuery constructor.
	 *
	 * @param $type
	 * @param $field
	 *
	 * @since 17.0.0
	 */
	public function __construct(string $field, int $type) {
		$this->field = $field;
		$this->type = $type;
	}


	/**
	 * Get the compare type of the query
	 *
	 * @return int
	 * @since 17.0.0
	 */
	public function getType(): int {
		return $this->type;
	}


	/**
	 * Get the field to apply query
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getField(): string {
		return $this->field;
	}

	/**
	 * Set the field to apply query
	 *
	 * @param string $field
	 *
	 * @return ISearchRequestSimpleQuery
	 * @since 17.0.0
	 */
	public function setField(string $field): ISearchRequestSimpleQuery {
		$this->field = $field;

		return $this;
	}


	/**
	 * Get the value to compare (string)
	 *
	 * @return array
	 * @since 17.0.0
	 */
	public function getValues(): array {
		return $this->values;
	}


	/**
	 * Add value to compare (string)
	 *
	 * @param string $value
	 *
	 * @return ISearchRequestSimpleQuery
	 * @since 17.0.0
	 */
	public function addValue(string $value): ISearchRequestSimpleQuery {
		$this->values[] = $value;

		return $this;
	}

	/**
	 * Add value to compare (int)
	 *
	 * @param int $value
	 *
	 * @return ISearchRequestSimpleQuery
	 * @since 17.0.0
	 */
	public function addValueInt(int $value): ISearchRequestSimpleQuery {
		$this->values[] = $value;

		return $this;
	}

	/**
	 * Add value to compare (array)
	 *
	 * @param array $value
	 *
	 * @return ISearchRequestSimpleQuery
	 * @since 17.0.0
	 */
	public function addValueArray(array $value): ISearchRequestSimpleQuery {
		$this->values[] = $value;

		return $this;
	}

	/**
	 * Add value to compare (bool)
	 *
	 * @param bool $value
	 *
	 * @return ISearchRequestSimpleQuery
	 * @since 17.0.0
	 */
	public function addValueBool(bool $value): ISearchRequestSimpleQuery {
		$this->values[] = $value;

		return $this;
	}


	/**
	 * @return array|mixed
	 * @since 17.0.0
	 */
	public function jsonSerialize() {
		return [
			'type' => $this->getType(),
			'field' => $this->getField(),
			'values' => $this->getValues()
		];
	}
}
