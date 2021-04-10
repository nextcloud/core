/**
 * @copyright Copyright (c) 2016 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

const path = require('path')

module.exports = {
	entry: {
		additionalScripts: path.join(__dirname, 'src', 'additionalScripts.js'),
		collaboration: path.join(__dirname, 'src', 'collaborationresourceshandler.js'),
		files_sharing_tab: path.join(__dirname, 'src', 'files_sharing_tab.js'),
		files_sharing: path.join(__dirname, 'src', 'files_sharing.js'),
		main: path.join(__dirname, 'src', 'index.js'),
		'personal-settings': path.join(__dirname, 'src', 'personal-settings.js'),
	},
	output: {
		path: path.resolve(__dirname, './js/dist/'),
		publicPath: '/js/',
		filename: '[name].js',
		chunkFilename: 'files_sharing.[id].js?v=[chunkhash]',
	},
}
