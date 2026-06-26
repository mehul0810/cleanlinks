/**
 * WordPress scripts Playwright config for CleanLinks release-readiness checks.
 *
 * The plugin is validated against a prepared local WordPress install. Set
 * WP_BASE_URL, WP_USERNAME, and WP_PASSWORD for the target test site. Set
 * WP_IGNORE_HTTPS_ERRORS=true only for local HTTPS certificates such as Studio.
 */
const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

if ( process.env.WP_IGNORE_HTTPS_ERRORS === 'true' ) {
	process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
}

module.exports = {
	...baseConfig,
	globalSetup: require.resolve( './specs/global-setup.js' ),
	webServer: undefined,
	use: {
		...baseConfig.use,
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
		ignoreHTTPSErrors: process.env.WP_IGNORE_HTTPS_ERRORS === 'true',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
};
