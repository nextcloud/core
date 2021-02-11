<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

namespace OCP\Authentication;

/**
 * @since 20.0.0
 */
interface IAlternativeLogin {

	/**
	 * Label shown on the login option
	 * @return string
	 * @since 20.0.0
	 */
	public function getLabel(): string;

	/**
	 * Relative link to the login option
	 * @return string
	 * @since 20.0.0
	 */
	public function getLink(): string;

	/**
	 * CSS classes added to the alternative login option on the login screen
	 * @return string
	 * @since 20.0.0
	 */
	public function getClass(): string;

	/**
	 * Load necessary resources to present the login option, e.g. style-file to style the getClass()
	 * @since 20.0.0
	 */
	public function load(): void;
}
