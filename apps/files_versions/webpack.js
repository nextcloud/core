const path = require('path')

module.exports = {
	entry: path.join(__dirname, 'src', 'files_versions.js'),
	output: {
		path: path.resolve(__dirname, 'js'),
		publicPath: '/js/',
		filename: 'files_versions.js',
		jsonpFunction: 'webpackJsonpFilesVersions'
	}
}
