<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017, Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCP\Federation;

/**
 * Parsed federated cloud id
 *
 * @since 12.0.0
 */
interface ICloudId {
	/**
	 * The remote cloud id
	 *
	 * @return string
	 * @since 12.0.0
	 */
	public function getId(): string;

	/**
	 * Get a clean representation of the cloud id for display
	 *
	 * @return string
	 * @since 12.0.0
	 */
	public function getDisplayId(): string;

	/**
	 * The username on the remote server
	 *
	 * @return string
	 * @since 12.0.0
	 */
	public function getUser(): string;

	/**
	 * The base address of the remote server
	 *
	 * @return string
	 * @since 12.0.0
	 */
	public function getRemote(): string;
}
