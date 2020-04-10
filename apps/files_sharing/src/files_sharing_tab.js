/**
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import SharingTab from './views/SharingTab'
import ShareSearch from './services/ShareSearch'
import ExternalLinkActions from './services/ExternalLinkActions'

import TabSections from './services/TabSections'

// Init Sharing Tab Service
if (!window.OCA.Sharing) {
	window.OCA.Sharing = {}
}
Object.assign(window.OCA.Sharing, { ShareSearch: new ShareSearch() })
Object.assign(window.OCA.Sharing, { ExternalLinkActions: new ExternalLinkActions() })
Object.assign(window.OCA.Sharing, { ShareTabSections: new TabSections() })

window.addEventListener('DOMContentLoaded', () => {
	if (OCA.Files && OCA.Files.Sidebar) {
		OCA.Files.Sidebar.registerTab(new OCA.Files.Sidebar.Tab('sharing', SharingTab))
	}
})
