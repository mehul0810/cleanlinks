import { migrate } from './migrate';

export function setupMigrateLinks() {
    const migrateBtns = document.querySelectorAll('.simplified-links--migrate-btn-wrap button');

	Array.from( migrateBtns ).forEach( ( button ) => {
		button.addEventListener( 'click', ( event ) => {
			event.preventDefault();

			// Display Spinner.
			button.parentElement.querySelector('.spinner').style.visibility = 'visible';

			// Migration Process.
			migrate( 'simplified_links_migrate', 0, button.dataset.post_type, button.dataset.redirect_to, button.dataset.clicks );

			// Hide Spinner.
			button.parentElement.querySelector('.spinner').style.visibility = 'none';
		} );
	});
}
