<?php

declare(strict_types=1);

/**
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Support\Subscription;

class DummySubscription implements \OCP\Support\Subscription\ISubscription {

	/** @var bool */
	private $hasValidSubscription;
	/** @var bool */
	private $hasExtendedSupport;
	/** @var bool */
	private $isHardUserLimitReached;

	/**
	 * DummySubscription constructor.
	 *
	 * @param bool $hasValidSubscription
	 * @param bool $hasExtendedSupport
	 */
	public function __construct(bool $hasValidSubscription, bool $hasExtendedSupport, bool $isHardUserLimitReached) {
		$this->hasValidSubscription = $hasValidSubscription;
		$this->hasExtendedSupport = $hasExtendedSupport;
		$this->isHardUserLimitReached = $isHardUserLimitReached;
	}

	/**
	 * @inheritDoc
	 */
	public function hasValidSubscription(): bool {
		return $this->hasValidSubscription;
	}

	/**
	 * @inheritDoc
	 */
	public function hasExtendedSupport(): bool {
		return $this->hasExtendedSupport;
	}

	public function isHardUserLimitReached(): bool {
		return $this->isHardUserLimitReached;
	}
}
