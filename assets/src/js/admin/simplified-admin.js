document.addEventListener( 'DOMContentLoaded', () => {
	// Get all elements with the class js-simplified-link-button
    const copyButtons = document.querySelectorAll(".js-simplified-link-button");

	copyButtons.forEach(function (btn) {
        const defaultText = btn.getAttribute('data-default-text');
		const copiedText = btn.getAttribute('data-copied-text');
		
		btn.addEventListener('click', function () {
            var simplifiedUrl = this.getAttribute('data-url');
			const span = btn.querySelector('.dashicons');
			span.classList.remove( 'dashicons-admin-page' );
			span.classList.add('dashicons-yes');
			// Change the button text
			btn.querySelector('.simplified-button-text').textContent = copiedText;
			copyTextToClipboard(simplifiedUrl);
		});

		btn.addEventListener('mouseleave', function () {
            const span = btn.querySelector('.dashicons');
			span.classList.remove( 'dashicons-yes' );
			span.classList.add('dashicons-admin-page');
			btn.querySelector('.simplified-button-text').textContent = defaultText;
        });

    });
} );

function copyTextToClipboard(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'absolute';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
}