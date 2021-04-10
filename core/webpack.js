/**
 * @copyright Copyright (c) 2016 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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
const webpack = require('webpack')

module.exports = [
	{
		entry: {
			files_client: path.join(__dirname, 'src/files/client.js'),
			files_fileinfo: path.join(__dirname, 'src/files/fileinfo.js'),
			files_iedavclient: path.join(__dirname, 'src/files/iedavclient.js'),
			install: path.join(__dirname, 'src/install.js'),
			login: path.join(__dirname, 'src/login.js'),
			main: path.join(__dirname, 'src/main.js'),
			maintenance: path.join(__dirname, 'src/maintenance.js'),
			recommendedapps: path.join(__dirname, 'src/recommendedapps.js'),
			'unified-search': path.join(__dirname, 'src/unified-search.js'),
		},
		output: {
			filename: '[name].js',
			path: path.resolve(__dirname, 'js/dist'),
		},
		module: {
			rules: [
				{
					test: /davclient/,
					loader: 'exports-loader',
					options: {
						type: 'commonjs',
						exports: 'dav',
					},
				},
			],
		},
		plugins: [
			new webpack.ProvidePlugin({
				_: 'underscore',
				$: 'jquery',
				jQuery: 'jquery',
			}),
		],
	},
	{
		entry: {
			systemtags: path.resolve(__dirname, 'src/systemtags/merged-systemtags.js'),
		},
		output: {
			filename: '[name].js',
			path: path.resolve(__dirname, 'js/dist'),
		},
	},
]
