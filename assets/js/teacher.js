/**
 * Teacher portal helpers.
 *
 * Loaded only on teacher pages; the portal script already covers the shared
 * behaviours (kind toggle, attendance highlighting, unsaved-change guard).
 */
( function () {
	'use strict';

	/**
	 * Offer a one-tap "mark everyone present" from the register itself, so a
	 * teacher marking a full class does not have to scroll back up.
	 */
	function initMarkAllShortcut() {
		var rows = document.querySelectorAll( '.sp-attendance-row' );

		if ( ! rows.length ) {
			return;
		}

		rows.forEach( function ( row ) {
			var present = row.querySelector( '.sp-attendance-option-present input' );

			if ( ! present || present.disabled ) {
				return;
			}

			row.addEventListener( 'dblclick', function () {
				present.checked = true;
				present.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
		} );
	}

	/**
	 * Prevent accidental navigation away from a half-entered result grid by
	 * confirming once, not on every keystroke.
	 */
	var resultGuardDirty = false;
	var resultGuardWindowBound = false;

	function initResultGuard() {
		var form = document.querySelector( '.sp-table-form' );
		var wrapper = form ? form.closest( 'form' ) : null;

		if ( ! wrapper ) {
			return;
		}

		// Fresh nodes after an async swap rebind here; never twice on one node.
		if ( wrapper.getAttribute( 'data-sp-guard-bound' ) ) {
			return;
		}

		wrapper.setAttribute( 'data-sp-guard-bound', '1' );

		wrapper.addEventListener( 'change', function () {
			resultGuardDirty = true;
		} );

		wrapper.addEventListener( 'submit', function () {
			resultGuardDirty = false;
		} );

		if ( resultGuardWindowBound ) {
			return;
		}

		resultGuardWindowBound = true;

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! resultGuardDirty ) {
				return;
			}

			event.preventDefault();
			event.returnValue = '';
			return '';
		} );
	}

	/**
	 * One button, two behaviours: on desktop it collapses the sidebar out of
	 * the way (preference kept in localStorage); below 1025px it slides the
	 * sidebar in as a drawer with a backdrop, page scroll locked, Escape and
	 * backdrop click to close, and it closes itself after a link is chosen.
	 */
	function initSideToggle() {
		var shell = document.querySelector( '.sp-teacher-shell' );
		var side = document.getElementById( 'sp-teacher-side' );
		var toggle = document.querySelector( '.sp-side-toggle' );
		var backdrop = document.querySelector( '.sp-side-backdrop' );

		if ( ! shell || ! side || ! toggle ) {
			return;
		}

		// The sidebar lives outside every async region; bind it exactly once
		// even when sp:async-replace re-runs the initialisers.
		if ( side.getAttribute( 'data-sp-side-bound' ) ) {
			return;
		}

		side.setAttribute( 'data-sp-side-bound', '1' );

		var desktop = window.matchMedia( '(min-width: 1025px)' );
		var storageKey = 'sp-teacher-side';

		function setExpanded( expanded ) {
			toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		}

		function isCollapsed() {
			try {
				return window.localStorage.getItem( storageKey ) === 'hidden';
			} catch ( err ) {
				return false;
			}
		}

		function applyDesktop() {
			shell.classList.toggle( 'sp-side-collapsed', isCollapsed() );
			setExpanded( ! isCollapsed() );
		}

		function closeMobile() {
			side.classList.remove( 'is-open' );
			if ( backdrop ) {
				backdrop.hidden = true;
			}
			document.body.classList.remove( 'sp-side-open' );
			setExpanded( false );
		}

		function openMobile() {
			side.classList.add( 'is-open' );
			if ( backdrop ) {
				backdrop.hidden = false;
			}
			document.body.classList.add( 'sp-side-open' );
			setExpanded( true );
		}

		toggle.addEventListener( 'click', function () {
			if ( desktop.matches ) {
				var hidden = ! shell.classList.contains( 'sp-side-collapsed' );
				shell.classList.toggle( 'sp-side-collapsed', hidden );
				try {
					window.localStorage.setItem( storageKey, hidden ? 'hidden' : 'shown' );
				} catch ( err ) {}
				setExpanded( ! hidden );
				return;
			}

			if ( side.classList.contains( 'is-open' ) ) {
				closeMobile();
			} else {
				openMobile();
			}
		} );

		if ( backdrop ) {
			backdrop.addEventListener( 'click', closeMobile );
		}

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && side.classList.contains( 'is-open' ) ) {
				closeMobile();
				toggle.focus();
			}
		} );

		side.addEventListener( 'click', function ( event ) {
			if ( ! desktop.matches && event.target.closest( 'a' ) ) {
				closeMobile();
			}
		} );

		// Crossing the breakpoint resets the mode that no longer applies.
		function onBreakpoint() {
			if ( desktop.matches ) {
				closeMobile();
				applyDesktop();
			} else {
				shell.classList.remove( 'sp-side-collapsed' );
				closeMobile();
			}
		}

		if ( desktop.addEventListener ) {
			desktop.addEventListener( 'change', onBreakpoint );
		} else if ( desktop.addListener ) {
			desktop.addListener( onBreakpoint );
		}

		if ( desktop.matches ) {
			applyDesktop();
		} else {
			setExpanded( false );
		}
	}

	/**
	 * Show only the fields the chosen assignment type actually uses: the time
	 * limit belongs to exams, the upload to papers.
	 */
	function initAssignmentType() {
		var form = document.querySelector( '.sp-atype-exam' );

		if ( ! form ) {
			return;
		}

		var wrapper = form.closest( 'form' );

		if ( ! wrapper ) {
			return;
		}

		var radios = wrapper.querySelectorAll( 'input[name="type"]' );

		if ( ! radios.length ) {
			return;
		}

		function apply() {
			var selected = wrapper.querySelector( 'input[name="type"]:checked' );
			var type = selected ? selected.value : 'paper';

			Array.prototype.forEach.call( wrapper.querySelectorAll( '.sp-atype-exam' ), function ( el ) {
				el.hidden = 'exam' !== type;
			} );

			Array.prototype.forEach.call( wrapper.querySelectorAll( '.sp-atype-paper' ), function ( el ) {
				el.hidden = 'paper' !== type;
			} );
		}

		Array.prototype.forEach.call( radios, function ( radio ) {
			radio.addEventListener( 'change', apply );
		} );

		apply();
	}

	/**
	 * Only the help text (and option list) for the selected question format.
	 */
	function initQuestionType() {
		var select = document.querySelector( '.sp-qtype-toggle' );

		if ( ! select ) {
			return;
		}

		function apply() {
			var type = select.value;

			Array.prototype.forEach.call( document.querySelectorAll( '.sp-qtype-mcq' ), function ( el ) {
				el.hidden = 'mcq' !== type;
			} );

			Array.prototype.forEach.call( document.querySelectorAll( '.sp-qtype-truefalse' ), function ( el ) {
				el.hidden = 'truefalse' !== type;
			} );

			Array.prototype.forEach.call( document.querySelectorAll( '.sp-qtype-fill' ), function ( el ) {
				el.hidden = 'fill' !== type;
			} );
		}

		select.addEventListener( 'change', apply );
		apply();
	}

	/**
	 * Reveal only the distribution controls the chosen publish mode needs.
	 */
	function initDistribution() {
		var toggles = document.querySelectorAll( '.sp-dist-toggle' );

		if ( ! toggles.length ) {
			return;
		}

		function apply() {
			var wrapper = toggles[0].closest( 'form' );
			var selected = wrapper ? wrapper.querySelector( '.sp-dist-toggle:checked' ) : null;
			var mode = selected ? selected.value : 'single';

			Array.prototype.forEach.call( document.querySelectorAll( '.sp-dist-single' ), function ( el ) {
				el.hidden = 'single' !== mode;
			} );

			Array.prototype.forEach.call( document.querySelectorAll( '.sp-dist-manual' ), function ( el ) {
				el.hidden = 'manual' !== mode;
			} );
		}

		Array.prototype.forEach.call( toggles, function ( radio ) {
			radio.addEventListener( 'change', apply );
		} );

		apply();
	}

	/**
	 * The class page's edit panel is a native <dialog>; wire the open/close
	 * controls and reopen it when the URL arrives with its anchor (a bookmark,
	 * or a return after saving), so the "Edit Class" control never hides a
	 * pending edit. Falls back to a [open] attribute where showModal is
	 * unsupported.
	 */
	function initClassEditPanel() {
		var dialog = document.getElementById( 'sp-edit-class' );

		if ( ! dialog || 'DIALOG' !== dialog.tagName ) {
			return;
		}

		function open() {
			if ( typeof dialog.showModal === 'function' ) {
				if ( ! dialog.open ) {
					dialog.showModal();
				}
			} else if ( ! dialog.hasAttribute( 'open' ) ) {
				dialog.setAttribute( 'open', 'open' );
			}
		}

		function close() {
			if ( typeof dialog.close === 'function' ) {
				dialog.close();
			} else {
				dialog.removeAttribute( 'open' );
			}
		}

		// Bind once per dialog node; async swaps hand over fresh ones.
		if ( ! dialog.getAttribute( 'data-sp-edit-bound' ) ) {
			dialog.setAttribute( 'data-sp-edit-bound', '1' );

			var openBtn = document.querySelector( '[data-sp-edit-open]' );

			if ( openBtn ) {
				openBtn.addEventListener( 'click', open );
			}

			Array.prototype.forEach.call( dialog.querySelectorAll( '[data-sp-edit-close]' ), function ( btn ) {
				btn.addEventListener( 'click', close );
			} );

			// Click on the backdrop (the dialog element itself) closes it.
			dialog.addEventListener( 'click', function ( event ) {
				if ( event.target === dialog ) {
					close();
				}
			} );
		}

		if ( '#sp-edit-class' === window.location.hash ) {
			open();
		}
	}

	/**
	 * Roster card: client-side search, status filter, pagination,
	 * CSV export and the per-student detail dialog.
	 */
	function initRoster() {
		var card = document.querySelector( '[data-sp-roster]' );

		if ( ! card || card.getAttribute( 'data-sp-roster-bound' ) ) {
			return;
		}

		card.setAttribute( 'data-sp-roster-bound', '1' );

		var search  = card.querySelector( '[data-sp-roster-search]' );
		var table   = card.querySelector( '[data-sp-roster-table]' );
		var count   = card.querySelector( '[data-sp-roster-count]' );
		var pager   = card.querySelector( '[data-sp-roster-pager]' );
		var filters = card.querySelectorAll( 'input[name="sp-roster-status"]' );
		var rows    = table ? Array.prototype.slice.call( table.querySelectorAll( 'tbody tr' ) ) : [];
		var perPage = parseInt( card.getAttribute( 'data-sp-per-page' ), 10 ) || 10;
		var page    = 1;
		var status  = '';

		function matches( row ) {
			var hay   = ( row.getAttribute( 'data-sp-search' ) || '' ).toLowerCase();
			var q     = search ? ( search.value || '' ).trim().toLowerCase() : '';
			var stat  = row.getAttribute( 'data-sp-status' ) || '';

			return ( ! q || -1 !== hay.indexOf( q ) ) && ( ! status || stat === status );
		}

		function fmt( n ) {
			return Number( n ).toLocaleString();
		}

		function render() {
			var visible = rows.filter( matches );
			var pages   = Math.max( 1, Math.ceil( visible.length / perPage ) );

			if ( page > pages ) {
				page = pages;
			}

			var start = ( page - 1 ) * perPage;
			var end   = start + perPage;

			rows.forEach( function ( row ) {
				row.hidden = true;
			} );

			visible.slice( start, end ).forEach( function ( row ) {
				row.hidden = false;
			} );

			if ( count ) {
				if ( ! visible.length ) {
					count.innerHTML = 'No matching students.';
				} else {
					count.innerHTML = 'Showing <strong>' + fmt( start + 1 ) + '</strong> to <strong>' +
						fmt( Math.min( end, visible.length ) ) + '</strong> of <strong>' +
						fmt( visible.length ) + '</strong> student' + ( 1 === visible.length ? '' : 's' );
				}
			}

			if ( pager ) {
				pager.innerHTML = '';

				if ( pages <= 1 ) {
					return;
				}

				function link( label, target, disabled, current ) {
					var el = document.createElement( current ? 'span' : 'button' );
					el.className = 'sp-pager-link' + ( current ? ' is-current' : '' );
					el.textContent = label;

					if ( ! current ) {
						el.type = 'button';
						el.disabled = !! disabled;
						el.addEventListener( 'click', function () {
							page = target;
							render();
						} );
					} else {
						el.setAttribute( 'aria-current', 'page' );
					}

					pager.appendChild( el );
				}

				link( 'Previous', page - 1, page <= 1 );

				for ( var i = 1; i <= pages; i++ ) {
					link( String( i ), i, false, i === page );
				}

				link( 'Next', page + 1, page >= pages );
			}
		}

		if ( search ) {
			search.addEventListener( 'input', function () {
				page = 1;
				render();
			} );
		}

		Array.prototype.forEach.call( filters, function ( radio ) {
			radio.addEventListener( 'change', function () {
				status  = radio.value;
				page = 1;
				render();
			} );
		} );

		// Close the kebab/filter popovers when clicking away.
		document.addEventListener( 'click', function ( event ) {
			Array.prototype.forEach.call(
				document.querySelectorAll( '.sp-row-menu[open], .sp-class-filter[open]' ),
				function ( el ) {
					if ( ! el.contains( event.target ) ) {
						el.removeAttribute( 'open' );
					}
				}
			);
		} );

		// CSV export of every matching (filtered) row.
		var exportBtn = document.querySelector( '[data-sp-roster-export]' );

		if ( exportBtn ) {
			exportBtn.addEventListener( 'click', function () {
				var csv = [];
				var head = [ 'Student', 'Enroll ID', 'Email', 'Phone', 'Attendance %', 'Present', 'Total', 'Status' ];
				csv.push( head.join( ',' ) );

				rows.filter( matches ).forEach( function ( row ) {
					var data = {};

					try {
						data = JSON.parse( row.getAttribute( 'data-sp-student' ) || '{}' );
					} catch ( err ) {
						data = {};
					}

					var cells = [
						data.name || '',
						data.enroll || '',
						data.email || '',
						data.phone || '',
						data.pct !== undefined ? data.pct : '',
						data.present !== undefined ? data.present : '',
						data.total !== undefined ? data.total : '',
						data.status || ''
					];

					csv.push( cells.map( function ( cell ) {
						return '"' + String( cell ).replace( /"/g, '""' ) + '"';
					} ).join( ',' ) );
				} );

				var blob = new Blob( [ csv.join( '\n' ) ], { type: 'text/csv;charset=utf-8;' } );
				var url  = URL.createObjectURL( blob );
				var a    = document.createElement( 'a' );

				a.href     = url;
				a.download = 'roster.csv';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( url );
			} );
		}

		// Per-student detail dialog (eye icon).
		var dialog = document.querySelector( '[data-sp-student-dialog]' );

		if ( dialog ) {
			card.addEventListener( 'click', function ( event ) {
				var btn = event.target.closest( '[data-sp-roster-view]' );

				if ( ! btn ) {
					return;
				}

				var row = btn.closest( 'tr' );
				var data = {};

				try {
					data = JSON.parse( row.getAttribute( 'data-sp-student' ) || '{}' );
				} catch ( err ) {
					data = {};
				}

				function set( sel, text ) {
					var el = dialog.querySelector( sel );
					if ( el ) {
						el.textContent = text || '—';
					}
				}

				set( '[data-sd-name]', data.name );
				set( '[data-sd-status]', data.status );
				set( '[data-sd-enroll]', data.enroll );
				set( '[data-sd-email]', data.email );
				set( '[data-sd-phone]', data.phone );
				set( '[data-sd-att]', ( data.pct !== undefined ? data.pct + '%' : '—' ) +
					( data.total ? '  (' + data.present + '/' + data.total + ')' : '' ) );

				var avatar = dialog.querySelector( '[data-sd-avatar]' );
				if ( avatar ) {
					avatar.textContent = ( data.name || '?' ).charAt( 0 ).toUpperCase();
				}

				var mail = dialog.querySelector( '[data-sd-mail]' );
				if ( mail && data.email ) {
					mail.setAttribute( 'href', 'mailto:' + data.email );
				}

				if ( typeof dialog.showModal === 'function' ) {
					dialog.showModal();
				} else {
					dialog.setAttribute( 'open', 'open' );
				}
			} );
		}

		// "Enroll Student" explainer dialog for teachers.
		var enrollBtn  = document.querySelector( '[data-sp-enroll-info]' );
		var enrollDialog = document.querySelector( '[data-sp-enroll-dialog]' );

		if ( enrollBtn && enrollDialog ) {
			enrollBtn.addEventListener( 'click', function () {
				if ( typeof enrollDialog.showModal === 'function' ) {
					enrollDialog.showModal();
				} else {
					enrollDialog.setAttribute( 'open', 'open' );
				}
			} );
		}

		render();
	}

	function run() {
		initMarkAllShortcut();
		initResultGuard();
		initSideToggle();
		initAssignmentType();
		initQuestionType();
		initDistribution();
		initClassEditPanel();
		initRoster();

		// Async region swaps hand over fresh attendance rows and result forms;
		// the sidebar toggle guards itself against rebinding.
		document.addEventListener( 'sp:async-replace', function () {
			initMarkAllShortcut();
			initResultGuard();
			initClassEditPanel();
			initRoster();
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();