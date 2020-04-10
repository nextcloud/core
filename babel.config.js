module.exports = {
	plugins: [
		'@babel/plugin-syntax-dynamic-import',
		['@babel/plugin-proposal-class-properties', { loose: true }],
	],
	presets: [
		[
			'@babel/preset-env',
			{
				modules: false,
				useBuiltIns: false,
			},
		],
	],
}
