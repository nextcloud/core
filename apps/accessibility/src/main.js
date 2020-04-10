import Vue from 'vue'
import { loadState } from '@nextcloud/initial-state'
import App from './Accessibility.vue'

// bind to window
Vue.prototype.OC = OC
Vue.prototype.t = t

const availableConfig = loadState('accessibility', 'available-config')
const userConfig = loadState('accessibility', 'user-config')

const View = Vue.extend(App)
const accessibility = new View({
	propsData: {
		availableConfig,
		userConfig,
	},
})
accessibility.$mount('#accessibility')
