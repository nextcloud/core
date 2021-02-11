const path = require('path')

module.exports = {
	entry: {
		comments: path.join(__dirname, 'src', 'comments.js'),
		'comments-app': path.join(__dirname, 'src', 'comments-app.js'),
		'comments-tab': path.join(__dirname, 'src', 'comments-tab.js'),
	},
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: '[name].js',
		jsonpFunction: 'webpackJsonpComments',
	},
	externals: {
		jquery: 'jQuery',
	},
}
