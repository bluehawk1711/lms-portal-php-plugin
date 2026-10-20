/**
 * Student Portal — async filters & small submits (sp-async).
 *
 * Markup contract:
 *   form[data-sp-async]               submit is handled with fetch. The page
 *                                     region is swapped with the matching node
 *                                     of the response; with
 *                                     data-sp-async-toast nothing is swapped —
 *                                     only a notice toast and the URL update.
 *   [data-sp-async-region]            the swapped element. Matched in the
 *                                     response by id when it has one, else by
 *                                     the attribute. Links inside a region that
 *                                     stay on the same page swap it too.
 *   select[data-sp-async-change]      auto-submit its form when changed.
 *   'sp:async-replace' document event after every swap — page scripts use it
 *                                     to re-bind handlers on the fresh nodes.
 *
 * Server notices rendered outside the region surface as floating toasts, so
 * feedback is never lost the way a redirect banner would be.
 *
 * Self-contained IIFE, DOMContentLoaded-safe, no dependencies.
 */
( function () {
	'use strict';

	var REGION_ATTR = 'data-sp-async-region';
	var busyForms = null;

	function closestRegion( el ) {
		if ( ! el || ! el.closest ) {
			return null;
		}

		return el.closest( '[' + REGION_ATTR + ']' );
	}

	function selectorFor( region ) {
		return region.id ? '#' + region.id : '[' + REGION_ATTR + ']';
	}

	/**
	 * Build the request URL. GET mirrors native semantics: the form's action
	 * query is replaced by the form's own fields; POST keeps the action URL
	 * as-is (no action means the current URL, query included).
	 */
	function buildUrl( form, isGet, submitter ) {
		var action = ( form.getAttribute( 'action' ) || '' ).trim();
		var base;

		if ( action ) {
			base = action;
		} else {
			base = isGet ? location.pathname : location.href;
		}

		var url = new URL( base, location.href );

		if ( isGet ) {
			url.search = '';

			// The submitter carries the button's name/value (action markers like
			// submit_after), exactly as a native submit would send it.
			new FormData( form, submitter || undefined ).forEach( function ( value, key ) {
				url.searchParams.append( key, value );
			} );
		}

		return url;
	}

	function toast( message, kind ) {
		if ( ! message ) {
			return;
		}

		if ( ! busyForms ) {
			busyForms = document.createElement( 'div' );
			busyForms.className = 'sp-async-toasts';
			busyForms.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( busyForms );
		}

		var el = document.createElement( 'div' );
		el.className = 'sp-async-toast' + ( kind ? ' ' + kind : '' );
		el.setAttribute( 'role', kind === 'is-error' ? 'alert' : 'status' );

		var text = document.createElement( 'span' );
		text.textContent = message;
		el.appendChild( text );

		var close = document.createElement( 'button' );
		close.type = 'button';
		close.setAttribute( 'aria-label', 'Dismiss' );
		close.innerHTML = '&times;';
		close.addEventListener( 'click', function () {
			if ( el.parentNode ) {
				el.parentNode.removeChild( el );
			}
		} );
		el.appendChild( close );

		busyForms.appendChild( el );

		window.setTimeout( function () {
			if ( el.parentNode ) {
				el.parentNode.removeChild( el );
			}
		}, 7000 );
	}

	function noticeFrom( doc, incoming ) {
		var selectors = [
			'.sp-notice-good',
			'.notice-success',
			'.sp-notice-error',
			'.notice-error',
			'.sp-notice-warn',
			'.notice-warning',
			'.notice-info'
		];

		for ( var i = 0; i < selectors.length; i++ ) {
			var node = doc.querySelector( selectors[ i ] );

			if ( ! node ) {
				continue;
			}

			// Notices inside the swapped region arrive with the swap itself.
			if ( incoming && incoming.contains( node ) ) {
				continue;
			}

			var kind = 'is-good';

			if ( selectors[ i ].indexOf( 'error' ) !== -1 ) {
				kind = 'is-error';
			} else if ( selectors[ i ].indexOf( 'warn' ) !== -1 ) {
				kind = 'is-warn';
			}

			return { text: ( node.textContent || '' ).replace( /\s+/g, ' ' ).trim(), kind: kind };
		}

		return null;
	}

	function setBusy( form, region, on ) {
		if ( on ) {
			form.setAttribute( 'data-sp-async-busy', '1' );

			if ( region ) {
				region.classList.add( 'sp-async-busy' );
				region.setAttribute( 'aria-busy', 'true' );
			}
		} else {
			form.removeAttribute( 'data-sp-async-busy' );

			if ( region ) {
				region.classList.remove( 'sp-async-busy' );
				region.removeAttribute( 'aria-busy' );
			}
		}
	}

	/**
	 * Fetch a page state and either swap the region or surface its notice.
	 *
	 * @param {HTMLFormElement|HTMLAnchorElement} source The initiating control.
	 * @param {URL}        url   Request URL.
	 * @param {string}     method GET or POST.
	 * @param {FormData|null} body POST body.
	 * @param {Element|null} region Region to swap (null for toast-only).
	 * @param {boolean}    isGet Whether the URL is pushed to history as a filter.
	 */
	function apply( source, url, method, body, region, isGet ) {
		var form = source && source.tagName === 'FORM' ? source : null;

		if ( form ) {
			setBusy( form, region, true );
		} else if ( region ) {
			region.classList.add( 'sp-async-busy' );
		}

		var options = {
			method: method,
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		};

		if ( body ) {
			options.body = body;
		}

		function done() {
			if ( form ) {
				setBusy( form, region, false );
			} else if ( region ) {
				region.classList.remove( 'sp-async-busy' );
			}
		}

		fetch( url.toString(), options )
			.then( function ( res ) {
				return res.text().then( function ( html ) {
					return { res: res, html: html };
				} );
			} )
			.then( function ( payload ) {
				var res = payload.res;
				var doc = new DOMParser().parseFromString( payload.html, 'text/html' );
				var incoming = null;

				if ( region ) {
					incoming = doc.querySelector( selectorFor( region ) );
				}

				// The response does not contain the expected region (a link
				// crossed screens, or the structure changed): showing the real
				// page beats a half-swap.
				if ( region && ! incoming ) {
					window.location.href = url.toString();
					return;
				}

				if ( incoming ) {
					region.replaceWith( incoming );
				}

				var finalUrl = res.url || url.toString();

				try {
					history[ isGet ? 'pushState' : 'replaceState' ]( null, '', finalUrl );
				} catch ( err ) { /* cross-origin or bare file: — the swap still counts. */ }

				var notice = noticeFrom( doc, incoming );

				if ( notice ) {
					toast( notice.text, notice.kind );
				} else if ( ! res.ok ) {
					toast( 'The server answered with an error (' + res.status + '). Reload the page if this persists.', 'is-error' );
				}

				document.dispatchEvent( new CustomEvent( 'sp:async-replace', {
					detail: { url: finalUrl, region: incoming }
				} ) );
			} )
			.catch( function ( err ) {
				if ( window.console && console.error ) {
					console.error( 'sp-async:', err );
				}
				toast( 'Network problem — the action did not go through. Please try again.', 'is-error' );
			} )
			.then( done );
	}

	function runForm( form, submitter ) {
		if ( ! form || form.getAttribute( 'data-sp-async-busy' ) ) {
			return;
		}

		var isGet = ( form.getAttribute( 'method' ) || 'get' ).toLowerCase() === 'get';
		var toastOnly = form.hasAttribute( 'data-sp-async-toast' );
		var region = toastOnly ? null : closestRegion( form );

		apply(
			form,
			buildUrl( form, isGet, submitter ),
			isGet ? 'GET' : 'POST',
			isGet ? null : new FormData( form, submitter || undefined ),
			region,
			isGet
		);
	}

	// Intercepts marked forms. Runs at the document so it survives every swap.
	document.addEventListener( 'submit', function ( event ) {
		if ( event.defaultPrevented ) {
			return; // an inline onsubmit (confirm) or page script said no.
		}

		var form = event.target;

		if ( ! form || ! form.matches || ! form.matches( 'form[data-sp-async]' ) ) {
			return;
		}

		event.preventDefault();
		runForm( form, event.submitter );
	}, false );

	// Filters: a marked select submits its form as soon as it changes.
	document.addEventListener( 'change', function ( event ) {
		var target = event.target;

		if ( ! target || ! target.matches || ! target.matches( 'select[data-sp-async-change]' ) ) {
			return;
		}

		var form = target.form;

		if ( form && form.matches( 'form[data-sp-async]' ) && ! event.defaultPrevented ) {
			runForm( form );
		}
	}, false );

	// In-region links stay in place: pagination, tab bars, "Clear" links.
	document.addEventListener( 'click', function ( event ) {
		if ( event.defaultPrevented || 0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
			return;
		}

		var link = event.target && event.target.closest ? event.target.closest( 'a[href]' ) : null;

		if ( ! link ) {
			return;
		}

		if ( link.hasAttribute( 'download' ) || link.getAttribute( 'data-sp-async-off' ) !== null ) {
			return;
		}

		if ( link.target && '_self' !== link.target ) {
			return;
		}

		var region = closestRegion( link );

		if ( ! region ) {
			return;
		}

		var href = link.getAttribute( 'href' ) || '';

		if ( ! href || '#' === href.charAt( 0 ) ) {
			return;
		}

		var url;

		try {
			url = new URL( href, location.href );
		} catch ( err ) {
			return;
		}

		if ( url.origin !== location.origin || ( 'http:' !== url.protocol && 'https:' !== url.protocol ) ) {
			return;
		}

		event.preventDefault();
		apply( link, url, 'GET', null, region, true );
	}, false );

	// Browser back/forward: re-render the state the URL describes.
	window.addEventListener( 'popstate', function () {
		window.location.reload();
	} );
} )();
