const path = require( 'path' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
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
				'./assets/src/css/admin/main.scss',
				'./assets/src/js/admin/main.js',
			],
		},
		plugins: [
			...defaultConfig.plugins,

			// Clean the directory before building.
			new CleanWebpackPlugin( {
				cleanOnceBeforeBuildPatterns: [ 'assets/dist/' ],
			} ),

			// Extract CSS into separate files under specific path.
			new MiniCssExtractPlugin( {
				filename: 'css/[name].css',
			} ),
		],
	},
};
