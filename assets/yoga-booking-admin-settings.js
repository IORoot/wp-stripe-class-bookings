/**
 * Settings screen: welcome panel collapse + deep-link ACF tabs from URL hash.
 */
( function () {
	'use strict';

	var LS_KEY = 'clasbowi_welcome_intro_collapsed';
	var root = document.getElementById( 'clasbowi-welcome-panel' );
	var toggle = document.getElementById( 'clasbowi-welcome-toggle' );
	var expandable = document.getElementById( 'clasbowi-welcome-expandable' );

	if ( root && toggle && expandable ) {
		function setCollapsed( collapsed ) {
			root.classList.toggle( 'is-collapsed', collapsed );
			toggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
			var ax = collapsed
				? toggle.getAttribute( 'data-clasbowi-aria-collapsed' )
				: toggle.getAttribute( 'data-clasbowi-aria-expanded' );
			if ( ax ) {
				toggle.setAttribute( 'aria-label', ax );
			}
			expandable.setAttribute( 'aria-hidden', collapsed ? 'true' : 'false' );
			try {
				localStorage.setItem( LS_KEY, collapsed ? '1' : '0' );
			} catch ( e ) {
				// Ignore storage errors (private mode, quota, etc.).
			}
		}

		try {
			if ( localStorage.getItem( LS_KEY ) === '1' ) {
				setCollapsed( true );
			}
		} catch ( e ) {
			// Ignore storage errors.
		}

		toggle.addEventListener( 'click', function () {
			setCollapsed( ! root.classList.contains( 'is-collapsed' ) );
		} );
	}

	function clasbowiOpenSettingsTabFromHash() {
		var h = window.location.hash || '';
		if ( h.indexOf( '#clasbowi-tab-' ) !== 0 ) {
			return;
		}
		var key = h.slice( '#clasbowi-tab-'.length );
		if ( ! /^field_clasbowi_tab_[a-z0-9_]+$/i.test( key ) ) {
			return;
		}
		var btn = document.querySelector( '.acf-tab-button[data-key="' + key + '"]' );
		if ( btn && typeof btn.click === 'function' ) {
			btn.click();
		}
	}

	function clasbowiScheduleTabFromHash() {
		clasbowiOpenSettingsTabFromHash();
		window.setTimeout( clasbowiOpenSettingsTabFromHash, 0 );
		window.setTimeout( clasbowiOpenSettingsTabFromHash, 250 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', clasbowiScheduleTabFromHash );
	} else {
		clasbowiScheduleTabFromHash();
	}
	window.addEventListener( 'hashchange', clasbowiScheduleTabFromHash );
} )();
