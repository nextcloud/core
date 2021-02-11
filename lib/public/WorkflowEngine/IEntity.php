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

use OCP\EventDispatcher\Event;

/**
 * Interface IEntity
 *
 * This interface represents an entity that supports events the workflow engine
 * can listen to. For example a file with the create, update, etc. events.
 *
 * Ensure to listen to 'OCP/WorkflowEngine::loadEntities' for registering your
 * entities.
 *
 * @since 18.0.0
 */
interface IEntity {

	/**
	 * returns a translated name to be presented in the web interface.
	 *
	 * Example: "File" (en), "Dosiero" (eo)
	 *
	 * @since 18.0.0
	 */
	public function getName(): string;

	/**
	 * returns the URL to the icon of the entity for display in the web interface.
	 *
	 * Usually, the implementation would utilize the `imagePath()` method of the
	 * `\OCP\IURLGenerator` instance and simply return its result.
	 *
	 * Example implementation: return $this->urlGenerator->imagePath('myApp', 'cat.svg');
	 *
	 * @since 18.0.0
	 */
	public function getIcon(): string;

	/**
	 * returns a list of supported events
	 *
	 * @return IEntityEvent[]
	 * @since 18.0.0
	 */
	public function getEvents(): array;

	/**
	 * @since 18.0.0
	 */
	public function prepareRuleMatcher(IRuleMatcher $ruleMatcher, string $eventName, Event $event): void;

	/**
	 * returns whether the provided user id is allowed to run a flow against
	 * the known context
	 *
	 * @since 18.0.0
	 */
	public function isLegitimatedForUserId(string $userId): bool;
}
