/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

import Vue from 'vue'
import Root from './components/UpdateNotification'

Vue.mixin({
	methods: {
		t: function(app, text, vars, count, options) {
			return OC.L10N.translate(app, text, vars, count, options)
		},
		n: function(app, textSingular, textPlural, count, vars, options) {
			return OC.L10N.translatePlural(app, textSingular, textPlural, count, vars, options)
		},
	},
})

// eslint-disable-next-line no-new
new Vue({
	el: '#updatenotification',
	render: h => h(Root),
})
