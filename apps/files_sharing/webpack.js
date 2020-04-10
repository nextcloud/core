const path = require('path');

module.exports = {
	entry: {
		'additionalScripts': path.join(__dirname, 'src', 'additionalScripts.js'),
		'collaboration': path.join(__dirname, 'src', 'collaborationresourceshandler.js'),
		'files_sharing_tab': path.join(__dirname, 'src', 'files_sharing_tab.js'),
		'files_sharing': path.join(__dirname, 'src', 'files_sharing.js'),
		'main': path.join(__dirname, 'src', 'index.js'),
		'personal-settings': path.join(__dirname, 'src', 'personal-settings.js'),
	},
	output: {
		path: path.resolve(__dirname, './js/dist/'),
		publicPath: '/js/',
		filename: '[name].js',
		chunkFilename: 'files_sharing.[id].js?v=[chunkhash]',
		jsonpFunction: 'webpackJsonpFilesSharing'
	}
}
