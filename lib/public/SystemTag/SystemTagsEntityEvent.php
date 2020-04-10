<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP\SystemTag;

use OCP\EventDispatcher\Event;

/**
 * Class SystemTagsEntityEvent
 *
 * @package OCP\SystemTag
 * @since 9.1.0
 */
class SystemTagsEntityEvent extends Event {

	const EVENT_ENTITY = 'OCP\SystemTag\ISystemTagManager::registerEntity';

	/** @var string */
	protected $event;
	/** @var \Closure[] */
	protected $collections;

	/**
	 * SystemTagsEntityEvent constructor.
	 *
	 * @param string $event
	 * @since 9.1.0
	 */
	public function __construct(string $event) {
		$this->event = $event;
		$this->collections = [];
	}

	/**
	 * @param string $name
	 * @param \Closure $entityExistsFunction The closure should take one
	 *                 argument, which is the id of the entity, that tags
	 *                 should be handled for. The return should then be bool,
	 *                 depending on whether tags are allowed (true) or not.
	 * @throws \OutOfBoundsException when the entity name is already taken
	 * @since 9.1.0
	 */
	public function addEntityCollection(string $name, \Closure $entityExistsFunction) {
		if (isset($this->collections[$name])) {
			throw new \OutOfBoundsException('Duplicate entity name "' . $name . '"');
		}

		$this->collections[$name] = $entityExistsFunction;
	}

	/**
	 * @return \Closure[]
	 * @since 9.1.0
	 */
	public function getEntityCollections(): array {
		return $this->collections;
	}
}
