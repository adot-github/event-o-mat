/**
 * Editor-Blöcke "Event-o-mat".
 *
 * Registriert pro Eintrag aus window.eventOMatBlocks.blocks einen dynamischen
 * Block (Serverausgabe via register.php / do_shortcode). Ein gemeinsames
 * Edit-Bauteil zeigt eine ServerSideRender-Vorschau und die Seitenleiste mit
 * Dropdowns für Kongress, Sprache, Workshop-Typen und ggf. Tempo.
 *
 * Kein Build-Schritt: reines wp.* im globalen Scope, kein JSX.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.blockEditor ) {
		return;
	}

	var h = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = ( wp.i18n && wp.i18n.__ ) || function ( s ) { return s; };

	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var cmp = wp.components || {};
	var ServerSideRender = wp.serverSideRender || cmp.ServerSideRender || null;
	var apiFetch = wp.apiFetch || null;

	var DATA = window.eventOMatBlocks || { blocks: {}, events: [], languages: [] };

	function blockNameFor( tag ) {
		return 'event-o-mat/' + tag.replace( /_/g, '-' );
	}

	function tagFromBlockName( name ) {
		var slug = String( name || '' ).replace( 'event-o-mat/', '' );
		var match = '';
		Object.keys( DATA.blocks ).forEach( function ( tag ) {
			if ( tag.replace( /_/g, '-' ) === slug ) {
				match = tag;
			}
		} );
		return match;
	}

	function paramsFor( tag ) {
		return ( DATA.blocks[ tag ] && DATA.blocks[ tag ].params ) || [];
	}

	function splitIds( value ) {
		return String( value || '' )
			.split( ',' )
			.map( function ( s ) { return s.trim(); } )
			.filter( Boolean );
	}

	function EventOMatEdit( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;

		var tag = tagFromBlockName( props.name );
		var def = DATA.blocks[ tag ] || { label: 'Event-o-mat', icon: 'calendar-alt' };
		var params = paramsFor( tag );
		var wantsType = params.indexOf( 'type' ) !== -1;
		var wantsSpeed = params.indexOf( 'speed' ) !== -1;

		var typesState = useState( [] );
		var wsTypes = typesState[ 0 ];
		var setWsTypes = typesState[ 1 ];
		var loadingState = useState( false );
		var isLoading = loadingState[ 0 ];
		var setIsLoading = loadingState[ 1 ];

		useEffect( function () {
			if ( ! wantsType || ! attributes.eventUid || ! apiFetch ) {
				setWsTypes( [] );
				return;
			}

			var cancelled = false;
			setIsLoading( true );

			apiFetch( {
				path:
					'/event-o-mat/v1/workshop-types?event_uid=' +
					encodeURIComponent( attributes.eventUid ) +
					'&lang=' +
					encodeURIComponent( attributes.lang || 'de' ),
			} )
				.then( function ( res ) {
					if ( cancelled ) { return; }
					setWsTypes( Array.isArray( res ) ? res : [] );
					setIsLoading( false );
				} )
				.catch( function () {
					if ( cancelled ) { return; }
					setWsTypes( [] );
					setIsLoading( false );
				} );

			return function () { cancelled = true; };
		}, [ attributes.eventUid, attributes.lang, wantsType ] );

		// ── Seitenleiste ────────────────────────────────────────────────
		var controls = [];

		var eventOptions = [ { value: '', label: __( '— Kongress wählen —', 'event-registration' ) } ];
		( DATA.events || [] ).forEach( function ( ev ) {
			eventOptions.push( { value: ev.uid, label: ev.label } );
		} );

		controls.push(
			h( cmp.SelectControl, {
				key: 'event',
				label: __( 'Kongress', 'event-registration' ),
				value: attributes.eventUid || '',
				options: eventOptions,
				onChange: function ( v ) { setAttributes( { eventUid: v } ); },
			} )
		);

		if ( ( DATA.languages || [] ).length ) {
			controls.push(
				h( cmp.SelectControl, {
					key: 'lang',
					label: __( 'Sprache', 'event-registration' ),
					value: attributes.lang || 'de',
					options: DATA.languages.map( function ( l ) {
						return { value: l.value, label: l.label };
					} ),
					onChange: function ( v ) { setAttributes( { lang: v } ); },
				} )
			);
		}

		if ( wantsType ) {
			var chosen = splitIds( attributes.type );

			if ( ! attributes.eventUid ) {
				controls.push(
					h( 'p', { key: 'type-hint', className: 'event-o-mat-block__hint' },
						__( 'Zuerst einen Kongress wählen — danach erscheinen die Workshop-Typen.', 'event-registration' ) )
				);
			} else if ( isLoading ) {
				controls.push(
					h( 'p', { key: 'type-loading', className: 'event-o-mat-block__hint' },
						__( 'Workshop-Typen werden geladen …', 'event-registration' ) )
				);
			} else if ( ! wsTypes.length ) {
				controls.push(
					h( 'p', { key: 'type-empty', className: 'event-o-mat-block__hint' },
						__( 'Keine Workshop-Typen für diesen Kongress.', 'event-registration' ) )
				);
			} else {
				controls.push(
					h( 'p', { key: 'type-label', className: 'event-o-mat-block__field-label' },
						__( 'Workshop-Typen (nichts angehakt = alle)', 'event-registration' ) )
				);
				wsTypes.forEach( function ( t ) {
					var id = String( t.id );
					controls.push(
						h( cmp.CheckboxControl, {
							key: 'type-' + id,
							label: t.name,
							checked: chosen.indexOf( id ) !== -1,
							onChange: function ( isChecked ) {
								var next = splitIds( attributes.type ).filter( function ( x ) { return x !== id; } );
								if ( isChecked ) { next.push( id ); }
								setAttributes( { type: next.join( ',' ) } );
							},
						} )
					);
				} );
			}
		}

		if ( wantsSpeed ) {
			controls.push(
				h( cmp.RangeControl, {
					key: 'speed',
					label: __( 'Tempo: Sekunden pro Durchlauf', 'event-registration' ),
					value: attributes.speed || 60,
					min: 5,
					max: 240,
					onChange: function ( v ) { setAttributes( { speed: v || 60 } ); },
				} )
			);
		}

		// ── Editor-Körper ──────────────────────────────────────────────
		var body;

		if ( ! attributes.eventUid ) {
			body = h( cmp.Placeholder, {
				icon: def.icon || 'calendar-alt',
				label: def.label,
				instructions: __( 'Rechts in der Seitenleiste einen Kongress auswählen.', 'event-registration' ),
			} );
		} else if ( ServerSideRender ) {
			body = h( 'div', { className: 'event-o-mat-block__preview' },
				h( 'div', { className: 'event-o-mat-block__badge' }, def.label ),
				h( ServerSideRender, {
					block: blockNameFor( tag ),
					attributes: attributes,
				} )
			);
		} else {
			body = h( 'p', {}, def.label );
		}

		return h( Fragment, {},
			h( InspectorControls, {},
				h( cmp.PanelBody, { title: __( 'Einstellungen', 'event-registration' ), initialOpen: true }, controls )
			),
			h( 'div', useBlockProps(), body )
		);
	}

	Object.keys( DATA.blocks ).forEach( function ( tag ) {
		var def = DATA.blocks[ tag ];

		registerBlockType( blockNameFor( tag ), {
			apiVersion: 2,
			title: def.label,
			description: def.description || '',
			category: 'event-o-mat',
			icon: def.icon || 'calendar-alt',
			keywords: [ 'event-o-mat', 'event', 'kongress', 'shortcode' ],
			supports: { html: false, reusable: true, multiple: true },
			attributes: {
				eventUid: { type: 'string', default: '' },
				lang: { type: 'string', default: 'de' },
				type: { type: 'string', default: '' },
				speed: { type: 'number', default: 60 },
			},
			edit: EventOMatEdit,
			save: function () { return null; },
		} );
	} );
} )( window.wp );
