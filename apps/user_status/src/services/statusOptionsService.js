/**
 * @copyright Copyright (c) 2020 Georg Ehrke
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import { translate as t } from '@nextcloud/l10n'

/**
 * Returns a list of all user-definable statuses
 *
 * @returns {Object[]}
 */
const getAllStatusOptions = () => {
	return [{
		type: 'online',
		label: t('user_status', 'Online'),
		icon: 'icon-user-status-online',
	}, {
		type: 'away',
		label: t('user_status', 'Away'),
		icon: 'icon-user-status-away',
	}, {
		type: 'dnd',
		label: t('user_status', 'Do not disturb'),
		subline: t('user_status', 'Mute all notifications'),
		icon: 'icon-user-status-dnd',

	}, {
		type: 'invisible',
		label: t('user_status', 'Invisible'),
		subline: t('user_status', 'Appear offline'),
		icon: 'icon-user-status-invisible',
	}]
}

export {
	getAllStatusOptions,
}
