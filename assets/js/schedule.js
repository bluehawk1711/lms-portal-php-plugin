/**
 * Schedule upgrades for the editable (admin) calendar.
 *
 * The grid itself is server-rendered — no JS is needed to browse Day, Week,
 * Month or Year. This script upgrades two things when the user is editing:
 *
 * - Edit icons and chips fetch the sidebar fragment over admin-ajax instead
 *   of reloading the page, so the current scroll position and unsaved form
 *   state survive.
 * - The sidebar's close button and backdrop hide the panel without a reload.
 *
 * Markup contract (from sp_render_schedule()):
 *   .sp-sch[data-base][data-view][data-date]  the admin Calendar base URL,
 *                                            plus the current view/focus date
 *   .sp-sch-side[.is-open]        the sidebar panel
 *   .sp-sch-side-backdrop         the click-to-close overlay
 *   .sp-sch-side-body             the fragment target
 *   .sp-sch-close                 the close button
 *   .sp-sch-editbtn[data-date]    per-date pencil
 *   a.sp-sch-chip[data-event]     an existing event chip
 *   .sp-sch-side-form             the add/edit form (posted back to this page)
 *
 * AJAX contract (sp_schedule_side_ajax()):
 *   GET admin-ajax.php?action=sp_schedule_side&nonce=…&date=…[&event_id=…]
 *      &view=…[&focus=…]
 *   — a GET query string, because the handler reads $_GET. Params in a POST
 *     body are not visible there.
 *   Responds with wp_send_json_success( array( 'date' => …, 'html' => … ) ),
 *   so the fragment is payload.data.html, not the response text.
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
		var root = document.querySelector( '.sp-sch' );

		if ( ! root || ! root.hasAttribute( 'data-base' ) ) {
			return;
		}

		var side      = root.querySelector( '.sp-sch-side' );
		var backdrop  = root.querySelector( '.sp-sch-side-backdrop' );
		var body      = root.querySelector( '.sp-sch-side-body' );
		var closeBtn  = root.querySelector( '.sp-sch-close' );

		if ( ! side || ! body ) {
			return;
		}

		var ajaxUrl  = ( window.SpSchedule && window.SpSchedule.ajax ) || '';
		var ajaxNonce = ( window.SpSchedule && window.SpSchedule.nonce ) || '';

		function open() {
			side.classList.add( 'is-open' );
			if ( backdrop ) {
				backdrop.hidden = false;
			}
		}

		function close() {
			side.classList.remove( 'is-open' );
			if ( backdrop ) {
				backdrop.hidden = true;
			}
		}

		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', close );
		}

		if ( backdrop ) {
			backdrop.addEventListener( 'click', close );
		}

		function loadFragment( date, eventId ) {
			if ( ! ajaxUrl ) {
				// No JS backend — fall back to the server-rendered link.
				return true;
			}

			var params = new URLSearchParams();
			params.set( 'action', 'sp_schedule_side' );
			params.set( 'nonce', ajaxNonce );
			params.set( 'date', date );

			if ( eventId ) {
				params.set( 'event_id', eventId );
			}

			// Carry the current view so the form's post-save redirect lands
			// back where the user is, not on the default week view.
			params.set( 'view', root.getAttribute( 'data-view' ) || 'week' );

			var focus = root.getAttribute( 'data-date' ) || '';
			if ( focus ) {
				params.set( 'focus', focus );
			}

			// GET with the params in the query string: the handler reads $_GET.
			fetch( ajaxUrl + '?' + params.toString(), {
				method: 'GET',
				credentials: 'same-origin'
			} )
				.then( function ( res ) {
					return res.ok ? res.json() : null;
				} )
				.then( function ( payload ) {
					// The handler wraps the fragment in wp_send_json_success;
					// the markup is data.html, not the response text.
					if ( payload && payload.success && payload.data && payload.data.html ) {
						body.innerHTML = payload.data.html;
					}
					// Open either way: a failed fetch leaves the server-rendered
					// sidebar (hint or pre-rendered fragment) visible rather
					// than swallowing the click.
					open();
				} )
				.catch( function () {
					// Leave the server-rendered sidebar as it was.
					open();
				} );

			return false;
		}

		// Edit pencils: fetch instead of navigate.
		root.querySelectorAll( '.sp-sch-editbtn[data-date]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( event ) {
				if ( loadFragment( el.getAttribute( 'data-date' ), 0 ) ) {
					return; // No AJAX — let the link navigate.
				}
				event.preventDefault();
			} );
		} );

		// Existing event chips: fetch the sidebar with that event loaded.
		root.querySelectorAll( 'a.sp-sch-chip[data-event]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( event ) {
				var eventId = parseInt( el.getAttribute( 'data-event' ), 10 ) || 0;
				var date    = el.getAttribute( 'data-date' ) || '';

				if ( loadFragment( date, eventId ) ) {
					return;
				}
				event.preventDefault();
			} );
		} );
	} );
} )();
