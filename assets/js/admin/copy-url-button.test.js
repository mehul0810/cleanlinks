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
