<?php

/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

use Behat\Behat\Context\Context;

class NotificationsContext implements Context, ActorAwareInterface {

	use ActorAware;

	/**
	 * @return Locator
	 */
	public static function notificationsButton() {
		return Locator::forThe()->css("#header .notifications .notifications-button")->
				describedAs("Notifications button in the header");
	}

	/**
	 * @return Locator
	 */
	public static function notificationsContainer() {
		return Locator::forThe()->css("#header .notifications .notification-container")->
				describedAs("Notifications container");
	}

	/**
	 * @return Locator
	 */
	public static function incomingShareNotificationForFile($fileName) {
		return Locator::forThe()->xpath("//div[contains(concat(' ', normalize-space(@class), ' '), ' notification ') and //div[starts-with(normalize-space(), 'You received $fileName as a share by')]]")->
				descendantOf(self::notificationsContainer())->
				describedAs("Notification of incoming share for file $fileName");
	}

	/**
	 * @return Locator
	 */
	public static function actionsInIncomingShareNotificationForFile($fileName) {
		return Locator::forThe()->css(".notification-actions")->
				descendantOf(self::incomingShareNotificationForFile($fileName))->
				describedAs("Actions in notification of incoming share for file $fileName");
	}

	/**
	 * @return Locator
	 */
	public static function actionInIncomingShareNotificationForFile($fileName, $action) {
		return Locator::forThe()->xpath("//button[normalize-space() = '$action']")->
				descendantOf(self::actionsInIncomingShareNotificationForFile($fileName))->
				describedAs("$action button in notification of incoming share for file $fileName");
	}

	/**
	 * @return Locator
	 */
	public static function acceptButtonInIncomingShareNotificationForFile($fileName) {
		return self::actionInIncomingShareNotificationForFile($fileName, 'Accept');
	}

	/**
	 * @Given I accept the share for :fileName in the notifications
	 */
	public function iAcceptTheShareForInTheNotifications($fileName) {
		$this->actor->find(self::notificationsButton(), 10)->click();

		// Notifications are refreshed every 30 seconds, so wait a bit longer.
		// As the waiting is long enough already the find timeout multiplier is
		// capped at 2 when finding notifications.
		$findTimeoutMultiplier = $this->actor->getFindTimeoutMultiplier();
		$this->actor->setFindTimeoutMultiplier(min(2, $findTimeoutMultiplier));
		$this->actor->find(self::acceptButtonInIncomingShareNotificationForFile($fileName), 35)->click();
		$this->actor->setFindTimeoutMultiplier($findTimeoutMultiplier);

		// Hide the notifications again
		$this->actor->find(self::notificationsButton(), 10)->click();
	}

}
