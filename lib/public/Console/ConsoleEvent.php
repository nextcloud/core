<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCP\Console;

use OCP\EventDispatcher\Event;

/**
 * Class ConsoleEvent
 *
 * @package OCP\Console
 * @since 9.0.0
 */
class ConsoleEvent extends Event {

	const EVENT_RUN = 'OC\Console\Application::run';

	/** @var string */
	protected $event;

	/** @var string[] */
	protected $arguments;

	/**
	 * DispatcherEvent constructor.
	 *
	 * @param string $event
	 * @param string[] $arguments
	 * @since 9.0.0
	 */
	public function __construct($event, array $arguments) {
		$this->event = $event;
		$this->arguments = $arguments;
	}

	/**
	 * @return string
	 * @since 9.0.0
	 */
	public function getEvent() {
		return $this->event;
	}

	/**
	 * @return string[]
	 * @since 9.0.0
	 */
	public function getArguments() {
		return $this->arguments;
	}
}
