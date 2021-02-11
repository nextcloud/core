<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCP\WorkflowEngine;

/**
 * Interface IEntityEvent
 *
 * represents an entitiy event that is dispatched via EventDispatcher
 *
 *
 * @since 18.0.0
 */
interface IEntityEvent {
	/**
	 * returns a translated name to be presented in the web interface.
	 *
	 * Example: "created" (en), "kreita" (eo)
	 *
	 * @since 18.0.0
	 */
	public function getDisplayName(): string;

	/**
	 * returns the event name that is emitted by the EventDispatcher, e.g.:
	 *
	 * Example: "OCA\MyApp\Factory\Cats::postCreated"
	 *
	 * @since 18.0.0
	 */
	public function getEventName(): string;
}
