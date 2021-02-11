<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCP\Search;

use JsonSerializable;
use function array_values;

/**
 * @since 20.0.0
 */
final class SearchResult implements JsonSerializable {

	/** @var string */
	private $name;

	/** @var bool */
	private $isPaginated;

	/** @var SearchResultEntry[] */
	private $entries;

	/** @var int|string|null */
	private $cursor;

	/**
	 * @param string $name the translated name of the result section or group, e.g. "Mail"
	 * @param bool $isPaginated
	 * @param SearchResultEntry[] $entries
	 * @param null $cursor
	 *
	 * @since 20.0.0
	 */
	private function __construct(string $name,
								 bool $isPaginated,
								 array $entries,
								 $cursor = null) {
		$this->name = $name;
		$this->isPaginated = $isPaginated;
		$this->entries = $entries;
		$this->cursor = $cursor;
	}

	/**
	 * @param SearchResultEntry[] $entries
	 *
	 * @return static
	 *
	 * @since 20.0.0
	 */
	public static function complete(string $name, array $entries): self {
		return new self(
			$name,
			false,
			$entries
		);
	}

	/**
	 * @param SearchResultEntry[] $entries
	 * @param int|string $cursor
	 *
	 * @return static
	 *
	 * @since 20.0.0
	 */
	public static function paginated(string $name,
									array $entries,
									 $cursor): self {
		return new self(
			$name,
			true,
			$entries,
			$cursor
		);
	}

	/**
	 * @return array
	 *
	 * @since 20.0.0
	 */
	public function jsonSerialize(): array {
		return [
			'name' => $this->name,
			'isPaginated' => $this->isPaginated,
			'entries' => array_values($this->entries),
			'cursor' => $this->cursor,
		];
	}
}
