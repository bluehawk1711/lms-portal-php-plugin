/**
 * Teacher class list: live search across active and archived classes.
 *
 * Each item carries its searchable text in data-sp-search (title, code,
 * description and student count); the grid and the archived section hide
 * themselves when nothing inside matches, and the empty-state element shows
 * only while a query is active with zero matches everywhere.
 *
 * Markup contract (from templates/teacher-classes.php):
 *   #sp-class-search              the search input
 *   #sp-class-search-clear        the clear button (shown while typing)
 *   #sp-class-search-status       the aria-live match counter
 *   [data-sp-active-grid]         the active cards wrapper
 *   [data-sp-archive-section]     the archived section wrapper
 *   [data-sp-class-item]          one card/row (has data-sp-search)
 *   [data-sp-search-empty]        the no-matches notice
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

	function bind() {
		var search = document.getElementById( 'sp-class-search' );

		if ( ! search ) {
			return;
		}

		// After an async swap this is a fresh input; a surviving one is bound.
		if ( search.getAttribute( 'data-sp-search-bound' ) ) {
			return;
		}

		search.setAttribute( 'data-sp-search-bound', '1' );

		var clear   = document.getElementById( 'sp-class-search-clear' );
		var status  = document.getElementById( 'sp-class-search-status' );
		var empty   = document.querySelector( '[data-sp-search-empty]' );
		var grid    = document.querySelector( '[data-sp-active-grid]' );
		var archive = document.querySelector( '[data-sp-archive-section]' );
		var items   = Array.prototype.slice.call(
			document.querySelectorAll( '[data-sp-class-item]' )
		);

		function anyVisible( scopeEl ) {
			if ( ! scopeEl ) {
				return true;
			}

			return Array.prototype.some.call(
				scopeEl.querySelectorAll( '[data-sp-class-item]' ),
				function ( row ) {
					return ! row.hidden;
				}
			);
		}

		function apply() {
			var q = ( search.value || '' ).trim().toLowerCase();
			var visible = 0;

			items.forEach( function ( el ) {
				var hay  = ( el.getAttribute( 'data-sp-search' ) || '' ).toLowerCase();
				var show = ! q || -1 !== hay.indexOf( q );

				el.hidden = ! show;

				if ( show ) {
					visible++;
				}
			} );

			// Hide a whole section when the query empties it, so neither the
			// grid nor the archived block leaves a blank area behind.
			if ( grid ) {
				grid.hidden = '' !== q && ! anyVisible( grid );
			}

			if ( archive ) {
				archive.hidden = '' !== q && ! anyVisible( archive );
			}

			if ( clear ) {
				clear.hidden = '' === q;
			}

			if ( empty ) {
				empty.hidden = ! ( '' !== q && 0 === visible );
			}

			if ( status ) {
				status.textContent = '' === q
					? ''
					: visible + ' of ' + items.length + ' classes shown';
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
	}

	ready( bind );

	// The class-create submit swaps the region; rebind the fresh search box.
	document.addEventListener( 'sp:async-replace', bind );
} )();
