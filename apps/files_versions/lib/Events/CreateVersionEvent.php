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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Files_Versions\Events;

use OCP\EventDispatcher\Event;
use OCP\Files\Node;

/**
 * Class CreateVersionEvent
 *
 * Event to allow other apps to disable versions for specific files
 *
 * @package OCA\Files_Versions
 */
class CreateVersionEvent extends Event {


	/** @var bool */
	private $createVersion;

	/** @var Node */
	private $node;

	/**
	 * CreateVersionEvent constructor.
	 *
	 * @param Node $node
	 */
	public function __construct(Node $node) {
		$this->createVersion = true;
		$this->node = $node;
	}

	/**
	 * get Node of the file which should be versioned
	 *
	 * @return Node
	 */
	public function getNode(): Node {
		return $this->node;
	}

	/**
	 * disable versions for this file
	 */
	public function disableVersions(): void {
		$this->createVersion = false;
	}

	/**
	 * should a version be created for this file?
	 *
	 * @return bool
	 */
	public function shouldCreateVersion(): bool {
		return $this->createVersion;
	}
}
