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

	function run() {
		initMarkAllShortcut();
		initResultGuard();
		initSideToggle();
		initAssignmentType();
		initQuestionType();
		initDistribution();

		// Async region swaps hand over fresh attendance rows and result forms;
		// the sidebar toggle guards itself against rebinding.
		document.addEventListener( 'sp:async-replace', function () {
			initMarkAllShortcut();
			initResultGuard();
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();