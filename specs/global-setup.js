const { request } = require( '@playwright/test' );
const { RequestUtils } = require( '@wordpress/e2e-test-utils-playwright' );

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

	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;
	const requestContext = await request.newContext( {
		baseURL,
		ignoreHTTPSErrors: process.env.WP_IGNORE_HTTPS_ERRORS === 'true',
	} );
	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath,
	} );

	await requestUtils.setupRest();
	await requestContext.dispose();
};
