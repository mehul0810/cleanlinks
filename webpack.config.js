const path = require( 'path' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );
// const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config.js' );

module.exports = {
	...defaultConfig,
	...{
		output: {
			path: path.resolve( __dirname, 'assets/dist/' ),
			filename: 'js/[name].js',
		},
		entry: {
			admin: [
				'./assets/src/css/admin/main.css',
				'./assets/src/js/admin/main.js',
			],
		},
		module: {
			rules: [
				...defaultConfig.module.rules,
				{
					test: /\.s[ac]ss$/i,
					use: [
					  // Creates `style` nodes from JS strings
					  "style-loader",
					  // Translates CSS into CommonJS
					  "css-loader",
					  // Compiles Sass to CSS
					  "postcss-loader",
					],
				},
			]
		},
		plugins: [
			...defaultConfig.plugins,

			// Clean the directory before building.
			new CleanWebpackPlugin( {
				cleanOnceBeforeBuildPatterns: [ 'assets/dist/' ],
			} ),

			// Extract CSS into separate files under specific path.
			// new MiniCssExtractPlugin( {
			// 	filename: 'css/[name].css',
			// } ),
		],
	},
};
