<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace Test\Notification;

use OCP\Notification\IApp;
use OCP\Notification\INotification;

class DummyApp implements IApp {

	/**
	 * @param INotification $notification
	 * @throws \InvalidArgumentException When the notification is not valid
	 * @since 9.0.0
	 */
	public function notify(INotification $notification): void {
		// TODO: Implement notify() method.
	}

	/**
	 * @param INotification $notification
	 * @since 9.0.0
	 */
	public function markProcessed(INotification $notification): void {
		// TODO: Implement markProcessed() method.
	}

	/**
	 * @param INotification $notification
	 * @return int
	 * @since 9.0.0
	 */
	public function getCount(INotification $notification): int {
		// TODO: Implement getCount() method.
	}
}
