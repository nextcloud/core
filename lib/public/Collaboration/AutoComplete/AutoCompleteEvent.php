<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCP\Collaboration\AutoComplete;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * @since 16.0.0
 */
class AutoCompleteEvent extends GenericEvent {

	/**
	 * @param array $arguments
	 * @since 16.0.0
	 */
	public function __construct(array $arguments) {
		parent::__construct(null, $arguments);
	}

	/**
	 * @since 16.0.0
	 */
	public function getResults(): array {
		return $this->getArgument('results');
	}

	/**
	 * @param array $results
	 * @since 16.0.0
	 */
	public function setResults(array $results): void {
		$this->setArgument('results', $results);
	}

	/**
	 * @since 16.0.0
	 */
	public function getSearchTerm(): string {
		return $this->getArgument('search');
	}

	/**
	 * @return int[]
	 * @since 16.0.0
	 */
	public function getShareTypes(): array {
		return $this->getArgument('shareTypes');
	}

	/**
	 * @since 16.0.0
	 */
	public function getItemType(): string {
		return $this->getArgument('itemType');
	}

	/**
	 * @since 16.0.0
	 */
	public function getItemId(): string {
		return $this->getArgument('itemId');
	}

	/**
	 * @since 16.0.0
	 */
	public function getSorter(): string {
		return $this->getArgument('sorter');
	}

	/**
	 * @since 16.0.0
	 */
	public function getLimit(): int {
		return $this->getArgument('limit');
	}

}
