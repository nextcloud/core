/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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

import { getLoggerBuilder } from '@nextcloud/logger'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import CommentsApp from '../views/Comments'
import Vue from 'vue'

const logger = getLoggerBuilder()
	.setApp('comments')
	.detectUser()
	.build()

// Add translates functions
Vue.mixin({
	data() {
		return {
			logger,
		}
	},
	methods: {
		t,
		n,
	},
})

export default class CommentInstance {

	/**
	 * Initialize a new Comments instance for the desired type
	 *
	 * @param {string} commentsType the comments endpoint type
	 * @param  {Object} options the vue options (propsData, parent, el...)
	 */
	constructor(commentsType = 'files', options) {
		// Add comments type as a global mixin
		Vue.mixin({
			data() {
				return {
					commentsType,
				}
			},
		})

		// Init Comments component
		const View = Vue.extend(CommentsApp)
		return new View(options)
	}

}
