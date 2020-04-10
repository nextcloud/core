<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCP\Notification;

/**
 * Interface INotifier classes should implement if they want to process notifications
 * that are dismissed by the user.
 *
 * This can be useful if dismissing the notification will leave it in an incomplete
 * state. The handler can chose to for example do some default action.
 *
 * @since 18.0.0
 */
interface IDismissableNotifier extends INotifier {

	/**
	 * @param INotification $notification
	 * @throws \InvalidArgumentException In case the handler can't handle the notification
	 *
	 * @since 18.0.0
	 */
	public function dismissNotification(INotification $notification): void;
}
