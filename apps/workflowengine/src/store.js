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
import axios from '@nextcloud/axios'
import { getApiUrl } from './helpers/api'
import confirmPassword from '@nextcloud/password-confirmation'
import { loadState } from '@nextcloud/initial-state'

Vue.use(Vuex)

const store = new Vuex.Store({
	state: {
		rules: [],
		scope: loadState('workflowengine', 'scope'),
		appstoreEnabled: loadState('workflowengine', 'appstoreenabled'),
		operations: loadState('workflowengine', 'operators'),

		plugins: Vue.observable({
			checks: {},
			operators: {},
		}),

		entities: loadState('workflowengine', 'entities'),
		events: loadState('workflowengine', 'entities')
			.map((entity) => entity.events.map(event => {
				return {
					id: `${entity.id}::${event.eventName}`,
					entity,
					...event,
				}
			})).flat(),
		checks: loadState('workflowengine', 'checks'),
	},
	mutations: {
		addRule(state, rule) {
			state.rules.push({ ...rule, valid: true })
		},
		updateRule(state, rule) {
			const index = state.rules.findIndex((item) => rule.id === item.id)
			const newRule = Object.assign({}, rule)
			Vue.set(state.rules, index, newRule)
		},
		removeRule(state, rule) {
			const index = state.rules.findIndex((item) => rule.id === item.id)
			state.rules.splice(index, 1)
		},
		addPluginCheck(state, plugin) {
			Vue.set(state.plugins.checks, plugin.class, plugin)
		},
		addPluginOperator(state, plugin) {
			plugin = Object.assign(
				{ color: 'var(--color-primary-element)' },
				plugin, state.operations[plugin.id] || {})
			if (typeof state.operations[plugin.id] !== 'undefined') {
				Vue.set(state.operations, plugin.id, plugin)
			}
		},
	},
	actions: {
		async fetchRules(context) {
			const { data } = await axios.get(getApiUrl(''))
			Object.values(data.ocs.data).flat().forEach((rule) => {
				context.commit('addRule', rule)
			})
		},
		createNewRule(context, rule) {
			let entity = null
			let events = []
			if (rule.isComplex === false && rule.fixedEntity === '') {
				entity = context.state.entities.find((item) => rule.entities && rule.entities[0] === item.id)
				entity = entity || Object.values(context.state.entities)[0]
				events = [entity.events[0].eventName]
			}

			context.commit('addRule', {
				id: -(new Date().getTime()),
				class: rule.id,
				entity: entity ? entity.id : rule.fixedEntity,
				events,
				name: '', // unused in the new ui, there for legacy reasons
				checks: [
					{ class: null, operator: null, value: '' },
				],
				operation: rule.operation || '',
			})
		},
		updateRule(context, rule) {
			context.commit('updateRule', {
				...rule,
				events: typeof rule.events === 'string' ? JSON.parse(rule.events) : rule.events,
			})
		},
		removeRule(context, rule) {
			context.commit('removeRule', rule)
		},
		async pushUpdateRule(context, rule) {
			if (context.state.scope === 0) {
				await confirmPassword()
			}
			let result
			if (rule.id < 0) {
				result = await axios.post(getApiUrl(''), rule)
			} else {
				result = await axios.put(getApiUrl(`/${rule.id}`), rule)
			}
			Vue.set(rule, 'id', result.data.ocs.data.id)
			context.commit('updateRule', rule)
		},
		async deleteRule(context, rule) {
			await confirmPassword()
			await axios.delete(getApiUrl(`/${rule.id}`))
			context.commit('removeRule', rule)
		},
		setValid(context, { rule, valid }) {
			rule.valid = valid
			context.commit('updateRule', rule)
		},
	},
	getters: {
		getRules(state) {
			return state.rules.filter((rule) => typeof state.operations[rule.class] !== 'undefined').sort((rule1, rule2) => {
				return rule1.id - rule2.id || rule2.class - rule1.class
			})
		},
		getOperationForRule(state) {
			return (rule) => state.operations[rule.class]
		},
		getEntityForOperation(state) {
			return (operation) => state.entities.find((entity) => operation.fixedEntity === entity.id)
		},
		getEventsForOperation(state) {
			return (operation) => state.events
		},

		/**
		 * Return all available checker plugins for a given entity class
		 * @param {Object} state the store state
		 * @param {Object} entity the entity class
		 * @returns {Array} the available plugins
		 */
		getChecksForEntity(state) {
			return (entity) => {
				return Object.values(state.checks)
					.filter((check) => check.supportedEntities.indexOf(entity) > -1 || check.supportedEntities.length === 0)
					.map((check) => state.plugins.checks[check.id])
					.reduce((obj, item) => {
						obj[item.class] = item
						return obj
					}, {})
			}
		},
	},
})

export default store
