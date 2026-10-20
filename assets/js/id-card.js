/**
 * ID card generation screen (wp-admin only).
 *
 * Loads the preview through admin-ajax so switching students never reloads
 * the page, rasterises the card with html2canvas and saves it as JPG or PDF.
 * The first download flags the card as generated for the student records.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var cfg = window.SpIdCard || {};

		var select = document.querySelector( '[data-sp-idc-select]' );
		var stage = document.querySelector( '[data-sp-idc-stage]' );
		var statusEl = document.querySelector( '[data-sp-idc-status]' );
		var jpgBtn = document.querySelector( '[data-sp-idc-jpg]' );
		var pdfBtn = document.querySelector( '[data-sp-idc-pdf]' );

		if ( ! select || ! stage ) {
			return;
		}

		var currentProfile = parseInt( select.value, 10 ) || 0;
		var busy = false;

		function setStatus( generatedAt ) {
			if ( ! statusEl ) {
				return;
			}

			if ( generatedAt ) {
				statusEl.classList.add( 'is-done' );
				statusEl.setAttribute( 'data-generated', generatedAt );
				statusEl.textContent = 'Generated';
			} else {
				statusEl.classList.remove( 'is-done' );
				statusEl.setAttribute( 'data-generated', '' );
				statusEl.textContent = 'Not generated';
			}
		}

		function post( action, extra ) {
			var data = new FormData();

			data.append( 'action', action );
			data.append( 'nonce', cfg.nonce || '' );

			if ( extra ) {
				Object.keys( extra ).forEach( function ( key ) {
					data.append( key, extra[ key ] );
				} );
			}

			return fetch( cfg.ajax || '', {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} ).then( function ( res ) {
				return res.json();
			} );
		}

		function markGenerated() {
			if ( ! currentProfile ) {
				return;
			}

			post( 'sp_idcard_mark', { profile_id: currentProfile } )
				.then( function ( res ) {
					if ( res && res.success ) {
						setStatus( res.data.generated_at );
					}
				} )
				.catch( function () {} );
		}

		function fileSlug() {
			var name = select.options[ select.selectedIndex ] ? select.options[ select.selectedIndex ].text : 'student';

			return name
				.split( '—' )[0]
				.toLowerCase()
				.replace( /[^a-z0-9]+/g, '-' )
				.replace( /^-+|-+$/g, '' ) || 'student';
		}

		function cardElement() {
			return stage.querySelector( '[data-sp-idc]' );
		}

		function rasterise() {
			var card = cardElement();

			if ( ! card ) {
				return Promise.reject( new Error( 'no-card' ) );
			}

			if ( 'function' !== typeof window.html2canvas ) {
				return Promise.reject( new Error( 'no-library' ) );
			}

			return window.html2canvas( card, {
				scale: 3,
				backgroundColor: '#ffffff',
				useCORS: true,
				logging: false
			} );
		}

		function downloadJpg() {
			if ( busy ) {
				return;
			}

			busy = true;

			rasterise()
				.then( function ( canvas ) {
					var link = document.createElement( 'a' );

					link.href = canvas.toDataURL( 'image/jpeg', 0.95 );
					link.download = 'idcard-' + fileSlug() + '.jpg';
					document.body.appendChild( link );
					link.click();
					document.body.removeChild( link );
					markGenerated();
				} )
				.catch( function ( err ) {
					window.alert(
						'no-library' === err.message
							? 'The export library did not load. Reload the page and try again.'
							: 'The card could not be exported. Make sure a student is selected.'
					);
				} )
				.finally( function () {
					busy = false;
				} );
		}

		function downloadPdf() {
			if ( busy ) {
				return;
			}

			busy = true;

			rasterise()
				.then( function ( canvas ) {
					var JsPDF = window.jspdf && window.jspdf.jsPDF;

					if ( 'function' !== typeof JsPDF ) {
						throw new Error( 'no-library' );
					}

					var widthMm = 86;
					var heightMm = Math.round( ( 86 * canvas.height ) / canvas.width * 100 ) / 100;
					var pdf = new JsPDF( {
						orientation: heightMm > widthMm ? 'portrait' : 'landscape',
						unit: 'mm',
						format: [ widthMm, heightMm ]
					} );

					pdf.addImage( canvas.toDataURL( 'image/jpeg', 0.95 ), 'JPEG', 0, 0, widthMm, heightMm );
					pdf.save( 'idcard-' + fileSlug() + '.pdf' );
					markGenerated();
				} )
				.catch( function ( err ) {
					window.alert(
						'no-library' === err.message
							? 'The PDF library did not load. Reload the page and try again.'
							: 'The card could not be exported. Make sure a student is selected.'
					);
				} )
				.finally( function () {
					busy = false;
				} );
		}

		if ( jpgBtn ) {
			jpgBtn.addEventListener( 'click', downloadJpg );
		}

		if ( pdfBtn ) {
			pdfBtn.addEventListener( 'click', downloadPdf );
		}

		// Switching students swaps the preview in place — no reload.
		select.addEventListener( 'change', function () {
			var next = parseInt( select.value, 10 ) || 0;

			if ( ! next || next === currentProfile ) {
				return;
			}

			currentProfile = next;
			stage.style.opacity = '0.4';

			post( 'sp_idcard_preview', { profile_id: currentProfile } )
				.then( function ( res ) {
					if ( res && res.success ) {
						stage.innerHTML = res.data.html;
						setStatus( res.data.generated_at );
					} else {
						window.alert( ( res && res.data && res.data.message ) || 'Could not load that student.' );
					}
				} )
				.catch( function () {
					window.alert( 'Could not load that student. Reload and try again.' );
				} )
				.finally( function () {
					stage.style.opacity = '';
				} );
		} );
	} );
} )();
