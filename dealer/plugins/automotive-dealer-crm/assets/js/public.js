/**
 * Dealer website: photo gallery (buttons, thumbnails, arrows, swipe) and
 * instant filtering of the inventory.
 */
( function () {
	'use strict';

	var gallery = document.querySelector( '[data-adc-gallery]' );
	if ( gallery ) {
		var main = gallery.querySelector( '[data-adc-gallery-main]' );
		var thumbs = Array.prototype.slice.call( gallery.querySelectorAll( '[data-adc-thumb]' ) );
		var counter = gallery.querySelector( '[data-adc-index]' );
		var index = 0;
		var show = function ( i ) {
			if ( ! main || ! thumbs.length ) {
				return;
			}
			index = ( i + thumbs.length ) % thumbs.length;
			var thumb = thumbs[ index ];
			main.removeAttribute( 'srcset' );
			if ( thumb.getAttribute( 'data-srcset' ) ) {
				main.setAttribute( 'srcset', thumb.getAttribute( 'data-srcset' ) );
			}
			main.src = thumb.getAttribute( 'href' );
			thumbs.forEach( function ( t, n ) {
				t.classList.toggle( 'is-active', n === index );
			} );
			if ( counter ) {
				counter.textContent = String( index + 1 );
			}
		};
		thumbs.forEach( function ( thumb, i ) {
			thumb.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				show( i );
			} );
		} );
		var prev = gallery.querySelector( '[data-adc-prev]' );
		var next = gallery.querySelector( '[data-adc-next]' );
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
		if ( main ) {
			main.addEventListener( 'touchstart', function ( event ) {
				startX = event.touches[ 0 ].clientX;
			}, { passive: true } );
			main.addEventListener( 'touchend', function ( event ) {
				if ( startX !== null && Math.abs( event.changedTouches[ 0 ].clientX - startX ) > 40 ) {
					show( index + ( event.changedTouches[ 0 ].clientX < startX ? 1 : -1 ) );
				}
				startX = null;
			}, { passive: true } );
		}
	}

	// Inventory filters apply as soon as a select changes.
	var filters = document.querySelector( '[data-adc-autosubmit]' );
	if ( filters ) {
		filters.addEventListener( 'change', function ( event ) {
			if ( event.target.tagName === 'SELECT' ) {
				filters.submit();
			}
		} );
	}
}() );
