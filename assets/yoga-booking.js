/* IORoot Stripe Bookings — frontend behaviour */
( function () {
	'use strict';

	const cfg = window.IOROOT_YB || {
		rest_url: window.location.origin.replace( /\/$/, '' ) + '/wp-json/stripe-bookings/v1/',
		nonce: '',
	};

	function $$( root, sel ) {
		return Array.prototype.slice.call( root.querySelectorAll( sel ) );
	}

	function formatPrice( pounds ) {
		return '£' + Number( pounds ).toFixed( 2 );
	}

	function showError( form, message, fieldName ) {
		const errEl = form.querySelector( '.yb-form__error' );
		if ( errEl ) {
			errEl.hidden = false;
			errEl.textContent = message;
		}
		$$( form, '.yb-form__input--error' ).forEach( function ( el ) {
			el.classList.remove( 'yb-form__input--error' );
		} );
		if ( fieldName ) {
			const f = form.querySelector( '[name="' + fieldName + '"]' );
			if ( f ) {
				f.classList.add( 'yb-form__input--error' );
				f.focus();
			}
		}
	}

	function clearError( form ) {
		const errEl = form.querySelector( '.yb-form__error' );
		if ( errEl ) {
			errEl.hidden = true;
			errEl.textContent = '';
		}
		$$( form, '.yb-form__input--error' ).forEach( function ( el ) {
			el.classList.remove( 'yb-form__input--error' );
		} );
	}

	function setLoading( button, on ) {
		if ( ! button ) return;
		button.classList.toggle( 'is-loading', !! on );
		button.disabled = !! on;
		button.setAttribute( 'aria-busy', on ? 'true' : 'false' );
	}

	function updateSeatsOptions( form ) {
		const dateSel = form.querySelector( '[name="class_date"]' );
		const seatsSel = form.querySelector( '[name="seats"]' );
		const totalEl = form.querySelector( '.yb-form__total' );
		if ( ! dateSel || ! seatsSel ) return;

		if ( dateSel.options.length && dateSel.selectedIndex < 0 ) {
			const firstSelectable = Array.prototype.findIndex.call( dateSel.options, function ( option ) {
				return ! option.disabled;
			} );
			if ( firstSelectable >= 0 ) {
				dateSel.selectedIndex = firstSelectable;
			}
		}

		const opt = dateSel.options[ dateSel.selectedIndex ];
		const remaining = opt ? Math.max( 0, parseInt( opt.dataset.remaining || '0', 10 ) ) : 0;
		const max = Math.max( 1, Math.min( 4, remaining ) );

		const previous = parseInt( seatsSel.value, 10 ) || 1;
		seatsSel.innerHTML = '';
		for ( let i = 1; i <= max; i++ ) {
			const o = document.createElement( 'option' );
			o.value = String( i );
			o.textContent = String( i );
			seatsSel.appendChild( o );
		}
		seatsSel.value = String( Math.min( previous, max ) );
		updateTotal( form );

		if ( remaining === 0 ) {
			seatsSel.disabled = true;
		} else {
			seatsSel.disabled = false;
		}
	}

	function updateTotal( form ) {
		const seatsSel = form.querySelector( '[name="seats"]' );
		const totalEl = form.querySelector( '.yb-form__total' );
		if ( ! seatsSel || ! totalEl ) return;
		const seats = parseInt( seatsSel.value, 10 ) || 1;
		const unit = parseFloat( totalEl.dataset.ybUnitPrice || '0' );
		totalEl.textContent = formatPrice( unit * seats );
	}

	async function refreshAvailability( wrapper ) {
		const classId = wrapper.dataset.ybClassId;
		if ( ! classId ) return;
		try {
			const res = await fetch( cfg.rest_url + 'availability?class_id=' + encodeURIComponent( classId ) );
			if ( ! res.ok ) return;
			const data = await res.json();
			const dateSel = wrapper.querySelector( '[name="class_date"]' );
			if ( ! dateSel ) return;
			dateSel.innerHTML = '';
			( data.dates || [] ).forEach( function ( d ) {
				const o = document.createElement( 'option' );
				o.value = d.date;
				o.dataset.remaining = String( d.remaining );
				o.dataset.cancelled = d.cancelled ? '1' : '0';
				if ( d.cancelled ) {
					o.disabled = true;
					o.className = 'yb-form__option--cancelled';
					o.textContent = 'Cancelled - ' + d.label;
				} else {
					const seatsLabel = ( d.remaining === 1 ) ? '1 seat left' : ( d.remaining + ' seats left' );
					o.textContent = d.label + ' · ' + seatsLabel;
				}
				dateSel.appendChild( o );
			} );
			updateSeatsOptions( wrapper.querySelector( '.yb-form__form' ) || wrapper );
		} catch ( e ) { /* ignore */ }
	}

	function getWrapperForForm( form ) {
		if ( ! form ) return null;
		return form.closest( '.yb-form[data-yb-class-id]' );
	}

	async function handleFormSubmit( form, ev ) {
		ev.preventDefault();
		clearError( form );

		const wrapper = getWrapperForForm( form );
		if ( ! wrapper ) {
			return;
		}

		const button = form.querySelector( '.yb-form__button' );
		const fd = new FormData( form );
		const extraFields = {};
		form.querySelectorAll( '[name^="extra_fields["]' ).forEach( function ( el ) {
			const match = el.name.match( /^extra_fields\[([^\]]+)\]$/ );
			if ( ! match ) return;
			const key = match[1];
			if ( el.type === 'checkbox' ) {
				extraFields[ key ] = el.checked ? 1 : 0;
			} else {
				extraFields[ key ] = ( el.value || '' ).toString().trim();
			}
		} );
		const payload = {
			class_id: parseInt( wrapper.dataset.ybClassId, 10 ) || 0,
			class_date: fd.get( 'class_date' ),
			seats: parseInt( fd.get( 'seats' ), 10 ) || 1,
			customer_name: ( fd.get( 'customer_name' ) || '' ).toString().trim(),
			customer_email: ( fd.get( 'customer_email' ) || '' ).toString().trim(),
			waiver_accepted: fd.has( 'waiver_accepted' ),
			mailchimp_opt_in: fd.has( 'mailchimp_opt_in' ),
			extra_fields: extraFields,
			origin_url: window.location.href,
		};

		if ( ! payload.customer_name ) {
			showError( form, 'Please enter your name.', 'customer_name' );
			return;
		}
		if ( ! payload.customer_email ) {
			showError( form, 'Please enter your email address.', 'customer_email' );
			return;
		}
		const missingRequiredExtra = Array.prototype.find.call(
			form.querySelectorAll( '[name^="extra_fields["][data-yb-required="1"]' ),
			function ( el ) {
				return ( el.type === 'checkbox' ) ? !el.checked : !( ( el.value || '' ).toString().trim() );
			}
		);
		if ( missingRequiredExtra ) {
			showError( form, 'Please complete all required fields.', missingRequiredExtra.name );
			return;
		}
		const waiverInput = form.querySelector( '[name="waiver_accepted"]' );
		if ( waiverInput && ! payload.waiver_accepted ) {
			showError( form, 'Please accept the waiver before continuing to payment.', 'waiver_accepted' );
			return;
		}

		setLoading( button, true );

		try {
			const res = await fetch( cfg.rest_url + 'checkout', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.nonce,
				},
				body: JSON.stringify( payload ),
			} );

			const data = await res.json().catch( function () { return {}; } );

			if ( ! res.ok || data.error ) {
				const message = data.message || 'Something went wrong. Please try again.';
				showError( form, message, data.field || null );
				setLoading( button, false );

				if ( data.reason === 'capacity_full' || data.reason === 'date_invalid' || data.reason === 'class_inactive' ) {
					refreshAvailability( wrapper );
				}
				return;
			}

			if ( data.url ) {
				window.location.href = data.url;
				return;
			}

			showError( form, 'No payment URL returned. Please try again.' );
			setLoading( button, false );
		} catch ( e ) {
			showError( form, 'Network error. Please check your connection and try again.' );
			setLoading( button, false );
		}
	}

	function attachStatusPolling() {
		document.querySelectorAll( '.yb-status[data-yb-session]' ).forEach( function ( el ) {
			const sessionId = el.dataset.ybSession;
			if ( ! sessionId ) return;
			if ( ! el.classList.contains( 'yb-status--pending' ) ) return;

			let attempts = 0;
			const max = 90; // ~3+ minutes with adaptive interval
			const tick = async function () {
				attempts++;
				try {
					const url = cfg.rest_url + 'booking-status?session=' + encodeURIComponent( sessionId ) + '&_t=' + Date.now();
					const res = await fetch( url, {
						credentials: 'same-origin',
						cache: 'no-store',
						headers: {
							'X-WP-Nonce': cfg.nonce,
							'Cache-Control': 'no-cache',
						},
					} );
					if ( res.ok ) {
						const data = await res.json();
						if ( data.status === 'paid' ) {
							window.location.reload();
							return;
						}
					}
				} catch ( e ) { /* ignore */ }
				if ( attempts < max ) {
					const wait = attempts < 10 ? 2000 : ( attempts < 30 ? 3000 : 5000 );
					setTimeout( tick, wait );
				} else {
					const text = el.querySelector( '.yb-status__pending-text' );
					if ( text ) {
						text.textContent = 'Still confirming with Stripe — please refresh this page in a moment.';
					}
				}
			};
			setTimeout( tick, 2000 );
		} );
	}

	function attachWaiverRichLabels() {
		document.querySelectorAll( '[data-yb-waiver-group]' ).forEach( function ( group ) {
			const input = group.querySelector( '[name="waiver_accepted"]' );
			const labelArea = group.querySelector( '[data-yb-waiver-label]' );
			if ( ! input || ! labelArea ) {
				return;
			}
			labelArea.addEventListener( 'click', function ( ev ) {
				if ( ev.target.closest && ev.target.closest( 'a' ) ) {
					return;
				}
				ev.preventDefault();
				input.checked = ! input.checked;
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
		} );
	}

	function init() {
		document.querySelectorAll( '.yb-form__form' ).forEach( updateSeatsOptions );
		attachWaiverRichLabels();
		document.addEventListener( 'change', function ( ev ) {
			const target = ev.target;
			if ( ! target || ! target.closest ) return;
			const form = target.closest( '.yb-form__form' );
			if ( ! form ) return;
			if ( target.matches( '[name="class_date"]' ) ) {
				updateSeatsOptions( form );
			}
			if ( target.matches( '[name="seats"]' ) ) {
				updateTotal( form );
			}
		} );
		document.addEventListener( 'submit', function ( ev ) {
			const form = ev.target && ev.target.closest ? ev.target.closest( '.yb-form__form' ) : null;
			if ( ! form ) return;
			handleFormSubmit( form, ev );
		} );
		attachStatusPolling();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
