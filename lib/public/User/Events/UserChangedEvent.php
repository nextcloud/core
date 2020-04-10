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

namespace OCP\User\Events;

use OCP\EventDispatcher\Event;
use OCP\IUser;

/**
 * @since 18.0.0
 */
class UserChangedEvent extends Event {

	/** @var IUser */
	private $user;

	/** @var string */
	private $feature;

	/** @var mixed */
	private $value;

	/** @var mixed */
	private $oldValue;

	/**
	 * @since 18.0.0
	 */
	public function __construct(IUser $user,
								string $feature,
								$value,
								$oldValue = null) {
		parent::__construct();
		$this->user = $user;
		$this->feature = $feature;
		$this->value = $value;
		$this->oldValue = $oldValue;
	}

	/**
	 * @return IUser
	 * @since 18.0.0
	 */
	public function getUser(): IUser {
		return $this->user;
	}

	/**
	 * @return string
	 * @since 18.0.0
	 */
	public function getFeature(): string {
		return $this->feature;
	}

	/**
	 * @return mixed
	 * @since 18.0.0
	 */
	public function getValue() {
		return $this->value;
	}

	/**
	 * @return mixed
	 * @since 18.0.0
	 */
	public function getOldValue() {
		return $this->oldValue;
	}


}
