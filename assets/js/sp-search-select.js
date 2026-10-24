/*
 * Student Portal — reusable searchable select (premium dropdown).
 *
 * Progressive enhancement for any <select data-sp-search-select>:
 *   - renders a trigger button showing the current option,
 *   - opens a popover with a live filter input and the option list,
 *   - keeps the native <select> in the DOM (hidden) so form posts and any
 *     existing `change` listeners (e.g. id-card.js) keep working,
 *   - keyboard: Enter/Space/ArrowDown open, arrows move, Enter picks,
 *     Escape closes, typing filters,
 *   - re-scans after async region swaps (sp:async-replace).
 *
 * Markup contract (opt-in per select):
 *   <select data-sp-search-select>…</select>
 *   Optional: data-sp-ss-placeholder="Search students…" (filter placeholder)
 *             data-sp-ss-empty="No matching students"   (empty-state copy)
 *
 * Self-contained IIFE, no dependencies.
 */
( function () {
	'use strict';

	var SELECTOR = 'select[data-sp-search-select]';

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function esc( text ) {
		return String( text )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function chevronSvg() {
		return '<svg class="sp-ss-chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
	}

	function searchSvg() {
		return '<svg class="sp-ss-search-icon" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>';
	}

	/**
	 * One enhanced select.
	 *
	 * @param {HTMLSelectElement} select Native select to enhance.
	 */
	function SearchSelect( select ) {
		this.select = select;
		this.options = Array.prototype.slice.call( select.options );
		this.activeIndex = -1;
		this.visible = [];
		this.build();
	}

	SearchSelect.prototype.build = function () {
		var self = this;
		var select = this.select;

		select.setAttribute( 'data-sp-ss-ready', '1' );

		// Wrapper replaces the select visually; the select stays for forms.
		this.wrap = document.createElement( 'div' );
		this.wrap.className = 'sp-ss';

		this.trigger = document.createElement( 'button' );
		this.trigger.type = 'button';
		this.trigger.className = 'sp-ss-trigger';
		this.trigger.setAttribute( 'aria-haspopup', 'listbox' );
		this.trigger.setAttribute( 'aria-expanded', 'false' );
		this.trigger.innerHTML =
			'<span class="sp-ss-label"></span>' + chevronSvg();

		this.pop = document.createElement( 'div' );
		this.pop.className = 'sp-ss-pop';
		this.pop.hidden = true;

		var searchWrap = document.createElement( 'div' );
		searchWrap.className = 'sp-ss-search';
		searchWrap.innerHTML =
			searchSvg() +
			'<input type="search" class="sp-ss-input" autocomplete="off" spellcheck="false" ' +
			'placeholder="' + esc( select.getAttribute( 'data-sp-ss-placeholder' ) || 'Search…' ) + '" ' +
			'aria-label="' + esc( select.getAttribute( 'data-sp-ss-placeholder' ) || 'Search options' ) + '" />';

		this.input = searchWrap.querySelector( 'input' );

		this.list = document.createElement( 'ul' );
		this.list.className = 'sp-ss-list';
		this.list.setAttribute( 'role', 'listbox' );

		this.empty = document.createElement( 'p' );
		this.empty.className = 'sp-ss-empty';
		this.empty.hidden = true;
		this.empty.textContent = select.getAttribute( 'data-sp-ss-empty' ) || 'No matches found';

		this.pop.appendChild( searchWrap );
		this.pop.appendChild( this.list );
		this.pop.appendChild( this.empty );

		this.wrap.appendChild( this.trigger );
		this.wrap.appendChild( this.pop );

		// Hide the native select but keep it submittable and changeable.
		select.parentNode.insertBefore( this.wrap, select );
		select.classList.add( 'sp-ss-native' );

		// The trigger carries its own instance for the page-level closers.
		this.trigger.__spSS = this;

		this.renderList( '' );
		this.syncLabel();

		this.trigger.addEventListener( 'click', function () {
			self.toggle();
		} );

		this.input.addEventListener( 'input', function () {
			self.renderList( self.input.value );
			self.moveActive( 1 );
		} );

		this.input.addEventListener( 'keydown', function ( event ) {
			self.onKey( event );
		} );

		this.trigger.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
				event.preventDefault();
				self.open();
			}
		} );

		this.list.addEventListener( 'click', function ( event ) {
			var li = event.target.closest( '.sp-ss-opt' );
			if ( li && ! li.classList.contains( 'is-disabled' ) ) {
				self.pick( parseInt( li.getAttribute( 'data-index' ), 10 ) );
			}
		} );

		select.addEventListener( 'change', function () {
			self.syncLabel();
		} );

		// Clicking the <label for=…> should open our popover, since the
		// native select itself is hidden.
		if ( select.id ) {
			Array.prototype.forEach.call(
				document.querySelectorAll( 'label[for="' + select.id.replace( /"/g, '\\"' ) + '"]' ),
				function ( label ) {
					label.addEventListener( 'click', function ( event ) {
						event.preventDefault();
						self.open( true );
					} );
				}
			);
		}
	};

	/** Rebuild option rows for the current filter query. */
	SearchSelect.prototype.renderList = function ( query ) {
		var self = this;
		var q = String( query || '' ).trim().toLowerCase();

		this.list.innerHTML = '';
		this.visible = [];

		this.options.forEach( function ( option, index ) {
			var text = ( option.textContent || '' ).replace( /\s+/g, ' ' ).trim();
			var haystack = ( text + ' ' + ( option.getAttribute( 'data-search' ) || '' ) ).toLowerCase();

			if ( q && -1 === haystack.indexOf( q ) ) {
				return;
			}

			var li = document.createElement( 'li' );
			li.className = 'sp-ss-opt';
			li.setAttribute( 'role', 'option' );
			li.setAttribute( 'data-index', String( index ) );

			if ( option.disabled ) {
				li.classList.add( 'is-disabled' );
				li.setAttribute( 'aria-disabled', 'true' );
			}

			if ( option.value === self.select.value ) {
				li.classList.add( 'is-selected' );
				li.setAttribute( 'aria-selected', 'true' );
			}

			li.innerHTML =
				'<span class="sp-ss-opt-text">' + esc( text ) + '</span>' +
				( option.value === self.select.value
					? '<svg class="sp-ss-check" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>'
					: '' );

			self.list.appendChild( li );
			self.visible.push( index );
		} );

		this.empty.hidden = 0 !== this.visible.length;

		// Keep the active highlight on the currently selected option.
		var selectedIndex = this.options.findIndex
			? this.options.findIndex( function ( o ) { return o.value === self.select.value; } )
			: -1;

		this.activeIndex = -1;

		if ( -1 !== selectedIndex && -1 !== this.visible.indexOf( selectedIndex ) ) {
			this.setActive( this.visible.indexOf( selectedIndex ), false );
		}
	};

	/** Move the keyboard highlight within the visible options. */
	SearchSelect.prototype.moveActive = function ( step, includeDisabled ) {
		if ( ! this.visible.length ) {
			return;
		}

		var next = this.activeIndex + step;

		while ( next >= 0 && next < this.visible.length ) {
			var option = this.options[ this.visible[ next ] ];

			if ( includeDisabled || ! option.disabled ) {
				this.setActive( next, true );
				return;
			}

			next += step;
		}

		if ( -1 === this.activeIndex ) {
			this.setActive( step > 0 ? 0 : this.visible.length - 1, true );
		}
	};

	SearchSelect.prototype.setActive = function ( visibleIndex, scroll ) {
		var rows = this.list.querySelectorAll( '.sp-ss-opt' );

		Array.prototype.forEach.call( rows, function ( row ) {
			row.classList.remove( 'is-active' );
			row.removeAttribute( 'aria-selected' );
		} );

		this.activeIndex = visibleIndex;

		var row = rows[ visibleIndex ];

		if ( row ) {
			row.classList.add( 'is-active' );
			row.setAttribute( 'aria-selected', 'true' );

			if ( scroll ) {
				var top = row.offsetTop;
				var bottom = top + row.offsetHeight;

				if ( top < this.list.scrollTop ) {
					this.list.scrollTop = top;
				} else if ( bottom > this.list.scrollTop + this.list.clientHeight ) {
					this.list.scrollTop = bottom - this.list.clientHeight;
				}
			}
		}
	};

	/** Enter picks the highlighted row. */
	SearchSelect.prototype.onKey = function ( event ) {
		if ( 'ArrowDown' === event.key ) {
			event.preventDefault();
			this.moveActive( 1 );
		} else if ( 'ArrowUp' === event.key ) {
			event.preventDefault();
			this.moveActive( -1 );
		} else if ( 'Enter' === event.key ) {
			event.preventDefault();

			if ( -1 !== this.activeIndex ) {
				this.pick( this.visible[ this.activeIndex ] );
			}
		} else if ( 'Escape' === event.key ) {
			event.preventDefault();
			this.close( true );
		} else if ( 'Tab' === event.key ) {
			this.close( false );
		}
	};

	/** Commit an option (by its index in the native select). */
	SearchSelect.prototype.pick = function ( index ) {
		var option = this.options[ index ];

		if ( ! option || option.disabled ) {
			return;
		}

		this.select.value = option.value;

		// Notify any existing listeners (id-card preview, async forms…).
		try {
			this.select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} catch ( err ) {
			var evt = document.createEvent( 'HTMLEvents' );
			evt.initEvent( 'change', true, false );
			this.select.dispatchEvent( evt );
		}

		this.syncLabel();
		this.close( true );
	};

	/** Mirror the native value onto the trigger. */
	SearchSelect.prototype.syncLabel = function () {
		var label = this.trigger.querySelector( '.sp-ss-label' );
		var option = this.select.options[ this.select.selectedIndex ];
		var text = option ? ( option.textContent || '' ).replace( /\s+/g, ' ' ).trim() : '';

		label.textContent = text || 'Select…';
		this.trigger.classList.toggle( 'is-placeholder', ! text );
	};

	SearchSelect.prototype.open = function ( focusInput ) {
		var self = this;

		// Only one popover at a time.
		Array.prototype.forEach.call(
			document.querySelectorAll( '.sp-ss-pop:not([hidden])' ),
			function ( openPop ) {
				var owner = openPop.closest( '.sp-ss' );
				if ( owner && owner !== self.wrap ) {
					var btn = owner.querySelector( '.sp-ss-trigger' );
					if ( btn && btn.__spSS ) {
						btn.__spSS.close( false );
					}
				}
			}
		);

		this.pop.hidden = false;
		this.trigger.setAttribute( 'aria-expanded', 'true' );
		this.wrap.classList.add( 'is-open' );

		this.input.value = '';
		this.renderList( '' );

		// Flip above the trigger when there is no room below.
		var rect = this.trigger.getBoundingClientRect();
		var roomBelow = window.innerHeight - rect.bottom;

		this.wrap.classList.toggle( 'sp-ss-dropup', roomBelow < 260 && rect.top > roomBelow );

		if ( false !== focusInput ) {
			this.input.focus();
		}
	};

	SearchSelect.prototype.close = function ( returnFocus ) {
		this.pop.hidden = true;
		this.trigger.setAttribute( 'aria-expanded', 'false' );
		this.wrap.classList.remove( 'is-open', 'sp-ss-dropup' );

		if ( returnFocus ) {
			this.trigger.focus();
		}
	};

	SearchSelect.prototype.toggle = function () {
		if ( this.pop.hidden ) {
			this.open();
		} else {
			this.close( true );
		}
	};

	// ── Wiring ───────────────────────────────────────────────────────────────

	function upgrade( select ) {
		if ( select.getAttribute( 'data-sp-ss-ready' ) ) {
			return;
		}

		new SearchSelect( select );
	}

	function scan() {
		Array.prototype.forEach.call( document.querySelectorAll( SELECTOR ), upgrade );
	}

	ready( function () {
		scan();

		// Close on outside click / Escape, page-wide.
		document.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '.sp-ss' ) ) {
				return;
			}

			Array.prototype.forEach.call(
				document.querySelectorAll( '.sp-ss.is-open' ),
				function ( wrap ) {
					var btn = wrap.querySelector( '.sp-ss-trigger' );
					if ( btn && btn.__spSS ) {
						btn.__spSS.close( false );
					}
				}
			);
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' !== event.key ) {
				return;
			}

			Array.prototype.forEach.call(
				document.querySelectorAll( '.sp-ss.is-open' ),
				function ( wrap ) {
					var btn = wrap.querySelector( '.sp-ss-trigger' );
					if ( btn && btn.__spSS ) {
						btn.__spSS.close( true );
					}
				}
			);
		} );

		// Async region swaps bring in fresh selects.
		document.addEventListener( 'sp:async-replace', scan );
		document.addEventListener( 'sp:async-done', scan );
	} );
} )();
