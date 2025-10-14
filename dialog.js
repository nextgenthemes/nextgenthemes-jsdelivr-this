const d = document;
const qs = d.querySelector.bind( d );
const qsa = d.querySelectorAll.bind( d );
const adminBarLink = qs( '#wp-admin-bar-ngt-jsdelivr a' );
const styles = qsa( 'link[href^="https://cdn.jsdelivr.net"]' );
const scripts = qsa( 'script[src^="https://cdn.jsdelivr.net"]' );
const dialog = qs( '.ngt-jsdelivr-dialog' );
const dialogP = qs( '.ngt-jsdelivr-dialog p' );
const dialogPre = qs( '.ngt-jsdelivr-dialog pre' );
const dialogClose = qs( '.ngt-jsdelivr-dialog button' );

function appendURL( url ) {
	const newText = d.createTextNode( url.replace( 'https://', '' ) + '\n' );
	dialogPre.appendChild( newText );
}

function appendImportMapURLs() {
	const importMapElement = d.getElementById( 'wp-importmap' );
	if ( ! importMapElement ) {
		return;
	}
	let importMap;
	try {
		importMap = JSON.parse( importMapElement.textContent );
	} catch ( error ) {
		console.error( 'Failed to parse import map:', error );
		return;
	}
	const integrity = importMap.integrity || {};

	for ( const url in integrity ) {
		if ( Object.hasOwnProperty.call( integrity, url ) ) {
			if ( url.startsWith( 'https://cdn.jsdelivr.net' ) ) {
				continue;
			}

			appendURL( url );
		}
	}
}

appendImportMapURLs();

scripts.forEach( ( el ) => {
	appendURL( el.getAttribute( 'src' ) );
} );

styles.forEach( ( el ) => {
	appendURL( el.getAttribute( 'href' ) );
} );

adminBarLink.removeAttribute( 'href' );
adminBarLink.setAttribute( 'type', 'button' );
adminBarLink.addEventListener( 'click', () => {
	dialog.showModal();
} );
dialogClose.addEventListener( 'click', () => {
	dialog.close();
} );
