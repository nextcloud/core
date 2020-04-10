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

import Axios from '@nextcloud/axios'

import OC from './OC/index'

const url = `${OC.getRootPath()}/status.php`

const check = () => {
	console.info('checking the Nextcloud maintenance status')
	Axios.get(url)
		.then(resp => resp.data)
		.then(status => {
			if (status.maintenance === false) {
				console.info('Nextcloud is not in maintenance mode anymore -> reloading')

				window.location.reload()
				return
			}

			console.info('Nextcloud is still in maintenance mode')

			// Wait 20sec before the next request
			setTimeout(check, 20 * 1000)
		})
		.catch(console.error.bind(this))
}

// Off we go!
check()
