/**
 * WordPress scripts Playwright config for CleanLinks release-readiness checks.
 *
 * The plugin is validated against a prepared local WordPress install. Set
 * WP_BASE_URL, WP_USERNAME, and WP_PASSWORD for the target test site.
 */
const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = {
	...baseConfig,
	globalSetup: require.resolve( './specs/global-setup.js' ),
	webServer: undefined,
	use: {
		...baseConfig.use,
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
};
