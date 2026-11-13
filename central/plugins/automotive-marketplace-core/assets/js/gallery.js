/**
 * Vehicle photo gallery: thumbnails, previous / next buttons, arrow keys and
 * swipe. Without JavaScript each thumbnail simply links to the full image.
 */
( function () {
	'use strict';

	var gallery = document.querySelector( '[data-amc-gallery]' );
	if ( ! gallery ) {
		return;
	}
	var main = gallery.querySelector( '[data-amc-gallery-main]' );
	var thumbs = Array.prototype.slice.call( gallery.querySelectorAll( '[data-amc-gallery-thumb]' ) );
	var counter = gallery.querySelector( '[data-amc-gallery-index]' );
	if ( ! main || thumbs.length < 2 ) {
		return;
	}
	var index = 0;

	function show( next ) {
		index = ( next + thumbs.length ) % thumbs.length;
		var thumb = thumbs[ index ];
		main.src = thumb.getAttribute( 'href' );
		main.alt = thumb.getAttribute( 'data-alt' ) || '';
		thumbs.forEach( function ( item, i ) {
			item.classList.toggle( 'is-active', i === index );
			if ( i === index ) {
				item.setAttribute( 'aria-current', 'true' );
			} else {
				item.removeAttribute( 'aria-current' );
			}
		} );
		if ( counter ) {
			counter.textContent = String( index + 1 );
		}
		if ( thumb.scrollIntoView ) {
			thumb.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
		}
	}

	thumbs.forEach( function ( thumb, i ) {
		thumb.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			show( i );
		} );
	} );

	var prev = gallery.querySelector( '[data-amc-gallery-prev]' );
	var next = gallery.querySelector( '[data-amc-gallery-next]' );
	if ( prev ) {
		prev.addEventListener( 'click', function () {
			show( index - 1 );
		} );
	}
	if ( next ) {
		next.addEventListener( 'click', function () {
			show( index + 1 );
		} );
	}

	gallery.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'ArrowLeft' ) {
			show( index - 1 );
		} else if ( event.key === 'ArrowRight' ) {
			show( index + 1 );
		}
	} );

	var startX = null;
	main.addEventListener( 'touchstart', function ( event ) {
		startX = event.touches[ 0 ].clientX;
	}, { passive: true } );
	main.addEventListener( 'touchend', function ( event ) {
		if ( startX === null ) {
			return;
		}
		var delta = event.changedTouches[ 0 ].clientX - startX;
		if ( Math.abs( delta ) > 40 ) {
			show( index + ( delta < 0 ? 1 : -1 ) );
		}
		startX = null;
	}, { passive: true } );
}() );
