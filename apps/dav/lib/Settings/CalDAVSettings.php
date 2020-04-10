<?php
/**
 * @copyright 2017, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\DAV\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class CalDAVSettings implements ISettings {

	/** @var IConfig */
	private $config;

	/**
	 * CalDAVSettings constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$parameters = [
			'send_invitations' => $this->config->getAppValue('dav', 'sendInvitations', 'yes'),
			'generate_birthday_calendar' => $this->config->getAppValue('dav', 'generateBirthdayCalendar', 'yes'),
			'send_reminders_notifications' => $this->config->getAppValue('dav', 'sendEventReminders', 'yes'),
			'send_reminders_notifications_push' => $this->config->getAppValue('dav', 'sendEventRemindersPush', 'no'),
		];

		return new TemplateResponse('dav', 'settings-admin-caldav', $parameters);
	}

	/**
	 * @return string
	 */
	public function getSection() {
		return 'groupware';
	}

	/**
	 * @return int
	 */
	public function getPriority() {
		return 10;
	}
}
