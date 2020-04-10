/*
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

import { loadState } from '@nextcloud/initial-state'
import queryString from 'query-string'
import Vue from 'vue'

// eslint-disable-next-line no-unused-vars
import OC from './OC/index' // TODO: Not needed but L10n breaks if removed
import LoginView from './views/Login.vue'
import Nextcloud from './mixins/Nextcloud'

const query = queryString.parse(location.search)
if (query.clear === '1') {
	try {
		window.localStorage.clear()
		window.sessionStorage.clear()
		console.debug('Browser storage cleared')
	} catch (e) {
		console.error('Could not clear browser storage', e)
	}
}

Vue.mixin(Nextcloud)

const fromStateOr = (key, orValue) => {
	try {
		return loadState('core', key)
	} catch (e) {
		return orValue
	}
}

const View = Vue.extend(LoginView)
new View({
	propsData: {
		errors: fromStateOr('loginErrors', []),
		messages: fromStateOr('loginMessages', []),
		redirectUrl: fromStateOr('loginRedirectUrl', undefined),
		username: fromStateOr('loginUsername', ''),
		throttleDelay: fromStateOr('loginThrottleDelay', 0),
		invertedColors: OCA.Theming && OCA.Theming.inverted,
		canResetPassword: fromStateOr('loginCanResetPassword', false),
		resetPasswordLink: fromStateOr('loginResetPasswordLink', ''),
		autoCompleteAllowed: fromStateOr('loginAutocomplete', true),
		resetPasswordTarget: fromStateOr('resetPasswordTarget', ''),
		resetPasswordUser: fromStateOr('resetPasswordUser', ''),
		directLogin: query.direct === '1',
		hasPasswordless: fromStateOr('webauthn-available', false),
		isHttps: window.location.protocol === 'https:',
		hasPublicKeyCredential: typeof (window.PublicKeyCredential) !== 'undefined',
	},
}).$mount('#login')
