<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCP\Authentication\TwoFactorAuth;

use OCP\EventDispatcher\Event;

/**
 * @since 20.0.0
 */
final class TwoFactorProviderDisabled extends Event {

	/** @var string */
	private $providerId;

	/**
	 * @since 20.0.0
	 */
	public function __construct(string $providerId) {
		parent::__construct();
		$this->providerId = $providerId;
	}

	/**
	 * @since 20.0.0
	 */
	public function getProviderId(): string {
		return $this->providerId;
	}
}
