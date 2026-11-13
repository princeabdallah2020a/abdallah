/**
 * Membership portal: pricing duration switch, plan picker summary,
 * registration wizard, confirmations and the app shell menu.
 */
( function () {
	'use strict';

	var i18n = ( window.amcPortal && window.amcPortal.i18n ) || {};

	// ----- Pricing page: commitment switch -----
	var durationSwitch = document.querySelector( '[data-amc-duration-switch]' );
	if ( durationSwitch ) {
		durationSwitch.addEventListener( 'change', function ( event ) {
			var months = event.target.value;
			document.querySelectorAll( '[data-amc-plan-prices]' ).forEach( function ( card ) {
				var prices = JSON.parse( card.getAttribute( 'data-amc-plan-prices' ) || '{}' )[ months ];
				if ( ! prices ) {
					return;
				}
				card.querySelector( '[data-amc-price-monthly]' ).textContent = prices.monthly;
				card.querySelector( '[data-amc-price-billed]' ).textContent = prices.billed;
				card.querySelector( '[data-amc-price-saving]' ).textContent = prices.saving;
				card.querySelector( '[data-amc-price-url]' ).href = prices.url;
			} );
		} );
	}

	// ----- Plan picker + order summary -----
	var picker = document.querySelector( '[data-amc-picker]' );
	var summary = document.querySelector( '[data-amc-summary]' );
	function updateSummary() {
		if ( ! picker || ! summary ) {
			return;
		}
		var data = JSON.parse( picker.getAttribute( 'data-prices' ) || '{}' );
		var plan = picker.querySelector( 'input[name="plan"]:checked' );
		var months = picker.querySelector( 'input[name="months"]:checked' );
		if ( ! plan || ! months || ! data[ plan.value ] ) {
			return;
		}
		var price = data[ plan.value ].prices[ months.value ];
		var label = months.closest( 'label' ).querySelector( 'span' ).firstChild.textContent.trim();
		summary.querySelector( '[data-amc-summary-plan]' ).textContent = data[ plan.value ].name;
		summary.querySelector( '[data-amc-summary-months]' ).textContent = label;
		summary.querySelector( '[data-amc-summary-monthly]' ).textContent = price.monthly;
		summary.querySelector( '[data-amc-summary-total]' ).textContent = price.total;
		summary.querySelector( '[data-amc-summary-saving]' ).textContent = price.saving;
		summary.querySelector( '[data-amc-summary-saving-row]' ).hidden = ! price.saving;
	}
	if ( picker ) {
		picker.addEventListener( 'change', updateSummary );
		updateSummary();
	}

	// ----- Dependent fields -----
	function toggleOnRadio( groupSelector, targetSelector, showWhen ) {
		var group = document.querySelector( groupSelector );
		var target = document.querySelector( targetSelector );
		if ( ! group || ! target ) {
			return;
		}
		var sync = function () {
			var checked = group.querySelector( 'input:checked' );
			target.hidden = ! checked || ! showWhen( checked.value );
		};
		group.addEventListener( 'change', sync );
		sync();
	}
	toggleOnRadio( '[data-amc-website-options]', '[data-amc-domain-field]', function ( value ) {
		return value !== 'later';
	} );
	toggleOnRadio( '[data-amc-wizard] [data-amc-pay-options]', '[data-amc-pay-details]', function ( value ) {
		return value !== 'later';
	} );

	// ----- Registration wizard -----
	var wizard = document.querySelector( '[data-amc-wizard]' );
	if ( wizard ) {
		var steps = Array.prototype.slice.call( wizard.querySelectorAll( '[data-amc-step]' ) );
		var stepper = Array.prototype.slice.call( wizard.querySelectorAll( '[data-amc-stepper] li' ) );
		var prev = wizard.querySelector( '[data-amc-prev]' );
		var next = wizard.querySelector( '[data-amc-next]' );
		var submit = wizard.querySelector( '[data-amc-submit]' );
		var current = 0;

		// After a failed submission, open the first step that has an error.
		var firstError = wizard.querySelector( '.amc-field-error' );
		if ( firstError ) {
			steps.forEach( function ( step, index ) {
				if ( step.contains( firstError ) && current === 0 ) {
					current = index;
				}
			} );
		}

		var show = function ( index ) {
			current = Math.max( 0, Math.min( steps.length - 1, index ) );
			steps.forEach( function ( step, i ) {
				step.hidden = i !== current;
			} );
			stepper.forEach( function ( item, i ) {
				item.classList.toggle( 'is-done', i < current );
				item.classList.toggle( 'is-current', i === current );
			} );
			prev.hidden = current === 0;
			next.hidden = current === steps.length - 1;
			submit.hidden = current !== steps.length - 1;
		};

		var valid = function ( step ) {
			var fields = Array.prototype.slice.call( step.querySelectorAll( 'input, select, textarea' ) ).filter( function ( field ) {
				return ! field.closest( '[hidden]' ) && field.type !== 'hidden';
			} );
			for ( var i = 0; i < fields.length; i++ ) {
				if ( ! fields[ i ].checkValidity() ) {
					fields[ i ].reportValidity();
					return false;
				}
			}
			return true;
		};

		wizard.classList.add( 'is-enhanced' );
		next.addEventListener( 'click', function () {
			if ( valid( steps[ current ] ) ) {
				show( current + 1 );
				wizard.scrollIntoView( { block: 'start', behavior: 'smooth' } );
			}
		} );
		prev.addEventListener( 'click', function () {
			show( current - 1 );
		} );
		wizard.addEventListener( 'submit', function ( event ) {
			if ( ! valid( steps[ current ] ) ) {
				event.preventDefault();
				return;
			}
			submit.disabled = true;
		} );
		show( current );
	}

	// ----- Confirmations -----
	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		if ( form.hasAttribute( 'data-amc-confirm' ) && ! window.confirm( i18n.confirm || 'OK?' ) ) {
			event.preventDefault();
		}
	} );

	// ----- App shell: mobile menu -----
	var menu = document.querySelector( '[data-amc-app-menu]' );
	var nav = document.getElementById( 'amc-app-nav' );
	if ( menu && nav ) {
		menu.addEventListener( 'click', function () {
			var open = ! nav.classList.contains( 'is-open' );
			nav.classList.toggle( 'is-open', open );
			menu.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}
}() );
