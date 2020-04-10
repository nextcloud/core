const path = require('path')

module.exports = {
	entry: path.join(__dirname, 'src', 'init.js'),
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: 'updatenotification.js',
		jsonpFunction: 'webpackJsonpUpdatenotification'
	}
}
