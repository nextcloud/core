import { loadState } from '@nextcloud/initial-state'
import Vue from 'vue'

import PersonalSettings from './views/PersonalSettings'
import store from './store'

Vue.prototype.t = t

const initialState = loadState('twofactor_backupcodes', 'state')
store.replaceState(initialState)

const View = Vue.extend(PersonalSettings)
new View({
	store,
}).$mount('#twofactor-backupcodes-settings')
