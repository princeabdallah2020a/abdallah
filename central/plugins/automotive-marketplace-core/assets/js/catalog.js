/**
 * Catalog: dependent brand → model selects (every search form) and AJAX
 * filtering, sorting and pagination (catalog and dealer pages).
 * Without JavaScript the forms still work as regular GET forms.
 */
( function () {
	'use strict';

	var config = window.amcCatalog || {};

	// ----- Brand → model -----
	function syncModels( form ) {
		var brand = form.querySelector( 'select[name="brand"]' );
		var model = form.querySelector( 'select[name="model"]' );
		if ( ! brand || ! model ) {
			return;
		}
		var models = ( config.brands && config.brands[ brand.value ] ) || {};
		var current = model.value;
		model.innerHTML = '';
		model.appendChild( new Option( ( config.i18n && config.i18n.allModels ) || '', '' ) );
		Object.keys( models ).forEach( function ( slug ) {
			model.appendChild( new Option( models[ slug ], slug, false, slug === current ) );
		} );
		model.disabled = ! brand.value;
	}

	document.querySelectorAll( '.amc-filters' ).forEach( function ( form ) {
		var brand = form.querySelector( 'select[name="brand"]' );
		if ( brand ) {
			syncModels( form );
			brand.addEventListener( 'change', function () {
				syncModels( form );
			} );
		}
	} );

	// ----- AJAX results -----
	var form = document.querySelector( '[data-amc-filters]' );
	var results = document.querySelector( '[data-amc-results]' );
	if ( ! form || ! results || ! config.restUrl || ! window.fetch || ! window.URLSearchParams ) {
		return;
	}

	var controller = null;
	var timer = null;

	function params( page ) {
		var out = new URLSearchParams();
		new FormData( form ).forEach( function ( value, key ) {
			if ( value !== '' && key.indexOf( 'amc_' ) !== 0 ) {
				out.append( key, value );
			}
		} );
		out.set( 'preset', form.getAttribute( 'data-preset' ) || 'all' );
		if ( form.getAttribute( 'data-dealer-page' ) ) {
			out.set( 'dealer_page', form.getAttribute( 'data-dealer-page' ) );
		}
		if ( page > 1 ) {
			out.set( 'pg', page );
		}
		return out;
	}

	function load( page, options ) {
		options = options || {};
		if ( controller ) {
			controller.abort();
		}
		controller = window.AbortController ? new AbortController() : null;
		results.classList.add( 'is-loading' );
		results.setAttribute( 'aria-busy', 'true' );

		var url = config.restUrl + ( config.restUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + params( page ).toString();

		fetch( url, { signal: controller ? controller.signal : undefined, headers: { Accept: 'application/json' } } )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				return response.json();
			} )
			.then( function ( data ) {
				results.innerHTML = data.html;
				if ( ! options.skipHistory && data.url ) {
					window.history.pushState( { amc: true }, '', data.url );
				}
				if ( options.scroll ) {
					results.scrollIntoView( { block: 'start' } );
				}
			} )
			.catch( function ( error ) {
				if ( error.name === 'AbortError' ) {
					return;
				}
				// Fall back to a normal page load so the user is never stuck.
				var alert = document.createElement( 'div' );
				alert.className = 'amds-alert amds-alert--error';
				alert.setAttribute( 'role', 'alert' );
				alert.textContent = ( config.i18n && config.i18n.error ) || 'Error';
				results.prepend( alert );
			} )
			.then( function () {
				results.classList.remove( 'is-loading' );
				results.setAttribute( 'aria-busy', 'false' );
			} );
	}

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		load( 1 );
	} );

	form.addEventListener( 'change', function () {
		load( 1 );
	} );

	form.addEventListener( 'input', function ( event ) {
		if ( event.target.matches( 'input[type="search"], input[type="number"]' ) ) {
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				load( 1 );
			}, 450 );
		}
	} );

	function reset() {
		form.reset();
		form.querySelectorAll( 'input:not([type="hidden"]), select' ).forEach( function ( field ) {
			field.value = '';
		} );
		var sort = form.querySelector( 'input[name="sort"]' );
		if ( sort ) {
			sort.value = '';
		}
		syncModels( form );
		load( 1 );
	}

	document.addEventListener( 'click', function ( event ) {
		var resetLink = event.target.closest( '[data-amc-reset]' );
		if ( resetLink ) {
			event.preventDefault();
			reset();
			return;
		}
		var pageLink = event.target.closest( '[data-amc-page]' );
		if ( pageLink && results.contains( pageLink ) ) {
			event.preventDefault();
			load( parseInt( pageLink.getAttribute( 'data-amc-page' ), 10 ) || 1, { scroll: true } );
		}
	} );

	results.addEventListener( 'change', function ( event ) {
		if ( event.target.matches( '[data-amc-sort]' ) ) {
			var sort = form.querySelector( 'input[name="sort"]' );
			if ( sort ) {
				sort.value = event.target.value;
			}
			load( 1 );
		}
	} );

	// Back / forward buttons: the URL is the source of truth.
	window.addEventListener( 'popstate', function () {
		window.location.reload();
	} );
}() );
