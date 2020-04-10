/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
import Vuex from 'vuex'
import store from './store'
import Settings from './components/Workflow'
import ShippedChecks from './components/Checks'

/**
 * A plugin for displaying a custom value field for checks
 *
 * @typedef {Object} CheckPlugin
 * @property {string} class - The PHP class name of the check
 * @property {Comparison[]} operators - A list of possible comparison operations running on the check
 * @property {Vue} component - A vue component to handle the rendering of options
 *  The component should handle the v-model directive properly,
 *  so it needs a value property to receive data and emit an input
 *  event once the data has changed
 * @property {callable} placeholder - Return a placeholder of no custom component is used
 * @property {callable} validate - validate a check if no custom component is used
 **/

/**
 * A plugin for extending the admin page repesentation of a operator
 *
 * @typedef {Object} OperatorPlugin
 * @property {string} id - The PHP class name of the check
 * @property {string} operation - Default value for the operation field
 * @property {string} color - Custom color code to be applied for the operator selector
 * @property {Vue} component - A vue component to handle the rendering of options
 *  The component should handle the v-model directive properly,
 *  so it needs a value property to receive data and emit an input
 *  event once the data has changed
 */

/**
 * @typedef {Object} Comparison
 * @property {string} operator - value the comparison should have, e.g. !less, greater
 * @property {string} name - Translated readable text, e.g. less or equals
 **/

/**
 * Public javascript api for apps to register custom plugins
 */
window.OCA.WorkflowEngine = Object.assign({}, OCA.WorkflowEngine, {

	/**
	 *
	 * @param {CheckPlugin} Plugin the plugin to register
	 */
	registerCheck: function(Plugin) {
		store.commit('addPluginCheck', Plugin)
	},
	/**
	 *
	 * @param {OperatorPlugin} Plugin the plugin to register
	 */
	registerOperator: function(Plugin) {
		store.commit('addPluginOperator', Plugin)
	},
})

// Register shipped checks
ShippedChecks.forEach((checkPlugin) => window.OCA.WorkflowEngine.registerCheck(checkPlugin))

Vue.use(Vuex)
Vue.prototype.t = t

const View = Vue.extend(Settings)
const workflowengine = new View({
	store,
})
workflowengine.$mount('#workflowengine')
