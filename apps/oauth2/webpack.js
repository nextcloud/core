const path = require('path')

module.exports = {
	entry: path.join(__dirname, 'src', 'main.js'),
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js',
		filename: 'oauth2.js',
		jsonpFunction: 'webpackJsonpOauth'
	}
}
