/**
 * Strategy F block attribute registration (editor mirror).
 *
 * Mirrors PHP block_type_metadata registration so Gutenberg preserves aimlBlockId
 * through ordinary edits (Spike S5 Phase 3 evidence).
 */
( function ( wp, config ) {
	if ( ! wp || ! wp.hooks || ! wp.blocks || ! config ) {
		return;
	}

	var supportedBlocks = Array.isArray( config.supportedBlocks ) ? config.supportedBlocks : [];
	var attrName = config.attrName || 'aimlBlockId';
	var attrDefinition = config.attrDefinition || { type: 'string' };

	function addAttribute( settings, blockName ) {
		if ( supportedBlocks.indexOf( blockName ) === -1 ) {
			return settings;
		}

		settings.attributes = settings.attributes || {};
		settings.attributes[ attrName ] = attrDefinition;

		return settings;
	}

	wp.hooks.addFilter(
		'blocks.registerBlockType',
		'ai-multilingual/block-attr',
		addAttribute
	);
}( window.wp, window.aimlBlockEditor ) );
