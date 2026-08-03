<?php
/**
 * Auto-translate boundary for the translator workspace.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;

/**
 * Single entry point for workspace auto-translate. F10.6 wires a real provider.
 */
final class TranslationService {

	/**
	 * Injected dependency.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Injected dependency.
	 *
	 * @var SegmentAssembler
	 */
	private SegmentAssembler $assembler;

	/**
	 * Builds the collaborator.
	 *
	 * @param Store            $store     Segment store.
	 * @param SegmentAssembler $assembler Segment assembler.
	 */
	public function __construct( Store $store, SegmentAssembler $assembler ) {
		$this->store     = $store;
		$this->assembler = $assembler;
	}

	/**
	 * Translates one segment synchronously when a provider is configured.
	 *
	 * @param WP_Post $post        Canonical post.
	 * @param int     $language_id Target language id.
	 * @param string  $segment_key Segment key.
	 * @return array<string, mixed>|WP_Error Updated segment DTO or error.
	 */
	public function translate_segment( WP_Post $post, int $language_id, string $segment_key ) {
		unset( $post, $language_id, $segment_key );

		return new WP_Error(
			'aiml_ai_not_configured',
			__( 'Automatic translation is not configured.', 'ai-multilingual' ),
			array( 'status' => 503 )
		);
	}
}
