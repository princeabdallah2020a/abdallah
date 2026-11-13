/**
 * AutoBuzy assistant chat (public floating widget and admin inline panel).
 * The conversation lives in sessionStorage only; the server keeps no transcript.
 */
( function () {
	'use strict';

	var cfg = window.amcAssistant;
	if ( ! cfg || ! window.fetch ) {
		return;
	}
	var t = cfg.i18n || {};
	var STORE = 'amcAssistant:' + cfg.scope;

	function el( tag, cls, text ) {
		var node = document.createElement( tag );
		if ( cls ) {
			node.className = cls;
		}
		if ( text !== undefined ) {
			node.textContent = text;
		}
		return node;
	}

	// Minimal, safe formatting: text is inserted with textContent; only
	// paragraphs, "- " lists, **bold** and links to this website are built.
	function renderText( container, text ) {
		var list = null;
		String( text ).split( /\n/ ).forEach( function ( line ) {
			var bullet = line.match( /^\s*[-•*]\s+(.*)$/ );
			var target;
			if ( bullet ) {
				if ( ! list ) {
					list = el( 'ul' );
					container.appendChild( list );
				}
				target = el( 'li' );
				list.appendChild( target );
				line = bullet[ 1 ];
			} else {
				list = null;
				if ( ! line.trim() ) {
					return;
				}
				target = el( 'p' );
				container.appendChild( target );
			}
			line.split( /(\*\*[^*]+\*\*|https?:\/\/[^\s)]+)/ ).forEach( function ( part ) {
				if ( /^\*\*[^*]+\*\*$/.test( part ) ) {
					target.appendChild( el( 'strong', '', part.slice( 2, -2 ) ) );
				} else if ( /^https?:\/\//.test( part ) && part.indexOf( cfg.home ) === 0 ) {
					var clean = part.replace( /[.,;:!?]+$/, '' );
					var a = el( 'a', '', t.link || clean );
					a.href = clean;
					target.appendChild( a );
					if ( clean !== part ) {
						target.appendChild( document.createTextNode( part.slice( clean.length ) ) );
					}
				} else if ( part ) {
					target.appendChild( document.createTextNode( part ) );
				}
			} );
		} );
	}

	function vehicleCard( v ) {
		var a = el( 'a', 'amc-ai__car' );
		a.href = v.url;
		if ( v.thumbnail_url ) {
			var img = el( 'img' );
			img.src = v.thumbnail_url;
			img.alt = '';
			img.loading = 'lazy';
			a.appendChild( img );
		}
		var body = el( 'span', 'amc-ai__car-body' );
		body.appendChild( el( 'strong', '', v.title ) );
		body.appendChild( el( 'span', 'amc-ai__car-price', v.price.formatted ) );
		var meta = [ v.year || '', v.mileage_km ? v.mileage_km.toLocaleString( 'fr-FR' ) + ' km' : '', v.city ].filter( Boolean ).join( ' · ' );
		body.appendChild( el( 'span', 'amc-ai__car-meta', meta ) );
		a.appendChild( body );
		return a;
	}

	function mount( root ) {
		var log = root.querySelector( '[data-amc-ai-log]' );
		var form = root.querySelector( '[data-amc-ai-form]' );
		var input = root.querySelector( '[data-amc-ai-input]' );
		var chips = root.querySelector( '[data-amc-ai-chips]' );
		var history = [];
		var busy = false;

		try {
			history = JSON.parse( window.sessionStorage.getItem( STORE ) || '[]' );
		} catch ( e ) {
			history = [];
		}

		function save() {
			try {
				window.sessionStorage.setItem( STORE, JSON.stringify( history.slice( -24 ) ) );
			} catch ( e ) {}
		}

		function addMessage( message ) {
			var row = el( 'div', 'amc-ai__msg amc-ai__msg--' + message.role );
			var bubble = el( 'div', 'amc-ai__bubble' );
			renderText( bubble, message.content );
			row.appendChild( bubble );
			if ( message.vehicles && message.vehicles.length ) {
				var cars = el( 'div', 'amc-ai__cars' );
				message.vehicles.forEach( function ( v ) {
					cars.appendChild( vehicleCard( v ) );
				} );
				row.appendChild( cars );
			}
			if ( message.url ) {
				var more = el( 'a', 'amc-ai__more', t.seeAll );
				more.href = message.url;
				row.appendChild( more );
			}
			log.appendChild( row );
			log.scrollTop = log.scrollHeight;
			return row;
		}

		function setChips( visible ) {
			if ( chips ) {
				chips.hidden = ! visible;
			}
		}

		function send( text ) {
			text = String( text || '' ).trim();
			if ( ! text || busy ) {
				return;
			}
			busy = true;
			setChips( false );
			var user = { role: 'user', content: text.slice( 0, 1000 ) };
			history.push( user );
			addMessage( user );
			input.value = '';
			input.style.height = '';
			var typing = el( 'div', 'amc-ai__msg amc-ai__msg--assistant' );
			var dots = el( 'div', 'amc-ai__bubble amc-ai__typing' );
			dots.setAttribute( 'aria-label', t.thinking || '' );
			dots.appendChild( el( 'span' ) );
			dots.appendChild( el( 'span' ) );
			dots.appendChild( el( 'span' ) );
			typing.appendChild( dots );
			log.appendChild( typing );
			log.scrollTop = log.scrollHeight;

			var headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
			if ( cfg.nonce ) {
				headers[ 'X-WP-Nonce' ] = cfg.nonce;
			}
			fetch( cfg.endpoint, {
				method: 'POST',
				headers: headers,
				credentials: cfg.nonce ? 'same-origin' : 'omit',
				body: JSON.stringify( {
					messages: history.slice( -12 ).map( function ( m ) {
						return { role: m.role, content: m.content };
					} ),
				} ),
			} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						if ( ! response.ok ) {
							throw new Error( data && data.message ? data.message : t.error );
						}
						return data;
					} );
				} )
				.then( function ( data ) {
					var reply = { role: 'assistant', content: data.text, vehicles: data.vehicles || [], url: data.url || '' };
					history.push( reply );
					save();
					typing.remove();
					addMessage( reply );
				} )
				.catch( function ( error ) {
					typing.remove();
					history.pop();
					var row = addMessage( { role: 'assistant', content: error.message || t.error } );
					row.classList.add( 'amc-ai__msg--error' );
					input.value = text;
				} )
				.then( function () {
					busy = false;
					input.focus();
				} );
		}

		history.forEach( addMessage );
		setChips( ! history.length );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			send( input.value );
		} );
		input.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Enter' && ! event.shiftKey ) {
				event.preventDefault();
				send( input.value );
			}
		} );
		input.addEventListener( 'input', function () {
			input.style.height = 'auto';
			input.style.height = Math.min( input.scrollHeight, 120 ) + 'px';
		} );
		if ( chips ) {
			chips.addEventListener( 'click', function ( event ) {
				var chip = event.target.closest( 'button' );
				if ( chip ) {
					send( chip.textContent );
				}
			} );
		}
		var reset = root.querySelector( '[data-amc-ai-reset]' );
		if ( reset ) {
			reset.addEventListener( 'click', function () {
				history = [];
				save();
				log.querySelectorAll( '.amc-ai__msg:not([data-amc-ai-welcome])' ).forEach( function ( node ) {
					node.remove();
				} );
				setChips( true );
				input.focus();
			} );
		}
		return { send: send, focus: function () { input.focus(); } };
	}

	// ----- Inline panel (admin) -----
	var inline = document.querySelector( '[data-amc-ai-inline]' );
	if ( inline ) {
		mount( inline );
	}

	// ----- Floating widget (public site) -----
	var widget = document.querySelector( '[data-amc-ai-widget]' );
	if ( widget ) {
		var panel = widget.querySelector( '[data-amc-ai-panel]' );
		var launcher = widget.querySelector( '[data-amc-ai-open]' );
		var chat = mount( widget );

		var open = function ( focus ) {
			panel.hidden = false;
			widget.classList.add( 'is-open' );
			launcher.setAttribute( 'aria-expanded', 'true' );
			if ( focus !== false ) {
				chat.focus();
			}
		};
		var close = function () {
			panel.hidden = true;
			widget.classList.remove( 'is-open' );
			launcher.setAttribute( 'aria-expanded', 'false' );
			launcher.focus();
		};
		launcher.addEventListener( 'click', function () {
			if ( panel.hidden ) {
				open();
			} else {
				close();
			}
		} );
		widget.querySelector( '[data-amc-ai-close]' ).addEventListener( 'click', close );
		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' && ! panel.hidden ) {
				close();
			}
		} );

		// Any element on the page can open the assistant, optionally with a question:
		// <form data-amc-ai-ask><input name="q"></form>  or  <button data-amc-ai-ask="…">
		document.addEventListener( 'submit', function ( event ) {
			var ask = event.target.closest( 'form[data-amc-ai-ask]' );
			if ( ask ) {
				event.preventDefault();
				var field = ask.querySelector( 'input, textarea' );
				open();
				if ( field && field.value.trim() ) {
					chat.send( field.value );
					field.value = '';
				}
			}
		} );
		document.addEventListener( 'click', function ( event ) {
			var ask = event.target.closest( 'button[data-amc-ai-ask], a[data-amc-ai-ask]' );
			if ( ask ) {
				event.preventDefault();
				open();
				if ( ask.getAttribute( 'data-amc-ai-ask' ) ) {
					chat.send( ask.getAttribute( 'data-amc-ai-ask' ) );
				}
			}
		} );
	}

	// ----- "Draft a reply" buttons (admin contact requests) -----
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-amc-ai-draft]' );
		if ( ! button || ! cfg.draftEndpoint ) {
			return;
		}
		event.preventDefault();
		var output = document.getElementById( 'amc-draft-' + button.getAttribute( 'data-amc-ai-draft' ) );
		button.disabled = true;
		output.hidden = false;
		output.value = t.thinking || '…';
		fetch( cfg.draftEndpoint.replace( '%d', button.getAttribute( 'data-amc-ai-draft' ) ), {
			method: 'POST',
			headers: { 'X-WP-Nonce': cfg.nonce, Accept: 'application/json' },
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				output.value = data.draft || data.message || t.error;
			} )
			.catch( function () {
				output.value = t.error;
			} )
			.then( function () {
				button.disabled = false;
			} );
	} );
}() );
