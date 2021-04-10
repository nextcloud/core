/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/* eslint-disable camelcase */

const path = require('path')
const { merge } = require('webpack-merge')

const ESLintPlugin = require('eslint-webpack-plugin')
const StyleLintPlugin = require('stylelint-webpack-plugin')
const BabelLoaderExcludeNodeModulesExcept = require('babel-loader-exclude-node-modules-except')

const webpackConfig = require('@nextcloud/webpack-vue-config')
const {
	RULE_ASSETS,
	RULE_CSS,
	RULE_JS,
	RULE_SCSS,
	RULE_VUE,
} = require('@nextcloud/webpack-vue-config/rules')

const accessibility = require('./apps/accessibility/webpack')
const comments = require('./apps/comments/webpack')
const core = require('./core/webpack')
const dashboard = require('./apps/dashboard/webpack')
const files = require('./apps/files/webpack')
const files_sharing = require('./apps/files_sharing/webpack')
const files_trashbin = require('./apps/files_trashbin/webpack')
const files_versions = require('./apps/files_versions/webpack')
const oauth2 = require('./apps/oauth2/webpack')
const settings = require('./apps/settings/webpack')
const systemtags = require('./apps/systemtags/webpack')
const user_status = require('./apps/user_status/webpack')
const weather_status = require('./apps/weather_status/webpack')
const twofactor_backupscodes = require('./apps/twofactor_backupcodes/webpack')
const updatenotification = require('./apps/updatenotification/webpack')
const workflowengine = require('./apps/workflowengine/webpack')

const modules = {
	accessibility,
	comments,
	core,
	dashboard,
	files,
	files_sharing,
	files_trashbin,
	files_versions,
	oauth2,
	settings,
	systemtags,
	user_status,
	weather_status,
	twofactor_backupscodes,
	updatenotification,
	workflowengine,
}

const modulesToBuild = () => {
	const MODULE = process.env.MODULE
	if (MODULE) {
		if (!modules[MODULE]) {
			throw new Error(`No module "${MODULE}" found`)
		}
		return [modules[MODULE]]
	}
	return Object.values(modules)
}

// Exclude node modules that doesn't require transpiling
RULE_JS.exclude = BabelLoaderExcludeNodeModulesExcept([
	'@nextcloud/dialogs',
	'@nextcloud/event-bus',
	'davclient.js',
	'nextcloud-vue-collections',
	'p-finally',
	'p-limit',
	'p-locate',
	'p-queue',
	'p-timeout',
	'p-try',
	'semver',
	'striptags',
	'toastify-js',
	'v-tooltip',
])
RULE_VUE.exclude = BabelLoaderExcludeNodeModulesExcept([
	'vue-material-design-icons',
])

// Override default rules with our modified ones
webpackConfig.entry = {}
webpackConfig.module.rules = [
	RULE_CSS,
	RULE_SCSS,
	RULE_VUE,
	RULE_JS,
	RULE_ASSETS,
]

// Change path of default linting plugins lookups
const indexES = webpackConfig.plugins.findIndex(plugin => plugin.constructor === ESLintPlugin)
const indexSL = webpackConfig.plugins.findIndex(plugin => plugin.constructor === StyleLintPlugin)
webpackConfig.plugins[indexES] = new ESLintPlugin({
	files: [
		'apps/*/src',
		'core/src',
	],
	extensions: ['js', 'vue'],
})
webpackConfig.plugins[indexSL] = new StyleLintPlugin({
	files: [
		'apps/*/src/**/*.{css,scss,vue}',
		'core/src/**/*.{css,scss,vue}',
	],
})

// Extend default config
module.exports = []
	.concat(
		...modulesToBuild()
	)
	.map(config => merge(webpackConfig, {
		module: {
			rules: [
				{
					test: /\.handlebars/,
					loader: 'handlebars-loader',
				},

			],
		},
		resolve: {
			alias: {
				OC: path.resolve(__dirname, './core/src/OC'),
				OCA: path.resolve(__dirname, './core/src/OCA'),
				// make sure to use the handlebar runtime when importing
				handlebars: 'handlebars/runtime',
			},
		},
	}, config))
