/**
 * @copyright Copyright (c) 2020 Azul <azul@riseup.net>
 *
 * @author Azul <azul@riseup.net>
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

import { encodePath } from '@nextcloud/paths'

export default function(name, context) {
	// replace potential leading double slashes
	const path = `${context.dir}/${name}`.replace(/^\/\//, '/')
	const oldQuery = location.search.replace(/^\?/, '')
	const onClose = () => OC.Util.History.pushState(oldQuery)
	if (!context.fileInfoModel && context.fileList) {
		context.fileInfoModel = context.fileList.getModelForFile(name)
	}
	if (context.fileInfoModel) {
		pushToHistory({ fileid: context.fileInfoModel.get('id') })
	}
	OCA.Viewer.open({ path, onPrev: pushToHistory, onNext: pushToHistory, onClose })
}

function pushToHistory({ fileid }) {
	const params = OC.Util.History.parseUrlQuery()
	const dir = params.dir
	delete params.dir
	delete params.fileid
	params.openfile = fileid
	const query = 'dir=' + encodePath(dir) + '&' + OC.buildQueryString(params)
	OC.Util.History.pushState(query)
}
