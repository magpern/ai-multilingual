/**
 * Term edit Localized URL slug operator (REST-backed, no build step).
 */
( function () {
	'use strict';

	if ( typeof wp === 'undefined' || ! wp.apiFetch || ! wp.domReady ) {
		return;
	}

	var cfg = window.aimlTermSlugAdmin || {};
	wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( cfg.nonce ) );

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( key ) {
			if ( key === 'className' ) {
				node.className = attrs[ key ];
			} else if ( key === 'text' ) {
				node.textContent = attrs[ key ];
			} else if ( key.indexOf( 'on' ) === 0 && typeof attrs[ key ] === 'function' ) {
				node.addEventListener( key.slice( 2 ).toLowerCase(), attrs[ key ] );
			} else if ( attrs[ key ] !== null && attrs[ key ] !== undefined ) {
				node.setAttribute( key, attrs[ key ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( typeof child === 'string' ) {
				node.appendChild( document.createTextNode( child ) );
			} else if ( child ) {
				node.appendChild( child );
			}
		} );
		return node;
	}

	function path( suffix ) {
		return '/' + ( cfg.restNamespace || 'aiml/v1' ) + suffix;
	}

	function defaultLanguageCode() {
		var langs = cfg.languages || [];
		for ( var i = 0; i < langs.length; i++ ) {
			if ( ! langs[ i ].is_default ) {
				return langs[ i ].code;
			}
		}
		return langs[ 0 ] ? langs[ 0 ].code : '';
	}

	function humanError( err ) {
		var code = ( err && err.code ) || '';
		var msg = ( err && err.message ) || cfg.i18n.loadError;
		if (
			code.indexOf( 'collision' ) !== -1 ||
			code === 'aiml_slug_route_collision' ||
			code === 'aiml_slug_history_collision' ||
			code === 'aiml_slug_collision_exhausted'
		) {
			return cfg.i18n.collisionHelp;
		}
		return msg;
	}

	wp.domReady( function () {
		var mount = document.getElementById( 'aiml-term-slug-panel' );
		if ( ! mount ) {
			return;
		}
		var termId = parseInt( mount.getAttribute( 'data-term-id' ) || cfg.termId || '0', 10 );
		var langs = cfg.languages || [];
		var language = defaultLanguageCode();
		if ( ! termId || ! language ) {
			mount.appendChild( el( 'p', { text: cfg.i18n.loadError } ) );
			return;
		}

		var statusEl = el( 'p', { className: 'aiml-term-slug-status' } );
		var candidateInput = el( 'input', { type: 'text', className: 'regular-text', id: 'aiml-term-slug-candidate' } );
		var meta = el( 'p', { className: 'description' } );
		var errEl = el( 'p', { className: 'notice notice-error', style: 'display:none;padding:8px;' } );
		var languageSelect = el( 'select', {
			id: 'aiml-term-slug-language',
			onChange: function ( event ) {
				language = event.target.value;
				refresh();
			},
		} );
		langs.forEach( function ( row ) {
			var opt = el( 'option', {
				value: row.code,
				text: ( row.name || row.code ) + ( row.is_default ? ' (default)' : '' ),
			} );
			if ( row.code === language ) {
				opt.selected = true;
			}
			languageSelect.appendChild( opt );
		} );

		function setError( text ) {
			if ( ! text ) {
				errEl.style.display = 'none';
				errEl.textContent = '';
				return;
			}
			errEl.style.display = 'block';
			errEl.textContent = text;
		}

		function applyView( view ) {
			candidateInput.value = view.slug_candidate || '';
			meta.textContent =
				cfg.i18n.origin +
				': ' +
				( view.slug_origin || '—' ) +
				' · ' +
				cfg.i18n.effective +
				': ' +
				( view.localized_path || view.active_route_slug || '—' ) +
				' · ' +
				cfg.i18n.sync +
				': ' +
				( view.route_sync_state || '—' );
			statusEl.textContent = view.route_publication_blocked_reason
				? cfg.i18n.blocked + ': ' + view.route_publication_blocked_reason
				: '';
			if ( view.collision_adjusted ) {
				setError( cfg.i18n.collisionHelp );
			} else {
				setError( '' );
			}
		}

		function call( method, route, body ) {
			var opts = { path: route + '?language=' + encodeURIComponent( language ), method: method };
			if ( body ) {
				opts.data = body;
			}
			return wp.apiFetch( opts );
		}

		function base() {
			return path( '/workspace/terms/' + termId + '/slug' );
		}

		function refresh() {
			return call( 'GET', base() )
				.then( applyView )
				.catch( function ( e ) {
					setError( humanError( e ) );
				} );
		}

		var actions = el( 'p', {}, [
			el( 'button', {
				type: 'button',
				className: 'button',
				text: cfg.i18n.generate,
				onClick: function () {
					call( 'POST', base() + '/generate' )
						.then( applyView )
						.catch( function ( e ) {
							setError( humanError( e ) );
						} );
				},
			} ),
			document.createTextNode( ' ' ),
			el( 'button', {
				type: 'button',
				className: 'button',
				text: cfg.i18n.save,
				onClick: function () {
					call( 'POST', base(), { slug_candidate: candidateInput.value } )
						.then( applyView )
						.catch( function ( e ) {
							setError( humanError( e ) );
						} );
				},
			} ),
			document.createTextNode( ' ' ),
			el( 'button', {
				type: 'button',
				className: 'button',
				text: cfg.i18n.clear,
				onClick: function () {
					call( 'DELETE', base() )
						.then( applyView )
						.catch( function ( e ) {
							setError( humanError( e ) );
						} );
				},
			} ),
			document.createTextNode( ' ' ),
			el( 'button', {
				type: 'button',
				className: 'button button-primary',
				text: cfg.i18n.publish,
				onClick: function () {
					call( 'POST', base() + '/publish-route' )
						.then( applyView )
						.catch( function ( e ) {
							setError( humanError( e ) );
						} );
				},
			} ),
			document.createTextNode( ' ' ),
			el( 'button', {
				type: 'button',
				className: 'button-link',
				text: cfg.i18n.refresh,
				onClick: refresh,
			} ),
		] );

		mount.appendChild( el( 'h3', { text: cfg.i18n.title } ) );
		mount.appendChild(
			el( 'p', {}, [
				el( 'label', { text: cfg.i18n.language + ': ' } ),
				languageSelect,
			] )
		);
		mount.appendChild( el( 'p', {}, [ el( 'label', { text: cfg.i18n.candidate } ), candidateInput ] ) );
		mount.appendChild( meta );
		mount.appendChild( statusEl );
		mount.appendChild( errEl );
		mount.appendChild( actions );
		refresh();
	} );
} )();
