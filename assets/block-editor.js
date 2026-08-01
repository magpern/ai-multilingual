/**
 * Strategy F block attribute registration (editor mirror).
 *
 * Mirrors PHP block_type_metadata registration so Gutenberg preserves aimlBlockId
 * through ordinary edits (Spike S5 Phase 3 evidence).
 */
( function ( wp, config ) {
	if ( ! wp || ! wp.hooks || ! wp.blocks || ! wp.data || ! config ) {
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

	/**
	 * After a successful save, re-parse blocks from the persisted entity content.
	 *
	 * SavePipeline may inject aimlBlockId server-side on the first canonical save
	 * while the in-memory editor still holds blocks without that attribute. Without
	 * this sync, the next save would omit aimlBlockId and trigger a new UUID.
	 */
	function rawContentFromRecord( record ) {
		if ( ! record || ! record.content ) {
			return '';
		}

		if ( typeof record.content === 'string' ) {
			return record.content;
		}

		if ( record.content.raw ) {
			return record.content.raw;
		}

		return '';
	}

	function syncBlocksFromSavedContent() {
		var editorSelect = wp.data.select( 'core/editor' );
		var blockDispatch = wp.data.dispatch( 'core/block-editor' );

		if ( ! editorSelect || ! blockDispatch ) {
			return;
		}

		var postType = editorSelect.getCurrentPostType();
		var postId = editorSelect.getCurrentPostId();

		if ( ! postType || ! postId ) {
			return;
		}

		var record = wp.data.select( 'core' ).getEditedEntityRecord( 'postType', postType, postId );
		var content = rawContentFromRecord( record );

		if ( ! content || content.indexOf( '"' + attrName + '"' ) === -1 ) {
			return;
		}

		blockDispatch.resetBlocks( wp.blocks.parse( content ) );
	}

	var wasSaving = false;

	wp.data.subscribe( function () {
		var editorSelect = wp.data.select( 'core/editor' );

		if ( ! editorSelect ) {
			return;
		}

		var saving = editorSelect.isSavingPost();

		if ( wasSaving && ! saving && editorSelect.didPostSaveRequestSucceed() ) {
			syncBlocksFromSavedContent();
		}

		wasSaving = saving;
	} );
}( window.wp, window.aimlBlockEditor ) );
