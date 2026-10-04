export async function copyTextToClipboard( text ) {
	if ( navigator.clipboard && window.isSecureContext ) {
		await navigator.clipboard.writeText( text );
		return;
	}

	const textarea = document.createElement( 'textarea' );
	const previousFocus = textarea.ownerDocument.activeElement;
	textarea.value = text;
	textarea.setAttribute( 'readonly', '' );
	textarea.style.position = 'absolute';
	textarea.style.left = '-9999px';
	document.body.appendChild( textarea );
	let copied;
	try {
		textarea.select();
		copied = document.execCommand( 'copy' );
	} finally {
		textarea.remove();
		if ( previousFocus && typeof previousFocus.focus === 'function' ) {
			previousFocus.focus();
		}
	}
	if ( !copied ) {
		throw new Error( 'Clipboard copy failed' );
	}
}

export function setupCopyUrlButtons() {
	const copyUrlButtons = document.querySelectorAll( '.cleanlinks--copy-button' );

	Array.from( copyUrlButtons ).forEach( ( button ) => {
		button.addEventListener( 'click', async ( event ) => {
			const currentButton = event.currentTarget;
			const url = currentButton.getAttribute( 'data-url' );
			const textElement = currentButton.querySelector( '.cleanlinks--copy-button-text' );
			if ( !url || !textElement ) {
				return;
			}
			const iconElement = currentButton.querySelector( '.dashicons' );

			try {
				await copyTextToClipboard( url );
				textElement.textContent = currentButton.getAttribute( 'data-copied-text' ) || 'Copied';
				if ( iconElement ) {
					iconElement.classList.replace( 'dashicons-admin-page', 'dashicons-yes' );
				}
			} catch ( e ) {
				textElement.textContent = currentButton.getAttribute( 'data-copy-failed-text' ) || 'Copy failed';
				if ( iconElement ) {
					iconElement.classList.replace( 'dashicons-yes', 'dashicons-admin-page' );
				}
			}
		} );

		button.addEventListener( 'mouseleave', ( event ) => {
			const currentButton = event.currentTarget;
			const textElement = currentButton.querySelector( '.cleanlinks--copy-button-text' );
			const iconElement = currentButton.querySelector( '.dashicons' );
			if ( textElement ) {
				textElement.textContent = currentButton.getAttribute( 'data-default-text' ) || '';
			}
			if ( iconElement ) {
				iconElement.classList.replace( 'dashicons-yes', 'dashicons-admin-page' );
			}
		} );
	} );
}
