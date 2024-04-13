export function setupCopyUrlButtons() {
    const copyUrlButtons = document.querySelectorAll('.simplified-links--copy-button');

	const copyTextToClipboard = ( text ) => {
		var textarea = document.createElement( 'textarea' );
		textarea.value = text;
		textarea.setAttribute( 'readonly', '' );
		textarea.style.position = 'absolute';
		textarea.style.left = '-9999px';
		document.body.appendChild( textarea );
		textarea.select();
		document.execCommand( 'copy' );
		document.body.removeChild( textarea );
	}


    Array.from(copyUrlButtons).forEach((button) => {
        button.addEventListener('click', ( event ) => {
            const url         = event.currentTarget.getAttribute('data-url');
			const copiedText  = event.currentTarget.getAttribute('data-copied-text');
            const iconElement = event.currentTarget.querySelector('.dashicons');
			const textElement = event.currentTarget.querySelector('.simplified-links--copy-button-text');

			iconElement.classList.add('dashicons-yes');
			iconElement.classList.remove('dashicons-admin-page');

			textElement.textContent = iconElement.textContent + copiedText;

            // Copy URL to clipboard.
            copyTextToClipboard(url);
        });

        button.addEventListener('mouseleave', (event) => {
			const defaultText = event.currentTarget.getAttribute('data-default-text');
            const iconElement = event.currentTarget.querySelector('span.dashicons');
			const textElement = event.currentTarget.querySelector('.simplified-links--copy-button-text');

			iconElement.classList.remove('dashicons-yes');
			iconElement.classList.add('dashicons-admin-page');

			textElement.textContent = iconElement.textContent + defaultText;
        });
    });
}
