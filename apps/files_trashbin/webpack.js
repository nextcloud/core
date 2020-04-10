const path = require('path')

module.exports = {
	entry: path.join(__dirname, 'src', 'files_trashbin.js'),
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: 'files_trashbin.js',
		jsonpFunction: 'webpackJsonpFilesTrashbin'
	}
}
