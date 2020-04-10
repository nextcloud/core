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
import Vue from 'vue'
import VueClipboard from 'vue-clipboard2'
import VTooltip from 'v-tooltip'

import AuthTokenSection from './components/AuthTokenSection'

// eslint-disable-next-line camelcase
__webpack_nonce__ = btoa(OC.requestToken)

Vue.use(VueClipboard)
Vue.use(VTooltip, { defaultHtml: false })
Vue.prototype.t = t

const View = Vue.extend(AuthTokenSection)
new View({
	propsData: {
		tokens: loadState('settings', 'app_tokens'),
		canCreateToken: loadState('settings', 'can_create_app_token'),
	},
}).$mount('#security-authtokens')
