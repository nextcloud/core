const path = require('path')

module.exports = {
	entry: path.join(__dirname, 'src', 'systemtags.js'),
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: 'systemtags.js',
		jsonpFunction: 'webpackJsonpSystemtags'
	}
}
