<?php
/**
 * @copyright Copyright (c) 2016 Morris Jobke <hey@morrisjobke.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCP\WorkflowEngine;

/**
 * Interface ICheck
 *
 * @package OCP\WorkflowEngine
 * @since 9.1
 */
interface ICheck {
	/**
	 * @param string $operator
	 * @param string $value
	 * @return bool
	 * @since 9.1
	 */
	public function executeCheck($operator, $value);

	/**
	 * @param string $operator
	 * @param string $value
	 * @throws \UnexpectedValueException
	 * @since 9.1
	 */
	public function validateCheck($operator, $value);

	/**
	 * returns a list of Entities the checker supports. The values must match
	 * the class name of the entity.
	 *
	 * An empty result means the check is universally available.
	 *
	 * @since 18.0.0
	 */
	public function supportedEntities(): array;

	/**
	 * returns whether the operation can be used in the requested scope.
	 *
	 * Scope IDs are defined as constants in OCP\WorkflowEngine\IManager. At
	 * time of writing these are SCOPE_ADMIN and SCOPE_USER.
	 *
	 * For possibly unknown future scopes the recommended behaviour is: if
	 * user scope is permitted, the default behaviour should return `true`,
	 * otherwise `false`.
	 *
	 * @since 18.0.0
	 */
	public function isAvailableForScope(int $scope): bool;
}
