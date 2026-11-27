/* eslint-env jest */
import { copyTextToClipboard, setupCopyUrlButtons } from './copy-url-button';

beforeEach( () => {
	document.body.innerHTML = '<button type="button">Keep focus</button>';
	Object.defineProperty( window, 'isSecureContext', {
		configurable: true,
		value: false,
	} );
} );

afterEach( () => {
	delete document.execCommand;
} );

test.each( [
	[ 'success', true, false ],
	[ 'false return', false, true ],
	[ 'exception', new Error( 'Denied' ), true ],
] )( 'fallback %s restores focus and removes the temporary input', async ( _, result, rejects ) => {
	const button = document.querySelector( 'button' );
	button.focus();
	document.execCommand = jest.fn( () => {
		if ( result instanceof Error ) {
			throw result;
		}
		return result;
	} );

	if ( rejects ) {
		await expect( copyTextToClipboard( 'https://example.com' ) ).rejects.toThrow();
	} else {
		await expect( copyTextToClipboard( 'https://example.com' ) ).resolves.toBeUndefined();
	}

	expect( document.activeElement ).toBe( button );
	expect( document.querySelector( 'textarea' ) ).toBeNull();
} );

test( 'copy button reports a failed write instead of claiming success', async () => {
	document.body.innerHTML = '<button class="cleanlinks--copy-button" data-url="https://example.com" data-copy-failed-text="Copy failed"><span class="dashicons dashicons-admin-page"></span><span class="cleanlinks--copy-button-text">Copy short URL</span></button>';
	document.execCommand = jest.fn( () => false );
	setupCopyUrlButtons();
	document.querySelector( 'button' ).click();
	await Promise.resolve();
	await Promise.resolve();

	expect( document.querySelector( '.cleanlinks--copy-button-text' ).textContent ).toBe( 'Copy failed' );
	expect( document.querySelector( '.dashicons' ).classList.contains( 'dashicons-yes' ) ).toBe( false );
} );

test( 'copy button restores its icon after a successful copy followed by a failed copy', async () => {
	document.body.innerHTML = '<button class="cleanlinks--copy-button" data-url="https://example.com" data-copied-text="Copied!" data-copy-failed-text="Localized failure"><span class="dashicons dashicons-admin-page"></span><span class="cleanlinks--copy-button-text">Copy URL</span></button>';
	document.execCommand = jest.fn().mockReturnValueOnce( true ).mockReturnValueOnce( false );
	setupCopyUrlButtons();
	const button = document.querySelector( 'button' );
	const icon = button.querySelector( '.dashicons' );
	const label = button.querySelector( '.cleanlinks--copy-button-text' );

	button.click();
	await Promise.resolve();
	expect( label.textContent ).toBe( 'Copied!' );
	expect( icon.classList.contains( 'dashicons-yes' ) ).toBe( true );

	button.click();
	await Promise.resolve();
	expect( label.textContent ).toBe( 'Localized failure' );
	expect( icon.classList.contains( 'dashicons-admin-page' ) ).toBe( true );
	expect( icon.classList.contains( 'dashicons-yes' ) ).toBe( false );
} );
