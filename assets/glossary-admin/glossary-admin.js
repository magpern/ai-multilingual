/**
 * Glossary admin UI (REST-backed, no build step).
 */
( function () {
	'use strict';

	if ( typeof wp === 'undefined' || ! wp.apiFetch || ! wp.domReady ) {
		return;
	}

	var cfg = window.aimlGlossaryAdmin || {};
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

	function defaultSourceId() {
		var langs = cfg.languages || [];
		for ( var i = 0; i < langs.length; i++ ) {
			if ( langs[ i ].is_default ) {
				return String( langs[ i ].language_id );
			}
		}
		return langs[ 0 ] ? String( langs[ 0 ].language_id ) : '';
	}

	function defaultTargetId() {
		var langs = cfg.languages || [];
		var source = defaultSourceId();
		for ( var i = 0; i < langs.length; i++ ) {
			if ( String( langs[ i ].language_id ) !== source ) {
				return String( langs[ i ].language_id );
			}
		}
		return source;
	}

	function optionList( selected ) {
		return ( cfg.languages || [] ).map( function ( lang ) {
			return el( 'option', {
				value: String( lang.language_id ),
				selected: String( lang.language_id ) === String( selected ) ? 'selected' : null,
				text: lang.name + ' (' + lang.code + ')',
			} );
		} );
	}

	wp.domReady( function () {
		var root = document.getElementById( 'aiml-glossary-admin-root' );
		if ( ! root ) {
			return;
		}

		var state = {
			items: [],
			total: 0,
			version: 0,
			q: '',
			message: '',
			error: '',
		};

		var form = {
			source_lang_id: defaultSourceId(),
			target_lang_id: defaultTargetId(),
			source_term: '',
			target_term: '',
			context: '',
			description: '',
		};

		function setMsg( text, isError ) {
			state.message = isError ? '' : text;
			state.error = isError ? text : '';
			render();
		}

		function load() {
			var query = '?per_page=50';
			if ( state.q ) {
				query += '&q=' + encodeURIComponent( state.q );
			}
			wp.apiFetch( { path: path( '/glossary' ) + query } )
				.then( function ( data ) {
					state.items = data.items || [];
					state.total = data.total || 0;
					state.version = data.version || 0;
					state.error = '';
					render();
				} )
				.catch( function () {
					setMsg( ( cfg.i18n && cfg.i18n.loadError ) || 'Load failed', true );
				} );
		}

		function createTerm( event ) {
			event.preventDefault();
			wp.apiFetch( {
				path: path( '/glossary' ),
				method: 'POST',
				data: {
					source_lang_id: parseInt( form.source_lang_id, 10 ),
					target_lang_id: parseInt( form.target_lang_id, 10 ),
					source_term: form.source_term,
					target_term: form.target_term,
					context: form.context,
					description: form.description,
					is_active: true,
				},
			} )
				.then( function () {
					form.source_term = '';
					form.target_term = '';
					form.context = '';
					form.description = '';
					setMsg( 'Term created.', false );
					load();
				} )
				.catch( function ( err ) {
					var msg = ( err && err.message ) || ( cfg.i18n && cfg.i18n.saveError ) || 'Save failed';
					setMsg( msg, true );
				} );
		}

		function toggle( id, active ) {
			var suffix = active ? '/activate' : '/deactivate';
			wp.apiFetch( { path: path( '/glossary/' + id + suffix ), method: 'POST' } )
				.then( load )
				.catch( function () {
					setMsg( ( cfg.i18n && cfg.i18n.saveError ) || 'Save failed', true );
				} );
		}

		function remove( id ) {
			if ( ! window.confirm( 'Delete this glossary term?' ) ) {
				return;
			}
			wp.apiFetch( { path: path( '/glossary/' + id ), method: 'DELETE' } )
				.then( load )
				.catch( function () {
					setMsg( ( cfg.i18n && cfg.i18n.deleteError ) || 'Delete failed', true );
				} );
		}

		function render() {
			root.innerHTML = '';

			if ( state.error ) {
				root.appendChild( el( 'div', { className: 'notice notice-error' }, [ el( 'p', { text: state.error } ) ] ) );
			}
			if ( state.message ) {
				root.appendChild( el( 'div', { className: 'notice notice-success is-dismissible' }, [ el( 'p', { text: state.message } ) ] ) );
			}

			root.appendChild(
				el( 'p', { className: 'aiml-glossary-meta', text: 'Lexicon version: ' + state.version + ' · Terms: ' + state.total } )
			);

			var search = el( 'input', {
				type: 'search',
				className: 'regular-text',
				placeholder: 'Search terms',
				value: state.q,
				onInput: function ( e ) {
					state.q = e.target.value;
				},
			} );
			root.appendChild(
				el( 'div', { className: 'aiml-glossary-toolbar' }, [
					search,
					el( 'button', {
						type: 'button',
						className: 'button',
						text: 'Search',
						onClick: function () {
							load();
						},
					} ),
				] )
			);

			var createForm = el( 'form', { className: 'aiml-glossary-form', onSubmit: createTerm }, [
				el( 'h2', { text: 'Add term' } ),
				el( 'p', {}, [
					el( 'label', { text: 'Source language ' } ),
					el(
						'select',
						{
							onChange: function ( e ) {
								form.source_lang_id = e.target.value;
							},
						},
						optionList( form.source_lang_id )
					),
				] ),
				el( 'p', {}, [
					el( 'label', { text: 'Target language ' } ),
					el(
						'select',
						{
							onChange: function ( e ) {
								form.target_lang_id = e.target.value;
							},
						},
						optionList( form.target_lang_id )
					),
				] ),
				el( 'p', {}, [
					el( 'label', { text: 'Source term ' } ),
					el( 'input', {
						type: 'text',
						className: 'regular-text',
						required: 'required',
						value: form.source_term,
						onInput: function ( e ) {
							form.source_term = e.target.value;
						},
					} ),
				] ),
				el( 'p', {}, [
					el( 'label', { text: 'Target term ' } ),
					el( 'input', {
						type: 'text',
						className: 'regular-text',
						required: 'required',
						value: form.target_term,
						onInput: function ( e ) {
							form.target_term = e.target.value;
						},
					} ),
				] ),
				el( 'p', {}, [
					el( 'label', { text: 'Context ' } ),
					el( 'input', {
						type: 'text',
						className: 'regular-text',
						value: form.context,
						onInput: function ( e ) {
							form.context = e.target.value;
						},
					} ),
				] ),
				el( 'p', {}, [
					el( 'button', { type: 'submit', className: 'button button-primary', text: 'Create term' } ),
				] ),
			] );
			root.appendChild( createForm );

			if ( ! state.items.length ) {
				root.appendChild(
					el( 'p', { text: ( cfg.i18n && cfg.i18n.empty ) || 'No glossary terms yet.' } )
				);
				return;
			}

			var tbody = el( 'tbody' );
			state.items.forEach( function ( item ) {
				tbody.appendChild(
					el( 'tr', {}, [
						el( 'td', { text: String( item.glossary_id ) } ),
						el( 'td', { text: item.source_term } ),
						el( 'td', { text: item.target_term } ),
						el( 'td', { text: item.source_lang_id + ' → ' + item.target_lang_id } ),
						el( 'td', { text: item.is_active ? 'active' : 'inactive' } ),
						el( 'td', { className: 'aiml-glossary-actions' }, [
							el( 'button', {
								type: 'button',
								className: 'button button-small',
								text: item.is_active ? 'Deactivate' : 'Activate',
								onClick: function () {
									toggle( item.glossary_id, ! item.is_active );
								},
							} ),
							el( 'button', {
								type: 'button',
								className: 'button button-small',
								text: 'Delete',
								onClick: function () {
									remove( item.glossary_id );
								},
							} ),
						] ),
					] )
				);
			} );

			root.appendChild(
				el( 'table', { className: 'widefat striped aiml-glossary-table' }, [
					el( 'thead', {}, [
						el( 'tr', {}, [
							el( 'th', { text: 'ID' } ),
							el( 'th', { text: 'Source' } ),
							el( 'th', { text: 'Target' } ),
							el( 'th', { text: 'Pair' } ),
							el( 'th', { text: 'Status' } ),
							el( 'th', { text: 'Actions' } ),
						] ),
					] ),
					tbody,
				] )
			);
		}

		render();
		load();
	} );
} )();
