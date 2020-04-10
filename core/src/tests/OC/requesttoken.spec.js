/*
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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

import {JSDOM} from 'jsdom'
import {subscribe, unsubscribe} from '@nextcloud/event-bus'

import {manageToken, setToken} from '../../OC/requesttoken'

describe('request token', () => {

	let dom
	let emit
	let manager
	const token = 'abc123'

	beforeEach(() => {
		dom = new JSDOM()
		emit = sinon.spy()
		const head = dom.window.document.getElementsByTagName('head')[0]
		head.setAttribute('data-requesttoken', token)

		manager = manageToken(dom.window.document, emit)
	})

	it('reads the token from the document', () => {
		expect(manager.getToken()).to.equal('abc123')
	})

	it('remembers the updated token', () => {
		manager.setToken('bca321')

		expect(manager.getToken()).to.equal('bca321')
	})

	describe('@nextcloud/auth integration', () => {
		let listener

		beforeEach(() => {
			listener = sinon.spy()

			subscribe('csrf-token-update', listener)
		})

		afterEach(() => {
			unsubscribe('csrf-token-update', listener)
		})

		it('fires off an event for @nextcloud/auth', () => {
			setToken('123')

			expect(listener).to.have.been.calledWith({token: '123'})
		})
	})

})
