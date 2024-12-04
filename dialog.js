( () => {
	'use strict';
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

	if ( ! styles.length && ! scripts.length && dialogP ) {
		dialogP.textContent = 'No assets loaded from jsDelivr CDN. Something seems to be wrong.';
		qs( '.ngt-jsdelivr-dialog p:last-of-type' )?.remove();
	}

	scripts.forEach( ( el ) => {
		const newText = d.createTextNode(
			el.getAttribute( 'src' ).replace( 'https://', '' ) + '\n'
		);
		dialogPre.appendChild( newText );
	} );

	styles.forEach( ( el ) => {
		const newText = d.createTextNode(
			el.getAttribute( 'href' ).replace( 'https://', '' ) + '\n'
		);
		dialogPre.appendChild( newText );
	} );

	adminBarLink.removeAttribute( 'href' );
	adminBarLink.setAttribute( 'type', 'button' );
	adminBarLink.addEventListener( 'click', () => {
		dialog.showModal();
	} );
	dialogClose.addEventListener( 'click', () => {
		dialog.close();
	} );
} )();
