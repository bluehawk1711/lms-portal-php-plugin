/**
 * Student portal behaviour.
 *
 * Progressive enhancement only: every link and form works without this file.
 */
( function () {
	'use strict';

	/**
	 * Show the materials matching the chosen type on the authoring screen.
	 */
	function initKindToggle() {
		var toggles = document.querySelectorAll( '.sp-kind-toggle' );

		if ( ! toggles.length ) {
			return;
		}

		function apply() {
			var selected = document.querySelector( '.sp-kind-toggle:checked' );
			var kind = selected ? selected.value : 'note';

			Array.prototype.forEach.call( document.querySelectorAll( '.sp-kind-note' ), function ( el ) {
				el.classList.toggle( 'is-visible', 'note' === kind );
			} );

			Array.prototype.forEach.call( document.querySelectorAll( '.sp-kind-file' ), function ( el ) {
				el.classList.toggle( 'is-visible', 'file' === kind );
			} );
		}

		Array.prototype.forEach.call( toggles, function ( toggle ) {
			toggle.addEventListener( 'change', apply );
		} );

		apply();
	}

	/**
	 * Mark the chosen attendance option so the tap target is highlighted even
	 * in browsers without :has() support.
	 */
	function initAttendanceOptions() {
		var options = document.querySelectorAll( '.sp-attendance-option' );

		if ( ! options.length ) {
			return;
		}

		function refresh( changed ) {
			if ( changed ) {
				changed.parentNode.classList.add( 'is-checked' );
			}

			Array.prototype.forEach.call( options, function ( option ) {
				var input = option.querySelector( 'input' );

				if ( input && input.checked ) {
					option.classList.add( 'is-checked' );
				} else {
					option.classList.remove( 'is-checked' );
				}
			} );
		}

		Array.prototype.forEach.call( options, function ( option ) {
			var input = option.querySelector( 'input' );

			if ( input ) {
				input.addEventListener( 'change', function () {
					refresh( option );
				} );
			}
		} );

		refresh( null );
	}

	/**
	 * Warn before leaving a register with unsaved changes.
	 */
	function initUnsavedGuard() {
		var form = document.querySelector( 'form[enctype="multipart/form-data"], .sp-attendance-list' );
		var register = document.querySelector( '.sp-attendance-list' );

		if ( ! form && ! register ) {
			return;
		}

		var dirty = false;
		var scope = register ? register.closest( 'form' ) : form;

		if ( ! scope ) {
			return;
		}

		scope.addEventListener( 'change', function () {
			dirty = true;
		} );

		scope.addEventListener( 'submit', function () {
			dirty = false;
		} );

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! dirty ) {
				return;
			}

			event.preventDefault();
			event.returnValue = '';
			return '';
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}

	/**
	 * Reveal a password field while the button is held/toggled.
	 *
	 * Markup: a button with .sp-pw-toggle and data-target pointing at the
	 * input's id, with an eye icon inside. The accessible name lives on
	 * aria-label rather than the button's text, because the visible content is
	 * an icon. Everything still works without this — the field is just a
	 * password field until the user clicks.
	 */
	function initPasswordToggles() {
		var buttons = document.querySelectorAll( '.sp-pw-toggle' );

		if ( ! buttons.length ) {
			return;
		}

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.setAttribute( 'aria-pressed', 'false' );

			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				var input = document.querySelector( button.getAttribute( 'data-target' ) );

				if ( ! input ) {
					return;
				}

				var reveal = 'password' === input.type;

				input.type = reveal ? 'text' : 'password';
				button.classList.toggle( 'is-revealed', reveal );
				button.setAttribute( 'aria-pressed', reveal ? 'true' : 'false' );
				button.setAttribute( 'aria-label', reveal ? 'Hide password' : 'Show password' );
			} );
		} );
	}

	/**
	 * Give the auth screen's submit button a real busy state.
	 *
	 * The form posts the old-fashioned way, so all this can do is disable the
	 * button (no double submit), flip it to its loading style with the
	 * spinner, and mark it aria-busy until the next page arrives. pageshow
	 * resets it when the browser restores the form from the back/forward
	 * cache, so a Back navigation never leaves a dead button behind.
	 */
	function initAuthSubmit() {
		var forms = document.querySelectorAll( '.sp-auth-form' );

		if ( ! forms.length ) {
			return;
		}

		Array.prototype.forEach.call( forms, function ( form ) {
			var button = form.querySelector( '.sp-auth-submit' );

			if ( ! button ) {
				return;
			}

			function reset() {
				button.disabled = false;
				button.classList.remove( 'is-loading' );
				button.removeAttribute( 'aria-busy' );
			}

			form.addEventListener( 'submit', function () {
				button.disabled = true;
				button.classList.add( 'is-loading' );
				button.setAttribute( 'aria-busy', 'true' );
			} );

			window.addEventListener( 'pageshow', function ( event ) {
				if ( event.persisted ) {
					reset();
				}
			} );
		} );
	}

	/**
	 * Slide the portal navigation in on small screens.
	 *
	 * Markup: a .sp-nav-toggle button, a .sp-portal-nav, and an optional
	 * .sp-nav-backdrop. The shell's .sp-portal-side wraps the nav and is the
	 * real drawer when present; the nav element itself is the fallback for
	 * markup that predates the sidebar shell. The sidebar is always visible at
	 * desktop widths; this only adds the drawer behaviour when the CSS has
	 * collapsed it.
	 */
	function initMobileNav() {
		var toggle = document.querySelector( '.sp-nav-toggle' );
		var nav = document.querySelector( '.sp-portal-nav' );

		if ( ! toggle || ! nav ) {
			return;
		}

		var drawer = document.querySelector( '.sp-portal-side' ) || nav;
		var backdrop = document.querySelector( '.sp-nav-backdrop' );

		function close() {
			drawer.classList.remove( 'is-open' );
			toggle.setAttribute( 'aria-expanded', 'false' );

			if ( backdrop ) {
				backdrop.hidden = true;
			}

			document.body.classList.remove( 'sp-nav-open' );
		}

		function open() {
			drawer.classList.add( 'is-open' );
			toggle.setAttribute( 'aria-expanded', 'true' );

			if ( backdrop ) {
				backdrop.hidden = false;
			}

			document.body.classList.add( 'sp-nav-open' );
		}

		toggle.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			if ( drawer.classList.contains( 'is-open' ) ) {
				close();
			} else {
				open();
			}
		} );

		if ( backdrop ) {
			backdrop.addEventListener( 'click', close );
		}

		document.addEventListener( 'keydown', function ( event ) {
			if ( 27 === event.key && drawer.classList.contains( 'is-open' ) ) {
				close();
				toggle.focus();
			}
		} );

		// A desktop breakpoint change must not leave the drawer stuck open.
		window.addEventListener( 'resize', function () {
			if ( window.innerWidth > 768 && drawer.classList.contains( 'is-open' ) ) {
				close();
			}
		} );

		close();
	}

	/**
	 * Run a timed exam: countdown, auto-submit, and the leave-page beacon.
	 *
	 * Markup: a .sp-exam-form carrying data-seconds-left, an optional
	 * .sp-exam-timer to draw the countdown in, and a hidden #sp-exam-leave
	 * form (its own nonce) posted by navigator.sendBeacon() when the page is
	 * closed mid-attempt. The server ignores a beacon that races a real
	 * submit, so the submitted flag here is only an optimisation.
	 */
	function initExamRunner() {
		var form = document.querySelector( '.sp-quiz-form, .sp-exam-form' );

		if ( ! form ) {
			return;
		}

		var timer = document.querySelector( '.sp-quiz-timer, .sp-exam-timer' );
		var leave = document.getElementById( 'sp-exam-leave' );
		var submitted = false;
		var seconds = parseInt( form.getAttribute( 'data-seconds-left' ), 10 );

		if ( isNaN( seconds ) || seconds < 0 ) {
			seconds = 0;
		}

		function render() {
			if ( ! timer ) {
				return;
			}

			var minutes = Math.floor( seconds / 60 );
			var rest = seconds % 60;

			timer.textContent = ( minutes < 10 ? '0' : '' ) + minutes + ':' + ( rest < 10 ? '0' : '' ) + rest;
			timer.classList.toggle( 'is-urgent', seconds <= 60 );
		}

		form.addEventListener( 'submit', function () {
			submitted = true;
		} );

		render();

		var tick = window.setInterval( function () {
			if ( submitted || seconds <= 0 ) {
				window.clearInterval( tick );
				return;
			}

			seconds -= 1;
			render();

			if ( 0 === seconds ) {
				submitted = true;
				form.submit();
			}
		}, 1000 );

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( submitted ) {
				return;
			}

			event.preventDefault();
			event.returnValue = '';
			return '';
		} );

		window.addEventListener( 'pagehide', function () {
			if ( submitted || ! leave ) {
				return;
			}

			if ( navigator.sendBeacon ) {
				navigator.sendBeacon( window.location.href, new FormData( leave ) );
			} else if ( window.fetch ) {
				window.fetch( window.location.href, { method: 'POST', body: new FormData( leave ), keepalive: true } );
			}
		} );
	}

	/**
	 * Question-by-question navigation for the quiz layout.
	 *
	 * Markup: a .sp-quiz root containing .sp-quiz-question panels (only one
	 * .is-active at a time), .sp-quiz-progress-bar, [data-sp-current],
	 * [data-sp-prev] / [data-sp-next] / [data-sp-submit] buttons, and
	 * [data-sp-goto] navigator items in the side panel.
	 */
	function initQuizNav() {
		var root = document.querySelector( '[data-sp-quiz]' );

		if ( ! root ) {
			return;
		}

		var questions = root.querySelectorAll( '.sp-quiz-question' );
		var total     = questions.length;
		var current   = 0;
		var isReview  = !! root.querySelector( '.sp-quiz-navitem.is-right, .sp-quiz-navitem.is-wrong' );

		if ( ! total ) {
			return;
		}

		var prevBtn   = root.querySelector( '[data-sp-prev]' );
		var nextBtn   = root.querySelector( '[data-sp-next]' );
		var submitBtn = root.querySelector( '[data-sp-submit]' );
		var progress  = root.querySelector( '[data-sp-progress]' );
		var counter   = root.querySelector( '[data-sp-current]' );
		var gotoBtns  = root.querySelectorAll( '[data-sp-goto]' );
		var scoreBox  = root.querySelector( '[data-sp-score]' );

		function show( index ) {
			current = Math.max( 0, Math.min( total - 1, index ) );

			questions.forEach( function ( q, i ) {
				q.classList.toggle( 'is-active', i === current );
			} );

			gotoBtns.forEach( function ( btn, i ) {
				btn.classList.toggle( 'is-current', i === current );
			} );

			if ( counter ) {
				counter.textContent = String( current + 1 );
			}

			if ( progress ) {
				progress.style.width = ( ( current + 1 ) / total * 100 ) + '%';
			}

			if ( prevBtn ) {
				prevBtn.disabled = ( 0 === current );
			}

			var last = ( current === total - 1 );

			if ( ! isReview ) {
				if ( nextBtn ) {
					nextBtn.hidden = last;
				}
				if ( submitBtn ) {
					submitBtn.hidden = ! last;
				}
			}
		}

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				show( current - 1 );
			} );
		}

		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				show( current + 1 );
			} );
		}

		gotoBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				show( parseInt( btn.getAttribute( 'data-sp-goto' ), 10 ) || 0 );
			} );
		} );

		// In review mode compute a live score from the marked questions.
		if ( isReview && scoreBox ) {
			var right = 0;

			questions.forEach( function ( q ) {
				if ( '1' === q.getAttribute( 'data-sp-right' ) ) {
					right += 1;
				}
			} );

			var pct = total > 0 ? Math.round( right / total * 100 ) : 0;
			var pctEl = scoreBox.querySelector( '[data-sp-score-pct]' );

			if ( pctEl ) {
				pctEl.textContent = pct + '%';
			}
			scoreBox.classList.add( 'is-visible' );
		}

		show( 0 );
	}

	function run() {
		initKindToggle();
		initAttendanceOptions();
		initUnsavedGuard();
		initPasswordToggles();
		initAuthSubmit();
		initMobileNav();
		initQuizNav();
		initExamRunner();
	}
} )();