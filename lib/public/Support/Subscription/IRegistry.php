<?php

declare(strict_types=1);

/**
 *
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP\Support\Subscription;

use OCP\Support\Subscription\Exception\AlreadyRegisteredException;

/**
 * @since 17.0.0
 */
interface IRegistry {

	/**
	 * Register a subscription instance. In case it is called multiple times the
	 * first one is used.
	 *
	 * @param ISubscription $subscription
	 * @throws AlreadyRegisteredException
	 *
	 * @since 17.0.0
	 */
	public function register(ISubscription $subscription): void;

	/**
	 * Fetches the list of app IDs that are supported by the subscription
	 *
	 * @since 17.0.0
	 */
	public function delegateGetSupportedApps(): array;

	/**
	 * Indicates if a valid subscription is available
	 *
	 * @since 17.0.0
	 */
	public function delegateHasValidSubscription(): bool;

	/**
	 * Indicates if the subscription has extended support
	 *
	 * @since 17.0.0
	 */
	public function delegateHasExtendedSupport(): bool;
}
