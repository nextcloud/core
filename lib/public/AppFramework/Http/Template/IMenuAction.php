<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCP\AppFramework\Http\Template;

/**
 * Interface IMenuAction
 *
 * @since 14.0
 */
interface IMenuAction {

	/**
	 * @since 14.0.0
	 * @return string
	 */
	public function getId(): string;

	/**
	 * @since 14.0.0
	 * @return string
	 */
	public function getLabel(): string;

	/**
	 * @since 14.0.0
	 * @return string
	 */
	public function getLink(): string;

	/**
	 * @since 14.0.0
	 * @return int
	 */
	public function getPriority(): int;

	/**
	 * @since 14.0.0
	 * @return string
	 */
	public function render(): string;
}
