/**
 * Finance Invoice tab — repeatable line-item rows + live total preview.
 *
 * Vanilla JS only (no React, no jQuery). Totals are preview-only and are never
 * submitted or persisted; the server recomputes them from the saved line items.
 */
( function () {
	'use strict';

	var table = document.getElementById( 'slate-fi-lines' );
	var template = document.getElementById( 'slate-fi-row-template' );
	var addBtn = document.getElementById( 'slate-fi-add-row' );

	if ( ! table || ! template || ! addBtn ) {
		return;
	}

	var body = table.querySelector( '.slate-fi-lines__body' );

	// Running index so each new row's field names stay unique
	// (line_items[N][field]). Seed above any index already rendered by PHP.
	var nextIndex = ( function () {
		var max = -1;
		body.querySelectorAll( 'input[name^="line_items["]' ).forEach( function ( input ) {
			var match = input.name.match( /^line_items\[(\d+)\]/ );
			if ( match ) {
				max = Math.max( max, parseInt( match[ 1 ], 10 ) );
			}
		} );
		return max + 1;
	} )();

	function recalcTotals() {
		var totals = { msrp: 0, invoice: 0 };
		body.querySelectorAll( 'input.slate-fi-num' ).forEach( function ( input ) {
			var col = input.getAttribute( 'data-col' );
			var value = parseFloat( input.value );
			if ( col && ! isNaN( value ) ) {
				totals[ col ] += value;
			}
		} );

		table.querySelectorAll( '[data-total]' ).forEach( function ( cell ) {
			var key = cell.getAttribute( 'data-total' );
			cell.textContent = totals[ key ].toLocaleString( undefined, {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			} );
		} );
	}

	function addRow() {
		// Clone the template, substituting the __i__ placeholder with the index.
		var html = template.innerHTML.replace( /__i__/g, String( nextIndex ) );
		nextIndex += 1;

		var tmp = document.createElement( 'tbody' );
		tmp.innerHTML = html.trim();
		var row = tmp.querySelector( 'tr' );
		if ( row ) {
			body.appendChild( row );
		}
	}

	function removeRow( row ) {
		row.parentNode.removeChild( row );
		// Always leave at least one row so the form is never empty.
		if ( ! body.querySelector( '.slate-fi-line' ) ) {
			addRow();
		}
		recalcTotals();
	}

	addBtn.addEventListener( 'click', addRow );

	body.addEventListener( 'click', function ( event ) {
		var btn = event.target.closest( '.slate-fi-remove-row' );
		if ( btn ) {
			var row = btn.closest( '.slate-fi-line' );
			if ( row ) {
				removeRow( row );
			}
		}
	} );

	body.addEventListener( 'input', function ( event ) {
		if ( event.target.classList.contains( 'slate-fi-num' ) ) {
			recalcTotals();
		}
	} );
} )();
