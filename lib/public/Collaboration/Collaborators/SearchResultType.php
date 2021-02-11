<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP\Collaboration\Collaborators;

/**
 * Class SearchResultType
 *
 * @since 13.0.0
 */
class SearchResultType {
	/** @var string  */
	protected $label;

	/**
	 * SearchResultType constructor.
	 *
	 * @param string $label
	 * @since 13.0.0
	 */
	public function __construct($label) {
		$this->label = $this->getValidatedType($label);
	}

	/**
	 * @return string
	 * @since 13.0.0
	 */
	public function getLabel() {
		return $this->label;
	}

	/**
	 * @param $type
	 * @return string
	 * @throws \InvalidArgumentException
	 * @since 13.0.0
	 */
	protected function getValidatedType($type) {
		$type = trim((string)$type);

		if ($type === '') {
			throw new \InvalidArgumentException('Type must not be empty');
		}

		if ($type === 'exact') {
			throw new \InvalidArgumentException('Provided type is a reserved word');
		}

		return $type;
	}
}
