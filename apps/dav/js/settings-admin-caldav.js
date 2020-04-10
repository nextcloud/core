/**
 * @copyright 2017, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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
"use strict";

$('#caldavSendInvitations').change(function() {
	var val = $(this)[0].checked;

	OCP.AppConfig.setValue('dav', 'sendInvitations', val ? 'yes' : 'no');
});

$('#caldavGenerateBirthdayCalendar').change(function() {
	var val = $(this)[0].checked;

	if (val) {
		$.post(OC.generateUrl('/apps/dav/enableBirthdayCalendar'));
	} else {
		$.post(OC.generateUrl('/apps/dav/disableBirthdayCalendar'));
	}
});

$('#caldavSendRemindersNotifications').change(function() {
	var val = $(this)[0].checked;

	OCP.AppConfig.setValue('dav', 'sendEventReminders', val ? 'yes' : 'no');

	$('#caldavSendRemindersNotificationsPush').prop('disabled', !val)
});

$('#caldavSendRemindersNotificationsPush').change(function() {
	var val = $(this)[0].checked;

	OCP.AppConfig.setValue('dav', 'sendEventRemindersPush', val ? 'yes' : 'no');
});

