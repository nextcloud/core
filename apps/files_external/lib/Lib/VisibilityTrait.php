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
 * Trait to implement visibility mechanics for a configuration class
 *
 * The standard visibility defines which users/groups can use or see the
 * object. The allowed visibility defines the maximum visibility allowed to be
 * set on the object. The standard visibility is often set dynamically by
 * stored configuration parameters that can be modified by the administrator,
 * while the allowed visibility is set directly by the object and cannot be
 * modified by the administrator.
 */
trait VisibilityTrait {

	/** @var int visibility */
	protected $visibility = BackendService::VISIBILITY_DEFAULT;

	/** @var int allowed visibilities */
	protected $allowedVisibility = BackendService::VISIBILITY_DEFAULT;

	/**
	 * @return int
	 */
	public function getVisibility() {
		return $this->visibility;
	}

	/**
	 * Check if the backend is visible for a user type
	 *
	 * @param int $visibility
	 * @return bool
	 */
	public function isVisibleFor($visibility) {
		if ($this->visibility & $visibility) {
			return true;
		}
		return false;
	}

	/**
	 * @param int $visibility
	 * @return self
	 */
	public function setVisibility($visibility) {
		$this->visibility = $visibility;
		$this->allowedVisibility |= $visibility;
		return $this;
	}

	/**
	 * @param int $visibility
	 * @return self
	 */
	public function addVisibility($visibility) {
		return $this->setVisibility($this->visibility | $visibility);
	}

	/**
	 * @param int $visibility
	 * @return self
	 */
	public function removeVisibility($visibility) {
		return $this->setVisibility($this->visibility & ~$visibility);
	}

	/**
	 * @return int
	 */
	public function getAllowedVisibility() {
		return $this->allowedVisibility;
	}

	/**
	 * Check if the backend is allowed to be visible for a user type
	 *
	 * @param int $allowedVisibility
	 * @return bool
	 */
	public function isAllowedVisibleFor($allowedVisibility) {
		if ($this->allowedVisibility & $allowedVisibility) {
			return true;
		}
		return false;
	}

	/**
	 * @param int $allowedVisibility
	 * @return self
	 */
	public function setAllowedVisibility($allowedVisibility) {
		$this->allowedVisibility = $allowedVisibility;
		$this->visibility &= $allowedVisibility;
		return $this;
	}

	/**
	 * @param int $allowedVisibility
	 * @return self
	 */
	public function addAllowedVisibility($allowedVisibility) {
		return $this->setAllowedVisibility($this->allowedVisibility | $allowedVisibility);
	}

	/**
	 * @param int $allowedVisibility
	 * @return self
	 */
	public function removeAllowedVisibility($allowedVisibility) {
		return $this->setAllowedVisibility($this->allowedVisibility & ~$allowedVisibility);
	}

}
