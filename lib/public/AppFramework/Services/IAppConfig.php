<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCP\AppFramework\Services;

/**
 * Wrapper for AppConfig for the AppFramework
 *
 * @since 20.0.0
 */
interface IAppConfig {
	/**
	 * Get all keys stored for this app
	 *
	 * @return string[] the keys stored for the app
	 * @since 20.0.0
	 */
	public function getAppKeys(): array ;

	/**
	 * Writes a new app wide value
	 *
	 * @param string $key the key of the value, under which will be saved
	 * @param string $value the value that should be stored
	 * @return void
	 * @since 20.0.0
	 */
	public function setAppValue(string $key, string $value): void;

	/**
	 * Looks up an app wide defined value
	 *
	 * @param string $key the key of the value, under which it was saved
	 * @param string $default the default value to be returned if the value isn't set
	 * @return string the saved value
	 * @since 20.0.0
	 */
	public function getAppValue(string $key, string $default = ''): string;

	/**
	 * Delete an app wide defined value
	 *
	 * @param string $key the key of the value, under which it was saved
	 * @return void
	 * @since 20.0.0
	 */
	public function deleteAppValue(string $key): void;

	/**
	 * Removes all keys in appconfig belonging to the app
	 *
	 * @return void
	 * @since 20.0.0
	 */
	public function deleteAppValues(): void;

	/**
	 * Set a user defined value
	 *
	 * @param string $userId the userId of the user that we want to store the value under
	 * @param string $key the key under which the value is being stored
	 * @param string $value the value that you want to store
	 * @param string $preCondition only update if the config value was previously the value passed as $preCondition
	 * @throws \OCP\PreConditionNotMetException if a precondition is specified and is not met
	 * @throws \UnexpectedValueException when trying to store an unexpected value
	 * @since 20.0.0
	 */
	public function setUserValue(string $userId, string $key, string $value, ?string $preCondition = null): void;

	/**
	 * Shortcut for getting a user defined value
	 *
	 * @param string $userId the userId of the user that we want to store the value under
	 * @param string $key the key under which the value is being stored
	 * @param mixed $default the default value to be returned if the value isn't set
	 * @return string
	 * @since 20.0.0
	 */
	public function getUserValue(string $userId, string $key, string $default = ''): string;

	/**
	 * Delete a user value
	 *
	 * @param string $userId the userId of the user that we want to store the value under
	 * @param string $key the key under which the value is being stored
	 * @since 20.0.0
	 */
	public function deleteUserValue(string $userId, string $key): void;
}
