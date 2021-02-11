const path = require('path')

module.exports = {
	entry: {
		'settings-apps-users-management': path.join(__dirname, 'src', 'main-apps-users-management'),
		'settings-admin-security': path.join(__dirname, 'src', 'main-admin-security'),
		'settings-personal-security': path.join(__dirname, 'src', 'main-personal-security'),
		'settings-personal-webauthn': path.join(__dirname, 'src', 'main-personal-webauth'),
		'settings-nextcloud-pdf': path.join(__dirname, 'src', 'main-nextcloud-pdf'),
	},
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: 'vue-[name].js?v=[contenthash]',
		chunkFilename: 'vue-[name]-[chunkhash].js?v=[contenthash]',
		jsonpFunction: 'webpackJsonpSettings',
	},
	optimization: {
		splitChunks: {
			automaticNameDelimiter: '-',
		},
	},
}
