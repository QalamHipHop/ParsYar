/* ParsYar admin bootstrap — lightweight shell.
   Real SPA is delivered by the enterprise-theme React bundle. */
( function () {
	'use strict';

	const root = document.getElementById( 'pars-yar-app' );
	if ( ! root ) {
		return;
	}

	root.innerHTML = '<p class="pars-yar-loading">در حال بارگذاری داشبورد…</p>';

	// If the theme's React bundle is loaded, it will replace #pars-yar-app.
	// Otherwise we render a basic table from the REST API.
	if ( typeof window.parsYar === 'undefined' ) {
		return;
	}

	const view = root.getAttribute( 'data-view' ) || 'Dashboard';

	if ( view === 'Audit' ) {
		renderAudit();
		return;
	}

	if ( [ 'Contact', 'Lead', 'Account' ].indexOf( view ) === -1 ) {
		renderDashboard();
		return;
	}

	renderList( view );
} )();

function renderDashboard() {
	const root = document.getElementById( 'pars-yar-app' );
	root.innerHTML = '<h2>داشبورد</h2><p>به پارس‌یار خوش آمدید. از منوی سمت راست، بخش مورد نظر را انتخاب کنید.</p>';
}

function renderAudit() {
	const root = document.getElementById( 'pars-yar-app' );
	root.innerHTML = '<h2>گزارش حسابرسی</h2><p>گزارش کامل از طریق REST API در دسترس است: <code>/wp-json/pars-yar/v1/audit</code></p>';
}

function renderList( objectName ) {
	const root = document.getElementById( 'pars-yar-app' );
	root.innerHTML = '<p class="pars-yar-loading">در حال دریافت اطلاعات…</p>';

	fetch( window.parsYar.rest + 'objects/' + objectName + '/records?limit=50', {
		headers: { 'X-WP-Nonce': window.parsYar.nonce }
	} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( data ) {
			if ( ! data || ! data.items || data.items.length === 0 ) {
				root.innerHTML = '<p>هیچ رکوردی یافت نشد.</p>';
				return;
			}
			const keys = Object.keys( data.items[ 0 ] ).filter( function ( k ) { return k !== 'id'; } );
			let html = '<table class="widefat striped"><thead><tr><th>#</th>';
			keys.forEach( function ( k ) { html += '<th>' + escapeHtml( k ) + '</th>'; } );
			html += '</tr></thead><tbody>';
			data.items.forEach( function ( row ) {
				html += '<tr><td>' + row.id + '</td>';
				keys.forEach( function ( k ) { html += '<td>' + escapeHtml( row[ k ] || '' ) + '</td>'; } );
				html += '</tr>';
			} );
			html += '</tbody></table>';
			root.innerHTML = html;
		} )
		.catch( function ( err ) {
			root.innerHTML = '<p style="color:red;">خطا: ' + escapeHtml( err.message ) + '</p>';
		} );
}

function escapeHtml( s ) {
	if ( s === null || s === undefined ) { return ''; }
	return String( s )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#039;' );
}
