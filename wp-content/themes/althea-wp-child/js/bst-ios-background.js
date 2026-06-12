/**
 * iOS site background — client fallback when page cache omits server-side UA markup.
 * Server path: functions.php bst_render_ios_background_image() (preferred when uncached).
 */
(function () {
	var cfg = window.bstIosBg;
	if ( ! cfg || ! cfg.url ) {
		return;
	}

	var ua = navigator.userAgent || '';
	if ( ! /iPhone|iPod|iPad/.test( ua ) ) {
		return;
	}

	if ( document.querySelector( '.bst-ios-bg-layer' ) ) {
		return;
	}

	var body = document.body;
	if ( ! body ) {
		return;
	}

	body.classList.add( 'bst-ios-bg' );
	if ( cfg.tint ) {
		body.classList.add( 'bst-ios-bg-tinted' );
	}

	var pos = ( cfg.posX || 'center' ) + ' ' + ( cfg.posY || 'bottom' );
	var layer = document.createElement( 'div' );
	layer.className = 'bst-ios-bg-layer';
	layer.setAttribute( 'aria-hidden', 'true' );

	var img = document.createElement( 'img' );
	img.src = cfg.url;
	img.alt = '';
	img.decoding = 'async';
	img.setAttribute( 'fetchpriority', 'low' );
	img.style.objectPosition = pos;
	layer.appendChild( img );

	if ( cfg.tint ) {
		var tint = document.createElement( 'span' );
		tint.className = 'bst-ios-bg-tint';
		tint.setAttribute( 'aria-hidden', 'true' );
		layer.appendChild( tint );
	}

	body.insertBefore( layer, body.firstChild );
})();
