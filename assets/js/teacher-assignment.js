/**
 * Teacher assignment edit: live search across the class question bank
 * table and the per-set question picker.
 *
 * Each bank row (.sp-ae-table tr) and each picker label (.sp-set-option)
 * carries its searchable text in `data-sp-qsearch` (prompt, format label
 * and/or answer). The bank table hides itself when nothing matches; the
 * set-picker card hides its matching rows. A per-set "x of N selected"
 * badge counts only visible checkboxes.
 *
 * Markup contract (from templates/teacher-assignment-edit.php):
 *   [data-sp-qbank]            the bank/sets card wrapper
 *   [data-sp-qbank-table]      the bank table wrapper
 *   [data-sp-qbank-search]     the search input
 *   [data-sp-qbank-clear]      the clear button (shown while typing)
 *   [data-sp-qbank-status]     the aria-live match counter
 *   [data-sp-set-picker]       one set's question-picker wrapper
 *   [data-sp-qitem]            one bank row / one picker label
 *   [data-sp-qsearch]          the haystack string (lower-cased match)
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var search  = document.querySelector( '[data-sp-qbank-search]' );
		var clear   = document.querySelector( '[data-sp-qbank-clear]' );
		var status  = document.querySelector( '[data-sp-qbank-status]' );
		var bank    = document.querySelector( '[data-sp-qbank-table]' );
		var sections = Array.prototype.slice.call(
			document.querySelectorAll( '[data-sp-set-picker]' )
		);

		if ( ! search ) {
			return;
		}

		var items = Array.prototype.slice.call(
			document.querySelectorAll( '[data-sp-qitem]' )
		);

		var countBadges = Array.prototype.slice.call(
			document.querySelectorAll( '.sp-ae-set-count' )
		);

		function apply() {
			var q = ( search.value || '' ).trim().toLowerCase();
			var visible = 0;

			items.forEach( function ( el ) {
				var hay  = ( el.getAttribute( 'data-sp-qsearch' ) || '' ).toLowerCase();
				var show = ! q || -1 !== hay.indexOf( q );
				el.hidden = ! show;
				if ( show ) {
					visible++;
				}
			} );

			// Hide the whole bank table when the query empties it.
			if ( bank ) {
				bank.hidden = '' !== q && ! Array.prototype.some.call(
					bank.querySelectorAll( '[data-sp-qitem]' ),
					function ( row ) { return ! row.hidden; }
				);
			}

			// Update per-set badges to reflect only visible choices.
			countBadges.forEach( function ( badge ) {
				var row   = badge.closest( '[data-sp-set-picker]' );
				var total = row ? row.querySelectorAll( '[data-sp-qitem]' ).length : 0;
				var shown = row ? row.querySelectorAll( '[data-sp-qitem]:not([hidden])' ).length : 0;
				badge.textContent = shown + ' of ' + total + ' selected';
			} );

			if ( clear ) {
				clear.hidden = '' === q;
			}

			if ( status ) {
				status.textContent = '' === q
					? ''
					: visible + ' of ' + items.length + ' questions match';
				status.hidden = '' === q;
			}
		}

		search.addEventListener( 'input', apply );

		if ( clear ) {
			clear.addEventListener( 'click', function () {
				search.value = '';
				apply();
				search.focus();
			} );
		}

		apply();
	} );
} )();
