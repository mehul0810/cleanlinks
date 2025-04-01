// Import the original config from the @wordpress/scripts package.
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

// Import path to update the output path.
const path = require('path');

// Import the wpPot function from the wp-pot package.
const wpPot = require('wp-pot');

// Set production mode.
const isProduction = 'production' === process.env.NODE_ENV;
const mode = isProduction ? 'production' : 'development';

const config = {
	...defaultConfig,
	mode,
	output: {
		path: path.resolve( __dirname, 'dist' ),
		clean: true,
	},
	entry: {
		admin: [ './assets/scss/admin/main.scss', './assets/js/admin/main.js' ],
	},
	module: {
		...defaultConfig.module,
		rules: [ ...defaultConfig.module.rules ],
	},
	plugins: [ ...defaultConfig.plugins ],
};

if ( isProduction ) {
	// POT file.
	wpPot({
		package: 'CleanLinks',
		domain: 'cleanlinks',
		destFile: 'languages/cleanlinks.pot',
		relativeTo: './',
		src: ['./**/*.php', '!./includes/libraries/**/*', '!./vendor/**/*'],
		bugReport: 'https://github.com/simplifiedwp/cleanlinks/issues/new',
		team: 'SimplifiedWP Team <hello@simplifiedwp.com>',
	} );
}

module.exports = config;
