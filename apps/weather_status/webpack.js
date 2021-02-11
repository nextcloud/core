const path = require('path')

module.exports = {
	entry: {
		'weather-status': path.join(__dirname, 'src', 'weather-status')
	},
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: '[name].js?v=[chunkhash]',
		jsonpFunction: 'webpackJsonpWeatherStatus'
	},
	optimization: {
		splitChunks: {
			automaticNameDelimiter: '-',
		}
	},
	module: {
        rules: [
            {
                test: /\.(png|jpg|gif|svg|woff|woff2|eot|ttf)$/,
                loader: 'url-loader',
            },
        ],
	},
}
