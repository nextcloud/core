<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
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

namespace OC\Files\Search;

use OCP\Files\Search\ISearchOperator;
use OCP\Files\Search\ISearchOrder;
use OCP\Files\Search\ISearchQuery;
use OCP\IUser;

class SearchQuery implements ISearchQuery {
	/** @var  ISearchOperator */
	private $searchOperation;
	/** @var  integer */
	private $limit;
	/** @var  integer */
	private $offset;
	/** @var  ISearchOrder[] */
	private $order;
	/** @var IUser */
	private $user;
	private $limitToHome;

	/**
	 * SearchQuery constructor.
	 *
	 * @param ISearchOperator $searchOperation
	 * @param int $limit
	 * @param int $offset
	 * @param array $order
	 * @param IUser $user
	 * @param bool $limitToHome
	 */
	public function __construct(
		ISearchOperator $searchOperation,
		int $limit,
		int $offset,
		array $order,
		IUser $user,
		bool $limitToHome = false
	) {
		$this->searchOperation = $searchOperation;
		$this->limit = $limit;
		$this->offset = $offset;
		$this->order = $order;
		$this->user = $user;
		$this->limitToHome = $limitToHome;
	}

	/**
	 * @return ISearchOperator
	 */
	public function getSearchOperation() {
		return $this->searchOperation;
	}

	/**
	 * @return int
	 */
	public function getLimit() {
		return $this->limit;
	}

	/**
	 * @return int
	 */
	public function getOffset() {
		return $this->offset;
	}

	/**
	 * @return ISearchOrder[]
	 */
	public function getOrder() {
		return $this->order;
	}

	/**
	 * @return IUser
	 */
	public function getUser() {
		return $this->user;
	}

	public function limitToHome(): bool {
		return $this->limitToHome;
	}
}
