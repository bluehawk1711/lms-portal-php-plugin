/*
 * Student Portal — attendance register submission UX (G5).
 *
 * Works on both register surfaces (teacher class tab and admin attendance
 * screen): a live "X of Y marked" counter with per-status counts, and a
 * confirmation summary before a register is submitted/locked so nothing is
 * finalised by accident.
 *
 * Self-contained IIFE, DOMContentLoaded-safe, no dependencies.
 */
( function () {
	'use strict';

	// Refresh hook of the register currently on screen (re-pointed on swap).
	var activeRefresh = null;

	function registerForm( form ) {
		if ( form && form.hasAttribute && form.hasAttribute( 'data-sp-register' ) ) {
			return form;
		}

		return document.querySelector( 'form[data-sp-register]' );
	}

	function tally( form ) {
		if ( ! form ) {
			return null;
		}

		var radios = form.querySelectorAll( 'input[type="radio"][name^="marks["]' );
		var seen = {};
		var total = 0;
		var marked = 0;
		var by = {};

		for ( var i = 0; i < radios.length; i++ ) {
			var radio = radios[ i ];

			if ( ! seen[ radio.name ] ) {
				seen[ radio.name ] = true;
				total++;
			}

			if ( radio.checked ) {
				marked++;
				by[ radio.value ] = ( by[ radio.value ] || 0 ) + 1;
			}
		}

		return { total: total, marked: marked, by: by };
	}

	function countText( t ) {
		if ( ! t || ! t.total ) {
			return '';
		}

		var parts = [ t.total + ' total marked' ];
		var order = [ 'present', 'absent', 'late', 'excused' ];

		for ( var i = 0; i < order.length; i++ ) {
			var key = order[ i ];

			// Present and absent always show (so an untouched row reads 0),
			// the rarer marks only appear once somebody carries them.
			if ( ! t.by[ key ] && 'present' !== key && 'absent' !== key ) {
				continue;
			}

			parts.push( key.charAt( 0 ).toUpperCase() + key.slice( 1 ) + ' ' + ( t.by[ key ] || 0 ) );
		}

		return parts.join( ' · ' );
	}

	function labelFor( root, form ) {
		return ( root && root.getAttribute( 'data-sp-date-label' ) ) ||
			( form && form.getAttribute( 'data-sp-date-label' ) ) || '';
	}

	function lockFor( root, form ) {
		return ( root && root.getAttribute( 'data-sp-lock-days' ) ) ||
			( form && form.getAttribute( 'data-sp-lock-days' ) ) || '';
	}

	function confirmMessage( root, form ) {
		var t = tally( registerForm( form ) );
		var dateLabel = labelFor( root, form );
		var lock = lockFor( root, form );
		var lines = [ 'Submit the register' + ( dateLabel ? ' for ' + dateLabel : '' ) + '?' ];

		if ( t ) {
			lines.push( countText( t ) + '.' );
		}

		if ( lock ) {
			lines.push( 'It locks ' + lock + ' days after submission; only an administrator can reopen it sooner.' );
		}

		return lines.join( '\n' );
	}

	function initRegister( form ) {
		if ( ! form || '1' === form.getAttribute( 'data-sp-register-ready' ) ) {
			return;
		}

		form.setAttribute( 'data-sp-register-ready', '1' );

		var out = form.querySelector( '[data-sp-register-count]' ) ||
			document.querySelector( '[data-sp-register-count]' );

		// Snapshot what the server rendered, so "Reset selection" can undo
		// every tentative mark in one click.
		var radios = form.querySelectorAll( 'input[type="radio"][name^="marks["]' );

		for ( var i = 0; i < radios.length; i++ ) {
			radios[ i ].setAttribute( 'data-sp-initial', radios[ i ].checked ? '1' : '0' );
		}

		function refresh() {
			if ( out ) {
				out.textContent = countText( tally( form ) );
			}
		}

		// The reset button lives in the action bar beside the form, so the
		// click handler runs on the document and calls back into this form.
		activeRefresh = refresh;

		form.addEventListener( 'change', function ( event ) {
			var target = event.target;

			if ( target && target.name && 0 === target.name.indexOf( 'marks[' ) ) {
				refresh();
			}
		} );

		refresh();
	}

	function boot() {
		function initAll() {
			var forms = document.querySelectorAll( 'form[data-sp-register]' );

			for ( var i = 0; i < forms.length; i++ ) {
				initRegister( forms[ i ] );
			}
		}

		initAll();

		// Async region swaps hand over fresh register nodes (the G5 counter
		// attaches once per form thanks to the ready flag above).
		document.addEventListener( 'sp:async-replace', initAll );

		// Confirmation before submit/lock — captured so the native submit
		// buttons keep working with or without this script.
		document.addEventListener( 'click', function ( event ) {
			var target = event.target;

			if ( ! target || ! target.closest ) {
				return;
			}

			var btn = target.closest( '[data-sp-register-submit]' );

			if ( ! btn ) {
				return;
			}

			if ( ! window.confirm( confirmMessage( btn, btn.form ) ) ) {
				event.preventDefault();
			}
		}, false );

		// "Reset selection" — put every student back to the state the server
		// rendered (its saved marks, or the default) without a round trip.
		document.addEventListener( 'click', function ( event ) {
			var target = event.target;

			if ( ! target || ! target.closest ) {
				return;
			}

			if ( ! target.closest( '[data-sp-register-reset]' ) ) {
				return;
			}

			var form = document.querySelector( 'form[data-sp-register]' );

			if ( ! form ) {
				return;
			}

			var radios = form.querySelectorAll( 'input[type="radio"][name^="marks["]' );

			for ( var i = 0; i < radios.length; i++ ) {
				radios[ i ].checked = '1' === radios[ i ].getAttribute( 'data-sp-initial' );
			}

			if ( activeRefresh ) {
				activeRefresh();
			}
		}, false );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();