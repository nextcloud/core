<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCP\WorkflowEngine\EntityContext;

/**
 * Interface IContextPortation
 *
 * Occasionally an IEntity needs to be reused not in the same, but a new
 * request. As IEntities receive custom context information during a flow
 * cycle, sometimes it might be necessary to export context identifiers to
 * be able to recreate the state at a later point. For example: handling
 * translations in a notification INotifier.
 *
 *
 * @since 20.0.0
 */
interface IContextPortation {

	/**
	 * All relevant context identifiers that are needed to restore the state
	 * of an entity shall be returned with this method. The resulting array
	 * must be JSON-serializable.
	 *
	 * @since 20.0.0
	 */
	public function exportContextIDs(): array;

	/**
	 * This method receives the array as returned by `exportContextIDs()` in
	 * order to restore the state of the IEntity if necessary.
	 *
	 * @since 20.0.0
	 */
	public function importContextIDs(array $contextIDs): void;
}
