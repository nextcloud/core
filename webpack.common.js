/* eslint-disable camelcase */
const { merge } = require('webpack-merge')
const { VueLoaderPlugin } = require('vue-loader')
const BabelLoaderExcludeNodeModulesExcept = require('babel-loader-exclude-node-modules-except')
const path = require('path')

const accessibility = require('./apps/accessibility/webpack')
const comments = require('./apps/comments/webpack')
const core = require('./core/webpack')
const dashboard = require('./apps/dashboard/webpack')
const dav = require('./apps/dav/webpack')
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
	dav,
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

module.exports = []
	.concat(
		...modulesToBuild()
	)
	.map(config => merge({
		module: {
			rules: [
				{
					test: /\.css$/,
					use: ['style-loader', 'css-loader'],
				},
				{
					test: /\.scss$/,
					use: ['style-loader', 'css-loader', 'sass-loader'],
				},
				{
					test: /\.(js|vue)$/,
					loader: 'eslint-loader',
					// no checks against vendors, modules or handlebar compiled files
					exclude: /node_modules|vendor|templates\.js/,
					enforce: 'pre',
				},
				{
					test: /\.vue$/,
					loader: 'vue-loader',
					exclude: BabelLoaderExcludeNodeModulesExcept([
						'vue-material-design-icons',
					]),
				},
				{
					test: /\.js$/,
					loader: 'babel-loader',
					// automatically detect necessary packages to
					// transpile in the node_modules folder
					exclude: BabelLoaderExcludeNodeModulesExcept([
						'@nextcloud/dialogs',
						'@nextcloud/event-bus',
						'@nextcloud/vue-dashboard',
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
					]),
				},
				{
					test: /\.(png|jpg|gif)$/,
					loader: 'url-loader',
					options: {
						name: '[name].[ext]?[hash]',
						limit: 8192,
					},
				},
				{
					test: /\.handlebars/,
					loader: 'handlebars-loader',
					query: {
						extensions: '.handlebars',
					},
				},

			],
		},
		plugins: [
			new VueLoaderPlugin(),
		],
		resolve: {
			alias: {
				OC: path.resolve(__dirname, './core/src/OC'),
				OCA: path.resolve(__dirname, './core/src/OCA'),
				// make sure to use the handlebar runtime when importing
				handlebars: 'handlebars/runtime',
			},
			extensions: ['*', '.js', '.vue'],
			symlinks: false,
		},
	}, config))
