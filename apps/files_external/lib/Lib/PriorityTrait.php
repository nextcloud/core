<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCA\Files_External\Lib;

use OCA\Files_External\Service\BackendService;

/**
 * Trait to implement priority mechanics for a configuration class
 */
trait PriorityTrait {

	/** @var int initial priority */
	protected $priority = BackendService::PRIORITY_DEFAULT;

	/**
	 * @return int
	 */
	public function getPriority() {
		return $this->priority;
	}

	/**
	 * @param int $priority
	 * @return self
	 */
	public function setPriority($priority) {
		$this->priority = $priority;
		return $this;
	}

	/**
	 * @param PriorityTrait $a
	 * @param PriorityTrait $b
	 * @return int
	 */
	public static function priorityCompare(PriorityTrait $a, PriorityTrait $b) {
		return ($a->getPriority() - $b->getPriority());
	}

}
