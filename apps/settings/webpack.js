const path = require('path')

module.exports = {
	entry: {
		'settings-apps-users-management': path.join(__dirname, 'src', 'main-apps-users-management'),
		'settings-admin-security': path.join(__dirname, 'src', 'main-admin-security'),
		'settings-personal-security': path.join(__dirname, 'src', 'main-personal-security'),
		'settings-personal-webauthn': path.join(__dirname, 'src', 'main-personal-webauth')
	},
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: 'vue-[name].js?v=[chunkhash]',
		jsonpFunction: 'webpackJsonpSettings'
	},
	optimization: {
		splitChunks: {
			automaticNameDelimiter: '-',
		}
	}
}
