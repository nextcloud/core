<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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
 * Class ManagerEvent
 *
 * @since 9.0.0
 */
class ManagerEvent extends Event {

	/**
	 * @deprecated 22.0.0
	 */
	public const EVENT_CREATE = 'OCP\SystemTag\ISystemTagManager::createTag';

	/**
	 * @deprecated 22.0.0
	 */
	public const EVENT_UPDATE = 'OCP\SystemTag\ISystemTagManager::updateTag';

	/**
	 * @deprecated 22.0.0
	 */
	public const EVENT_DELETE = 'OCP\SystemTag\ISystemTagManager::deleteTag';

	/** @var string */
	protected $event;
	/** @var ISystemTag */
	protected $tag;
	/** @var ISystemTag */
	protected $beforeTag;

	/**
	 * DispatcherEvent constructor.
	 *
	 * @param string $event
	 * @param ISystemTag $tag
	 * @param ISystemTag|null $beforeTag
	 * @since 9.0.0
	 */
	public function __construct(string $event, ISystemTag $tag, ISystemTag $beforeTag = null) {
		$this->event = $event;
		$this->tag = $tag;
		$this->beforeTag = $beforeTag;
	}

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getEvent(): string {
		return $this->event;
	}

	/**
	 * @return ISystemTag
	 * @since 9.0.0
	 */
	public function getTag(): ISystemTag {
		return $this->tag;
	}

	/**
	 * @return ISystemTag
	 * @since 9.0.0
	 * @throws \BadMethodCallException
	 */
	public function getTagBefore(): ISystemTag {
		if ($this->event !== self::EVENT_UPDATE) {
			throw new \BadMethodCallException('getTagBefore is only available on the update Event');
		}
		return $this->beforeTag;
	}
}
