const path = require('path');

module.exports = {
	entry: {
		'sidebar': path.join(__dirname, 'src', 'sidebar.js'),
		'files-app-settings': path.join(__dirname, 'src', 'files-app-settings.js'),
		'personal-settings': path.join(__dirname, 'src', 'main-personal-settings.js'),
	},
	output: {
		path: path.resolve(__dirname, './js/dist/'),
		publicPath: '/js/',
		filename: '[name].js',
		chunkFilename: 'files.[id].js'
	}
}
