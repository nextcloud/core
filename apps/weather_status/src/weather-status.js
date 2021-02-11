import Vue from 'vue'
import { getRequestToken } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import App from './App'

// eslint-disable-next-line camelcase
__webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// eslint-disable-next-line
__webpack_public_path__ = generateUrl('/apps/weather_status/js/')

Vue.prototype.t = t

document.addEventListener('DOMContentLoaded', function() {
	if (!OCA.Dashboard) {
		return
	}

	OCA.Dashboard.registerStatus('weather', (el) => {
		const Dashboard = Vue.extend(App)
		return new Dashboard({
			propsData: {
				inline: true,
			},
		}).$mount(el)
	})
})
