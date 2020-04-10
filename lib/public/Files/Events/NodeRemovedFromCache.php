<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCP\Files\Events;

use OCP\EventDispatcher\Event;
use OCP\Files\Storage\IStorage;

/**
 * @since 18.0.0
 */
class NodeRemovedFromCache extends Event {

	/** @var IStorage */
	private $storage;

	/** @var string */
	private $path;

	/**
	 * @param IStorage $storage
	 * @param string $path
	 * @since 18.0.0
	 */
	public function __construct(IStorage $storage,
								string $path) {
		parent::__construct();
		$this->storage = $storage;
		$this->path = $path;
	}

	/**
	 * @return IStorage
	 * @since 18.0.0
	 */
	public function getStorage(): IStorage {
		return $this->storage;
	}

	/**
	 * @return string
	 * @since 18.0.0
	 */
	public function getPath(): string {
		return $this->path;
	}

}
