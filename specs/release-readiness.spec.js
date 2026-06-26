const fs = require( 'node:fs/promises' );
const path = require( 'node:path' );
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const POST_TYPE = 'simplifiedwp_links';
const REST_BASE = `/wp/v2/${ POST_TYPE }`;
const SEED_PREFIX = 'CleanLinks E2E Release';
const SEED_REDIRECT_URL = 'https://example.com/cleanlinks-release-readiness';

async function ensureCleanLinksRestRoute( requestUtils ) {
	try {
		await requestUtils.rest( {
			path: `/wp/v2/types/${ POST_TYPE }`,
		} );
	} catch ( error ) {
		throw new Error(
			`CleanLinks REST post type "${ POST_TYPE }" is unavailable. Activate the CleanLinks plugin in the target WordPress test site before running npm run test:e2e. Original error: ${ JSON.stringify(
				error
			) }`
		);
	}
}

async function deleteSeededLinks( requestUtils, ids = [] ) {
	const knownIds = new Set( ids.filter( Boolean ) );

	try {
		const posts = await requestUtils.rest( {
			path: REST_BASE,
			params: {
				search: SEED_PREFIX,
				status: 'publish,draft,pending,private,trash',
				per_page: 100,
			},
		} );

		posts.forEach( ( post ) => knownIds.add( post.id ) );
	} catch ( error ) {
		// Some WordPress REST configurations reject multi-status custom post
		// type queries. Known IDs are still cleaned up below.
	}

	await Promise.all(
		Array.from( knownIds ).map( async ( id ) => {
			try {
				await requestUtils.rest( {
					method: 'DELETE',
					path: `${ REST_BASE }/${ id }`,
					params: {
						force: true,
					},
				} );
			} catch ( error ) {}
		} )
	);
}

async function createSeededLink( requestUtils, title, slug ) {
	return requestUtils.rest( {
		method: 'POST',
		path: REST_BASE,
		data: {
			title,
			slug,
			status: 'publish',
		},
	} );
}

async function captureReleaseEvidence( page, testInfo, fileName ) {
	const artifactsRoot =
		process.env.WP_ARTIFACTS_PATH ||
		path.join( process.cwd(), 'artifacts' );
	const releaseDir = path.join( artifactsRoot, 'release-readiness' );
	const screenshotPath = path.join( releaseDir, fileName );

	await fs.mkdir( releaseDir, { recursive: true } );
	await page.screenshot( {
		path: screenshotPath,
		fullPage: true,
	} );
	await testInfo.attach( `release-readiness/${ fileName }`, {
		path: screenshotPath,
		contentType: 'image/png',
	} );
}

async function visitAdminPage( admin, adminPath, query ) {
	await admin.visitAdminPage( adminPath, query );
	await expect( admin.page.locator( '#wpadminbar' ) ).toBeVisible();
}

test.describe.serial( 'CleanLinks release-readiness admin screenshots', () => {
	let seededLinks = [];

	test.beforeAll( async ( { requestUtils } ) => {
		await ensureCleanLinksRestRoute( requestUtils );
		await deleteSeededLinks( requestUtils );

		const timestamp = Date.now();
		seededLinks = await Promise.all( [
			createSeededLink(
				requestUtils,
				`${ SEED_PREFIX } Primary Link`,
				`cleanlinks-e2e-primary-${ timestamp }`
			),
			createSeededLink(
				requestUtils,
				`${ SEED_PREFIX } Partner Link`,
				`cleanlinks-e2e-partner-${ timestamp }`
			),
		] );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await deleteSeededLinks(
			requestUtils,
			seededLinks.map( ( link ) => link.id )
		);
	} );

	test( 'captures the dashboard list table with seeded links', async ( {
		admin,
		page,
	}, testInfo ) => {
		await visitAdminPage( admin, 'edit.php', `post_type=${ POST_TYPE }` );

		await expect(
			page.getByRole( 'heading', { name: /links/i } ).first()
		).toBeVisible();
		await expect( page.getByText( SEED_PREFIX ).first() ).toBeVisible();
		await expect( page.locator( '.wp-list-table' ) ).toContainText(
			'Redirect To'
		);
		await expect( page.locator( '.wp-list-table' ) ).toContainText(
			'Total Clicks'
		);
		await expect(
			page.locator( '.simplified-links--copy-button' ).first()
		).toBeVisible();

		await captureReleaseEvidence( page, testInfo, 'dashboard-list.png' );
	} );

	test( 'captures the edit link Redirection Settings panel', async ( {
		admin,
		page,
	}, testInfo ) => {
		await visitAdminPage(
			admin,
			'post.php',
			`post=${ seededLinks[ 0 ].id }&action=edit`
		);

		const redirectField = page.locator( '#simplified_redirect_url' );
		await expect( redirectField ).toBeVisible();
		await redirectField.fill( SEED_REDIRECT_URL );
		await expect( redirectField ).toHaveValue( SEED_REDIRECT_URL );
		await expect(
			page.getByRole( 'heading', { name: 'Redirection Settings' } )
		).toBeVisible();

		await captureReleaseEvidence(
			page,
			testInfo,
			'edit-link-settings.png'
		);
	} );

	test( 'captures the import screen', async ( { admin, page }, testInfo ) => {
		await visitAdminPage(
			admin,
			'edit.php',
			`post_type=${ POST_TYPE }&page=simplified_links_migrate`
		);

		await expect(
			page.getByRole( 'heading', { name: /migrate/i } ).first()
		).toBeVisible();
		await expect( page.getByText( /import your links/i ) ).toBeVisible();

		await captureReleaseEvidence( page, testInfo, 'import.png' );
	} );

	test( 'captures the export action on Import/Export', async ( {
		admin,
		page,
	}, testInfo ) => {
		await visitAdminPage(
			admin,
			'edit.php',
			`post_type=${ POST_TYPE }&page=simplified_links_import_export`
		);

		await expect(
			page.getByRole( 'heading', { name: /import\/export/i } )
		).toBeVisible();
		await expect( page.locator( '#export-form' ) ).toBeVisible();
		await expect( page.locator( '#export-submit' ) ).toHaveValue(
			'Export'
		);

		await captureReleaseEvidence( page, testInfo, 'export.png' );
	} );

	test( 'captures constrained dashboard and settings views', async ( {
		admin,
		page,
	}, testInfo ) => {
		await page.setViewportSize( {
			width: 390,
			height: 844,
		} );

		await visitAdminPage( admin, 'edit.php', `post_type=${ POST_TYPE }` );
		await expect( page.getByText( SEED_PREFIX ).first() ).toBeVisible();
		await captureReleaseEvidence(
			page,
			testInfo,
			'mobile-dashboard-list.png'
		);

		await visitAdminPage(
			admin,
			'post.php',
			`post=${ seededLinks[ 0 ].id }&action=edit`
		);
		await expect(
			page.locator( '#simplified_redirect_url' )
		).toBeVisible();
		await captureReleaseEvidence(
			page,
			testInfo,
			'mobile-edit-link-settings.png'
		);
	} );
} );
