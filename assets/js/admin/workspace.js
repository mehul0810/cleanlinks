/* global cleanlinksWorkspace */
const config = cleanlinksWorkspace;
const all = document.querySelector( '[data-cl-select-all]' );
if ( all ) {
	all.addEventListener( 'change', () => {
		for ( const box of document.querySelectorAll( 'input[name="ids[]"]' ) ) {
			box.checked = all.checked;
		}
	} );
}
for ( const button of document.querySelectorAll( '[data-cl-copy]' ) ) {
	button.addEventListener( 'click', async () => {
		const live = document.getElementById( 'cl-live' );
		try {
			await navigator.clipboard.writeText( button.dataset.clCopy );
			live.textContent = config.copied;
		} catch {
			live.textContent = config.copyFailed;
		}
	} );
}
// Parse the first CSV record, including quoted commas and newlines.
function header( text ) {
	const values = [];
	let field = '';
	let quoted = false;
	for ( let i = 0; i < text.length; i++ ) {
		const char = text[ i ];
		if ( char === '"' ) {
			if ( quoted && text[ i + 1 ] === '"' ) {
				field += '"';
				i++;
			} else {
				quoted = ! quoted;
			}
		} else if ( ! quoted && char === ',' ) {
			values.push( field );
			field = '';
		} else if ( ! quoted && ( char === '\n' || char === '\r' ) ) {
			break;
		} else {
			field += char;
		}
	}
	values.push( field );
	return values;
}
const file = document.querySelector( '[data-cl-csv]' );
if ( file ) {
	file.addEventListener( 'change', async () => {
		if ( ! file.files.length ) {
			return;
		}
		const columns = header( await file.files[ 0 ].slice( 0, 8192 ).text() );
		const aliases = { url: 'destination', 'redirect to': 'destination', 'redirect from': 'slug', post_title: 'title', post_name: 'slug', post_status: 'status' };
		for ( const map of document.querySelectorAll( '[data-cl-map]' ) ) {
			map.replaceChildren( new Option( config.ignore, '-1' ) );
			for ( let i = 0; i < columns.length; i++ ) {
				map.add( new Option( columns[ i ], String( i ) ) );
				const name = columns[ i ].trim().toLowerCase();
				if ( ( aliases[ name ] || name ) === map.dataset.clMap ) {
					map.value = String( i );
				}
			}
			map.disabled = false;
		}
	} );
}
const runner = document.querySelector( '[data-cl-run]' );
if ( runner ) {
	runner.addEventListener( 'click', async () => {
		runner.disabled = true;
		const live = document.getElementById( 'cl-import-live' );
		try {
			let running = true;
			while ( running ) {
				const response = await fetch( config.ajax, {
					method: 'POST',
					credentials: 'same-origin',
					body: new URLSearchParams( { action: 'cleanlinks_import_step', nonce: config.nonce, job: runner.dataset.clRun } ),
				} );
				const result = await response.json();
				if ( ! response.ok || ! result.success ) {
					throw new Error( result.data?.message || config.failed );
				}
				live.textContent = config.progress.replace( '%1$s', result.data.next ).replace( '%2$s', result.data.pages ).replace( '%3$s', result.data.counts.created );
				running = result.data.state === 'running';
			}
			window.location.reload();
		} catch ( error ) {
			live.textContent = error.message || config.failed;
			runner.disabled = false;
		}
	} );
}

const notice = document.querySelector( '[data-cl-notice][role="alert"]' );
if ( notice ) {
	window.addEventListener( 'load', () => {
		window.requestAnimationFrame( () => notice.focus() );
	} );
}

const editor = document.querySelector( '[data-cl-editor]' );
if ( editor ) {
	let dirty = false;
	editor.addEventListener( 'input', () => { dirty = true; } );
	editor.addEventListener( 'submit', () => { dirty = false; } );
	window.addEventListener( 'beforeunload', ( event ) => {
		if ( dirty ) {
			event.preventDefault();
			event.returnValue = '';
		}
	} );
}

// Suggestions remain editable; the server confirms uniqueness and the saved URL.
if ( editor?.dataset.clNew === '1' ) {
	const destination = document.getElementById( 'cl-destination' );
	const title = document.getElementById( 'cl-title' );
	const slug = document.getElementById( 'cl-slug' );
	const preview = document.getElementById( 'cl-url-preview' );
	const emptyPreview = preview.textContent;
	let titleEdited = !! title.value;
	let slugEdited = !! slug.value;
	const showPreview = () => {
		if ( slug.value ) {
			preview.textContent = preview.dataset.clPreviewPattern.replace( 'cl-slug-placeholder', encodeURIComponent( slug.value ) );
		} else {
			preview.textContent = emptyPreview;
		}
	};
	const suggestSlug = () => {
		if ( ! slugEdited ) {
			slug.value = title.value.normalize( 'NFKD' ).replace( /[\u0300-\u036f]/g, '' ).toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' ).slice( 0, 200 ).replace( /-+$/g, '' );
		}
		showPreview();
	};
	destination.addEventListener( 'change', () => {
		try {
			const url = new URL( destination.value );
			if ( [ 'http:', 'https:' ].includes( url.protocol ) && ! titleEdited ) {
				title.value = url.hostname + ( url.pathname === '/' ? '' : ' ' + url.pathname );
				suggestSlug();
			}
		} catch {
			// Destination validation and input retention belong to the server.
		}
	} );
	title.addEventListener( 'input', () => { titleEdited = true; suggestSlug(); } );
	slug.addEventListener( 'input', () => { slugEdited = true; showPreview(); } );
	showPreview();
}
