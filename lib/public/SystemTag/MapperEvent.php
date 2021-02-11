<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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
 * Class MapperEvent
 *
 * @since 9.0.0
 */
class MapperEvent extends Event {

	/**
	 * @deprecated 22.0.0
	 */
	public const EVENT_ASSIGN = 'OCP\SystemTag\ISystemTagObjectMapper::assignTags';

	/**
	 * @deprecated 22.0.0
	 */
	public const EVENT_UNASSIGN = 'OCP\SystemTag\ISystemTagObjectMapper::unassignTags';

	/** @var string */
	protected $event;
	/** @var string */
	protected $objectType;
	/** @var string */
	protected $objectId;
	/** @var int[] */
	protected $tags;

	/**
	 * DispatcherEvent constructor.
	 *
	 * @param string $event
	 * @param string $objectType
	 * @param string $objectId
	 * @param int[] $tags
	 * @since 9.0.0
	 */
	public function __construct(string $event, string $objectType, string $objectId, array $tags) {
		$this->event = $event;
		$this->objectType = $objectType;
		$this->objectId = $objectId;
		$this->tags = $tags;
	}

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getEvent(): string {
		return $this->event;
	}

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getObjectType(): string {
		return $this->objectType;
	}

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getObjectId(): string {
		return $this->objectId;
	}

	/**
	 * @return int[]
	 * @since 9.0.0
	 */
	public function getTags(): array {
		return $this->tags;
	}
}
