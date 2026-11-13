/**
 * Dealer CRM: menu, confirmations, photo previews, listing type fields,
 * customer quick-create toggle, live rental total and sale price prefill.
 */
( function () {
	'use strict';

	var cfg = window.adcCrm || {};

	var menu = document.querySelector( '[data-adc-menu]' );
	var side = document.getElementById( 'adc-side' );
	if ( menu && side ) {
		menu.addEventListener( 'click', function () {
			var open = ! side.classList.contains( 'is-open' );
			side.classList.toggle( 'is-open', open );
			menu.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		if ( form.hasAttribute( 'data-adc-confirm' ) && ! window.confirm( cfg.confirm || 'OK?' ) ) {
			event.preventDefault();
			return;
		}
		var button = form.querySelector( 'button[type="submit"]' );
		if ( button && ! event.defaultPrevented ) {
			window.setTimeout( function () {
				button.disabled = true;
			}, 0 );
		}
	} );

	// Photo previews before upload.
	var input = document.querySelector( '[data-adc-photos]' );
	var preview = document.querySelector( '[data-adc-preview]' );
	var drop = document.querySelector( '[data-adc-drop]' );
	if ( input && preview ) {
		input.addEventListener( 'change', function () {
			preview.innerHTML = '';
			Array.prototype.slice.call( input.files ).forEach( function ( file ) {
				if ( ! /^image\//.test( file.type ) ) {
					return;
				}
				var li = document.createElement( 'li' );
				var img = document.createElement( 'img' );
				img.alt = '';
				img.src = URL.createObjectURL( file );
				li.appendChild( img );
				preview.appendChild( li );
			} );
		} );
		if ( drop ) {
			[ 'dragenter', 'dragover' ].forEach( function ( type ) {
				drop.addEventListener( type, function () {
					drop.classList.add( 'is-over' );
				} );
			} );
			[ 'dragleave', 'drop' ].forEach( function ( type ) {
				drop.addEventListener( type, function () {
					drop.classList.remove( 'is-over' );
				} );
			} );
		}
	}

	// Rental period field only for rental vehicles.
	var listing = document.querySelector( '[data-adc-listing-type]' );
	var rentOnly = document.querySelector( '[data-adc-rent-only]' );
	if ( listing && rentOnly ) {
		var sync = function () {
			rentOnly.hidden = listing.value !== 'rent';
		};
		listing.addEventListener( 'change', sync );
		sync();
	}

	// New customer fields only when no existing customer is selected.
	document.querySelectorAll( '[data-adc-customer-picker]' ).forEach( function ( picker ) {
		var select = picker.querySelector( '[data-adc-customer-select]' );
		var fields = picker.querySelector( '[data-adc-new-customer]' );
		var update = function () {
			fields.hidden = select.value !== '';
		};
		select.addEventListener( 'change', update );
		update();
	} );

	// Prefill price / rate from the chosen vehicle.
	document.querySelectorAll( '[data-adc-prices]' ).forEach( function ( form ) {
		var prices = JSON.parse( form.getAttribute( 'data-adc-prices' ) || '{}' );
		var vehicle = form.querySelector( '[data-adc-vehicle]' );
		var price = form.querySelector( '[data-adc-price]' );
		if ( vehicle && price ) {
			vehicle.addEventListener( 'change', function () {
				if ( prices[ vehicle.value ] ) {
					price.value = prices[ vehicle.value ];
				}
				form.dispatchEvent( new Event( 'input' ) );
			} );
		}
	} );

	// Live rental total.
	var rental = document.querySelector( '[data-adc-rental]' );
	if ( rental ) {
		var total = rental.querySelector( '[data-adc-total]' );
		var currency = rental.getAttribute( 'data-currency' ) || '';
		var compute = function () {
			var start = new Date( rental.querySelector( '[name="starts_at"]' ).value );
			var end = new Date( rental.querySelector( '[name="ends_at"]' ).value );
			var unit = rental.querySelector( '[name="rate_unit"]' ).value;
			var rate = parseInt( rental.querySelector( '[name="rate_amount"]' ).value, 10 ) || 0;
			if ( isNaN( start ) || isNaN( end ) || end <= start || ! rate ) {
				total.textContent = '';
				return;
			}
			var units = Math.max( 1, Math.ceil( ( end - start ) / ( unit === 'hour' ? 3600000 : 86400000 ) ) );
			total.textContent = units + ' × ' + rate.toLocaleString( 'fr-FR' ) + ' = ' + ( units * rate ).toLocaleString( 'fr-FR' ) + ' ' + currency;
		};
		rental.addEventListener( 'input', compute );
		rental.addEventListener( 'change', compute );
		compute();
	}
}() );
