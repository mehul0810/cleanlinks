import { setupCopyUrlButtons } from './copy-url-button.js';

document.addEventListener( 'DOMContentLoaded', () => {
	setupCopyUrlButtons();

	const destination = document.getElementById( 'cleanlink_redirect_url' );
	const error = document.getElementById( 'cleanlink_redirect_url_error' );
	if ( !destination || !error || !destination.form ) {
		return;
	}

	const validateDestination = () => {
		let valid = true;
		if ( destination.value.trim() ) {
			try {
				const url = new URL( destination.value.trim() );
				valid = [ 'http:', 'https:' ].includes( url.protocol ) && Boolean( url.hostname );
			} catch ( e ) {
				valid = false;
			}
		}

		error.hidden = valid;
		if ( valid ) {
			destination.removeAttribute( 'aria-invalid' );
		} else {
			destination.setAttribute( 'aria-invalid', 'true' );
		}
		return valid;
	};

	destination.addEventListener( 'input', validateDestination );
	destination.addEventListener( 'invalid', () => {
		validateDestination();
	} );
	destination.form.addEventListener( 'submit', ( event ) => {
		if ( !validateDestination() ) {
			event.preventDefault();
			destination.focus();
		}
	} );
} );
