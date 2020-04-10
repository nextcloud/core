<?php
/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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

namespace OCA\Files_Trashbin\Events;

use OCP\EventDispatcher\Event;
use OCP\Files\Node;

/**
 * Class MoveToTrashEvent
 *
 * Event to allow other apps to disable the trash bin for specific files
 *
 * @package OCA\Files_Trashbin\Events
 */
class MoveToTrashEvent extends Event {

	/** @var bool */
	private $moveToTrashBin;

	/** @var Node */
	private $node;

	public function __construct(Node $node) {
		$this->moveToTrashBin = true;
		$this->node = $node;
	}

	/**
	 * get Node which will be deleted
	 *
	 * @return Node
	 */
	public function getNode() {
		return $this->node;
	}

	/**
	 * disable trash bin for this operation
	 */
	public function disableTrashBin() {
		$this->moveToTrashBin = false;
	}

	/**
	 * should the file be moved to the trash bin?
	 *
	 * @return bool
	 */
	public function shouldMoveToTrashBin() {
		return $this->moveToTrashBin;
	}
}
