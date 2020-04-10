const path = require('path');

module.exports = {
	entry: path.join(__dirname, 'src', 'workflowengine.js'),
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: 'workflowengine.js',
		jsonpFunction: 'webpackJsonpWorkflowengine'
	}
}
