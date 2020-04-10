/**
 * @copyright Copyright (c) 2019 Gary Kim <gary@garykim.dev>
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author Gary Kim <gary@garykim.dev>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'
import Settings from './services/Settings'
import SettingsView from './views/Settings'
import Setting from './models/Setting'

Vue.prototype.t = t

// Init Files App Settings Service
if (!window.OCA.Files) {
	window.OCA.Files = {}
}
Object.assign(window.OCA.Files, { Settings: new Settings() })
Object.assign(window.OCA.Files.Settings, { Setting })

window.addEventListener('DOMContentLoaded', () => {
	// Init Vue app
	// eslint-disable-next-line
	new Vue({
		el: '#files-app-settings',
		render: h => h(SettingsView),
	})

	const appSettingsHeader = document.getElementById('app-settings-header')
	if (appSettingsHeader) {
		appSettingsHeader.addEventListener('click', e => {
			const opened = e.currentTarget.children[0].classList.contains('opened')
			OCA.Files.Settings.settings.forEach(e => opened ? e.close() : e.open())
		})
	}
})
