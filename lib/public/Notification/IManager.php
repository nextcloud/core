<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCP\Notification;

/**
 * Interface IManager
 *
 * @package OCP\Notification
 * @since 9.0.0
 */
interface IManager extends IApp, INotifier {
	/**
	 * @param string $appClass The service must implement IApp, otherwise a
	 *                          \InvalidArgumentException is thrown later
	 * @since 17.0.0
	 */
	public function registerApp(string $appClass): void;

	/**
	 * @param \Closure $service The service must implement INotifier, otherwise a
	 *                          \InvalidArgumentException is thrown later
	 * @param \Closure $info    An array with the keys 'id' and 'name' containing
	 *                          the app id and the app name
	 * @deprecated 17.0.0 use registerNotifierService instead.
	 * @since 8.2.0 - Parameter $info was added in 9.0.0
	 */
	public function registerNotifier(\Closure $service, \Closure $info);

	/**
	 * @param string $notifierService The service must implement INotifier, otherwise a
	 *                          \InvalidArgumentException is thrown later
	 * @since 17.0.0
	 */
	public function registerNotifierService(string $notifierService): void;

	/**
	 * @return INotifier[]
	 * @since 9.0.0
	 */
	public function getNotifiers(): array;

	/**
	 * @return INotification
	 * @since 9.0.0
	 */
	public function createNotification(): INotification;

	/**
	 * @return bool
	 * @since 9.0.0
	 */
	public function hasNotifiers(): bool;

	/**
	 * @param bool $preparingPushNotification
	 * @since 14.0.0
	 */
	public function setPreparingPushNotification(bool $preparingPushNotification): void;

	/**
	 * @return bool
	 * @since 14.0.0
	 */
	public function isPreparingPushNotification(): bool;

	/**
	 * @since 18.0.0
	 */
	public function dismissNotification(INotification $notification): void;
}
