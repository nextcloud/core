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

import $ from 'jquery'
import { emit } from '@nextcloud/event-bus'

import { generateUrl } from './OC/routing'
import OC from './OC'
import { setToken as setRequestToken } from './OC/requesttoken'

/**
 * session heartbeat (defaults to enabled)
 * @returns {boolean}
 */
const keepSessionAlive = () => {
	return OC.config.session_keepalive === undefined
		|| !!OC.config.session_keepalive
}

/**
 * get interval in seconds
 * @returns {Number}
 */
const getInterval = () => {
	let interval = NaN
	if (OC.config.session_lifetime) {
		interval = Math.floor(OC.config.session_lifetime / 2)
	}

	// minimum one minute, max 24 hours, default 15 minutes
	return Math.min(
		24 * 3600,
		Math.max(
			60,
			isNaN(interval) ? 900 : interval
		)
	)
}

const getToken = async() => {
	const url = generateUrl('/csrftoken')

	// Not using Axios here as Axios is not stubbable with the sinon fake server
	// see https://stackoverflow.com/questions/41516044/sinon-mocha-test-with-async-ajax-calls-didnt-return-promises
	// see js/tests/specs/coreSpec.js for the tests
	const resp = await $.get(url)

	return resp.token
}

const poll = async() => {
	try {
		const token = await getToken()
		setRequestToken(token)
	} catch (e) {
		console.error('session heartbeat failed', e)
	}
}

const startPolling = () => {
	const interval = setInterval(poll, getInterval() * 1000)

	console.info('session heartbeat polling started')

	return interval
}

/**
 * Calls the server periodically to ensure that session and CSRF
 * token doesn't expire
 */
export const initSessionHeartBeat = () => {
	if (!keepSessionAlive()) {
		console.info('session heartbeat disabled')
		return
	}
	let interval = startPolling()

	window.addEventListener('online', async() => {
		console.info('browser is online again, resuming heartbeat')
		interval = startPolling()
		try {
			await poll()
			console.info('session token successfully updated after resuming network')

			// Let apps know we're online and requests will have the new token
			emit('networkOnline', {
				success: true,
			})
		} catch (e) {
			console.error('could not update session token after resuming network', e)

			// Let apps know we're online but requests might have an outdated token
			emit('networkOnline', {
				success: false,
			})
		}
	})
	window.addEventListener('offline', () => {
		console.info('browser is offline, stopping heartbeat')

		// Let apps know we're offline
		emit('networkOffline', {})

		clearInterval(interval)
		console.info('session heartbeat polling stopped')
	})
}
