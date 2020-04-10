<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017, Robin Appelman <robin@icewind.nl>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
 * Interface for resolving federated cloud ids
 *
 * @since 12.0.0
 */
interface ICloudIdManager {
	/**
	 * @param string $cloudId
	 * @return ICloudId
	 * @throws \InvalidArgumentException
	 *
	 * @since 12.0.0
	 */
	public function resolveCloudId(string $cloudId): ICloudId;

	/**
	 * Get the cloud id for a remote user
	 *
	 * @param string $user
	 * @param string $remote
	 * @return ICloudId
	 *
	 * @since 12.0.0
	 */
	public function getCloudId(string $user, string $remote): ICloudId;

	/**
	 * Check if the input is a correctly formatted cloud id
	 *
	 * @param string $cloudId
	 * @return bool
	 *
	 * @since 12.0.0
	 */
	public function isValidCloudId(string $cloudId): bool;
}
