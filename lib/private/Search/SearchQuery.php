<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OC\Search;

use OCP\Search\ISearchQuery;

class SearchQuery implements ISearchQuery {
	public const LIMIT_DEFAULT = 5;

	/** @var string */
	private $term;

	/** @var int */
	private $sortOrder;

	/** @var int */
	private $limit;

	/** @var int|string|null */
	private $cursor;

	/** @var string */
	private $route;

	/** @var array */
	private $routeParameters;

	/**
	 * @param string $term
	 * @param int $sortOrder
	 * @param int $limit
	 * @param int|string|null $cursor
	 * @param string $route
	 * @param array $routeParameters
	 */
	public function __construct(string $term,
								int $sortOrder = ISearchQuery::SORT_DATE_DESC,
								int $limit = self::LIMIT_DEFAULT,
								$cursor = null,
								string $route = '',
								array $routeParameters = []) {
		$this->term = $term;
		$this->sortOrder = $sortOrder;
		$this->limit = $limit;
		$this->cursor = $cursor;
		$this->route = $route;
		$this->routeParameters = $routeParameters;
	}

	/**
	 * @inheritDoc
	 */
	public function getTerm(): string {
		return $this->term;
	}

	/**
	 * @inheritDoc
	 */
	public function getSortOrder(): int {
		return $this->sortOrder;
	}

	/**
	 * @inheritDoc
	 */
	public function getLimit(): int {
		return $this->limit;
	}

	/**
	 * @inheritDoc
	 */
	public function getCursor() {
		return $this->cursor;
	}

	/**
	 * @inheritDoc
	 */
	public function getRoute(): string {
		return $this->route;
	}

	/**
	 * @inheritDoc
	 */
	public function getRouteParameters(): array {
		return $this->routeParameters;
	}
}
