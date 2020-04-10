const path = require('path')

module.exports = {
	entry: path.join(__dirname, 'src', 'settings.js'),
	output: {
		path: path.resolve(__dirname, 'js'),
		publicPath: '/js',
		filename: 'settings.js',
		jsonpFunction: 'webpackJsonpTwofactorBackupcodes'
	}
}
