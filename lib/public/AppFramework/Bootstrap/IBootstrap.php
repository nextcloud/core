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

namespace OCP\AppFramework\Bootstrap;

/**
 * @since 20.0.0
 */
interface IBootstrap {

	/**
	 * @param IRegistrationContext $context
	 *
	 * @since 20.0.0
	 */
	public function register(IRegistrationContext $context): void;

	/**
	 * Boot the application
	 *
	 * At this stage you can assume that all services are registered and the DI
	 * container(s) are ready to be queried.
	 *
	 * This is also the state where an optional `appinfo/app.php` was loaded.
	 *
	 * @param IBootContext $context
	 *
	 * @since 20.0.0
	 */
	public function boot(IBootContext $context): void;
}
