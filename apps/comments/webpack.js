const path = require('path')

module.exports = {
	entry: path.join(__dirname, 'src', 'comments.js'),
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: 'comments.js',
		jsonpFunction: 'webpackJsonpComments'
	},
	externals: {
		jquery: 'jQuery'
	}
}
