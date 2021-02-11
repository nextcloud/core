<?php
/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCP\DirectEditing;

use JsonSerializable;

/**
 * Class ATemplate
 *
 * @since 18.0.0
 */
abstract class ATemplate implements JsonSerializable {

	/**
	 * Return a unique id so the app can identify the template
	 *
	 * @since 18.0.0
	 * @return string
	 */
	abstract public function getId(): string;

	/**
	 * Return a title that is displayed to the user
	 *
	 * @since 18.0.0
	 * @return string
	 */
	abstract public function getTitle(): string;

	/**
	 * Return a link to the template preview image
	 *
	 * @since 18.0.0
	 * @return string
	 */
	abstract public function getPreview(): string;

	/**
	 * @since 18.0.0
	 * @return array|mixed
	 */
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'preview' => $this->getPreview(),
		];
	}
}
