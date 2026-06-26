const wpScriptsGlobalSetup = require( '@wordpress/scripts/config/playwright/global-setup' );

module.exports = async function globalSetup( config ) {
	const missing = [ 'WP_BASE_URL', 'WP_USERNAME', 'WP_PASSWORD' ].filter(
		( key ) => ! process.env[ key ]
	);

	if ( missing.length ) {
		throw new Error(
			`CleanLinks e2e tests require ${ missing.join(
				', '
			) } to target an explicit WordPress test site. Example: WP_BASE_URL=https://development.wp.local WP_USERNAME=admin WP_PASSWORD=... npm run test:e2e`
		);
	}

	await wpScriptsGlobalSetup( config );
};
