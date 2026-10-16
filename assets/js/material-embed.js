/**
 * Inline .docx previews for /portal/materials/.
 *
 * PDFs, images and plain text are embedded by the server (an <iframe> or
 * <img> on the gated route with inline=1), so this file only handles the one
 * kind a browser cannot render: .docx.
 *
 * The conversion runs through the Mammoth bundle shipped by the Mammoth .docx
 * converter plugin. That bundle never exposes the library — on load it wires
 * itself to the hidden #mammoth-docx-uploader contract printed by
 * sp_docx_preview_host() and nothing else. So this script:
 *
 *   1. fetches the gated file with the student's own cookies,
 *   2. hands it to #mammoth-docx-upload as a real File (DataTransfer),
 *   3. watches the host element's class, which the bundle flips through
 *      status-loading → status-loaded / status-error,
 *   4. copies the converted HTML out of #mammoth-docx-visual-preview.
 *
 * Conversions run one at a time: there is a single shared host, so a second
 * conversion must wait for the first to settle.
 */
( function () {
	'use strict';

	/** How long one conversion may run before the card gives up on it. */
	var TIMEOUT_MS = 45000;

	var queue = [];
	var busy = false;

	/**
	 * Replace a pending preview card with the "not available" card.
	 *
	 * @param {Element} el      .sp-embed-docx container.
	 * @param {string} message  Explanation shown to the student.
	 */
	function showUnavailable( el, message ) {
		el.className = 'sp-embed sp-embed-unavailable is-from-error';
		el.removeAttribute( 'data-src' );
		el.innerHTML =
			'<strong>Preview not available</strong>' +
			'<span class="sp-muted"></span>';
		el.querySelector( '.sp-muted' ).textContent = message;
	}

	/**
	 * Convert one file material, then report completion.
	 *
	 * @param {Element} el   .sp-embed-docx container.
	 * @param {Function} done Called once the card has settled.
	 */
	function preview( el, done ) {
		var src = el.getAttribute( 'data-src' );
		var name = el.getAttribute( 'data-name' ) || 'document.docx';

		fetch( src, { credentials: 'same-origin', redirect: 'follow' } )
			.then( function ( response ) {
				var type = ( response.headers.get( 'content-type' ) || '' ).toLowerCase();

				// A logged-out session is redirected to the portal HTML, which
				// must never be fed to the converter as if it were a document.
				if ( ! response.ok || response.redirected || -1 !== type.indexOf( 'text/html' ) ) {
					throw new Error( 'not a document' );
				}

				return response.arrayBuffer();
			} )
			.then( function ( buffer ) {
				convert( el, name, buffer, done );
			} )
			.catch( function () {
				showUnavailable( el, 'This document could not be loaded for preview.' );
				done();
			} );
	}

	/**
	 * Feed a buffer to the Mammoth bundle and collect the HTML it produces.
	 *
	 * @param {Element} el      .sp-embed-docx container.
	 * @param {string} name     Original filename, used for the File object.
	 * @param {ArrayBuffer} buf Document body.
	 * @param {Function} done   Called once the card has settled.
	 */
	function convert( el, name, buf, done ) {
		var host = document.getElementById( 'mammoth-docx-uploader' );
		var input = document.getElementById( 'mammoth-docx-upload' );
		var frame = document.getElementById( 'mammoth-docx-visual-preview' );
		var settled = false;
		var observer = null;
		var timer = null;

		if ( ! host || ! input || ! frame ) {
			showUnavailable( el, 'The document converter is not available on this page.' );
			done();
			return;
		}

		/**
		 * Close out the card exactly once, with either the HTML or a message.
		 *
		 * @param {string} html  Converted document, or '' on failure.
		 * @param {string} error Failure message, or '' on success.
		 */
		function settle( html, error ) {
			if ( settled ) {
				return;
			}

			settled = true;

			if ( observer ) {
				observer.disconnect();
			}
			if ( timer ) {
				window.clearTimeout( timer );
			}

			if ( error ) {
				showUnavailable( el, error );
			} else {
				el.innerHTML = '<div class="sp-embed-docx-body">' + html + '</div>';
				el.classList.add( 'is-ready' );
			}

			done();
		}

		/** Read the result, or give up when the bundle reports a failure. */
		function check() {
			if ( settled ) {
				return;
			}

			var state = host.className;

			if ( -1 !== state.indexOf( 'status-loaded' ) ) {
				var body = frame.contentDocument && frame.contentDocument.body;
				var html = body ? body.innerHTML : '';

				if ( html && html.trim() ) {
					settle( html, '' );
				}
				return;
			}

			if ( -1 !== state.indexOf( 'status-error' ) ) {
				settle( '', 'That document could not be converted for preview.' );
			}
		}

		// about:blank resolves immediately in practice; poll briefly anyway so
		// a browser that defers it does not turn into a silent failure.
		function whenFrameReady( callback ) {
			var attempts = 0;

			function probe() {
				var ready = false;

				try {
					ready = !!( frame.contentDocument && frame.contentDocument.body );
				} catch ( err ) {
					ready = false;
				}

				if ( ready ) {
					callback();
					return;
				}

				if ( ++attempts > 50 ) {
					settle( '', 'The document preview could not start.' );
					return;
				}

				window.setTimeout( probe, 100 );
			}

			probe();
		}

		whenFrameReady( function () {
			observer = new MutationObserver( check );
			observer.observe( host, { attributes: true, attributeFilter: [ 'class' ] } );

			timer = window.setTimeout( function () {
				settle( '', 'The preview took too long and was cancelled.' );
			}, TIMEOUT_MS );

			try {
				var transfer = new DataTransfer();
				transfer.items.add(
					new File( [ buf ], name, {
						type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
					} )
				);
				input.files = transfer.files;
			} catch ( err ) {
				settle( '', 'This browser cannot run the document preview.' );
				return;
			}

			// The bundle flips the host to status-loading synchronously while
			// handling this event, then settles asynchronously.
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			check();
		} );
	}

	/** Work through the queued .docx cards one conversion at a time. */
	function next() {
		if ( busy ) {
			return;
		}

		var el = queue.shift();

		if ( ! el ) {
			return;
		}

		busy = true;

		preview( el, function () {
			busy = false;
			next();
		} );
	}

	/**
	 * Keep the embeds out of casual capture: no right-click menu (which
	 * offers "Save image as" / "Print"), no Ctrl/Cmd+P, +S or +U.
	 *
	 * This is a deterrence layer, not a guarantee — an OS-level screenshot or
	 * screen recording cannot be blocked by any web page, and the browser's
	 * own PDF viewer keeps shortcuts the page never sees.
	 */
	function initGuards() {
		document.addEventListener( 'contextmenu', function ( event ) {
			var target = event.target;

			if ( target && target.closest && target.closest( '.sp-embed' ) ) {
				event.preventDefault();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( ! ( event.ctrlKey || event.metaKey ) ) {
				return;
			}

			var key = ( event.key || '' ).toLowerCase();

			if ( 'p' === key || 's' === key || 'u' === key ) {
				event.preventDefault();
			}
		} );
	}

	function init() {
		initGuards();

		Array.prototype.forEach.call( document.querySelectorAll( '.sp-embed-docx' ), function ( el ) {
			queue.push( el );
		} );

		next();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
