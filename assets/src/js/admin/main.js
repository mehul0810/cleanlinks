import { setupCopyUrlButtons } from './copy-url-button.js';

document.addEventListener( 'DOMContentLoaded', () => {
    setupCopyUrlButtons();


	const migratebtn = document.getElementById( 'migrate-btn' );
	const errorMessage = document.getElementById( 'errormessage' );
	const progress = document.getElementById( 'progress' );
	const progressBar = document.getElementById( 'progressbar' );
	const progressmsg = document.querySelector( '.js-message' );

	const setProgressZero = () => {
		progressBar.style.width = '0%'; // Initialize progress bar width to 0%
		progress.classList.remove( 'sl-hidden' ); // Show progress
		progressmsg.textContent =
			'Importing and updating your links. For large bulk imports this may take some time with no progress bar movement.';
	};

	const setProgress = ( progessPercentage ) => {
		progressBar.style.width = progessPercentage + '%';
	};

	const hideProgressBar = () => {
		progressmsg.textContent = '';
		progress.classList.add( 'sl-hidden' ); // Hide progress bar
	};

	if ( null !== migratebtn ) {
		migratebtn.addEventListener( 'click', ( e ) => {
			e.preventDefault();

			setProgressZero(); // Show progress bar before sending the request

			const formData = new FormData();
			formData.append( 'action', 'simplified_import' );

			fetch( ajaxurl, {
				method: 'POST',
				body: formData,
			} )
				.then( ( response ) => {
					setProgress( 10 );
					if ( 200 === response.status ) {
						return response.json();
					}
					return false;
				} )
				.then( ( data ) => {
					console.log( 'data = ' + data.success );
					if ( data.success === true ) {
						let width = 0;
						const interval = setInterval( () => {
							width += 10; // Increase width by 10%
							progressBar.style.width = `${ width }%`;

							if ( width >= 100 ) {
								clearInterval( interval ); // Stop the interval when width reaches 100%
								progressmsg.textContent = 'Import Successfully';
							}
						}, 200 ); // Update progress every 200 milliseconds
						setTimeout( () => {
							hideProgressBar();
						}, 5000 );
						console.log( 'success' );
					} else {
						console.log( 'data = ' + data.success );
						console.log( 'error' );
						errorMessage.classList.remove( 'sl-hidden' );
						errorMessage.textContent =
							'There was no any import lasso post data.';
						setTimeout( () => {
							errorMessage.classList.add( 'sl-hidden' );
							errorMessage.textContent = '';
							hideProgressBar();
						}, 5000 );
					}
				} )
				.catch( ( error ) => {
					console.error(
						'There was a problem with the fetch operation:',
						error
					);
				} );
		} );
	}


} );

