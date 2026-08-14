<?php
/**
 * Object/language public eligibility for prepared routes (MSEO.1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Language\Languages;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;

/**
 * Compositional eligibility — not a second TI.7 policy engine.
 */
final class ObjectLanguagePublicEligibility {

	/**
	 * Constructs the service.
	 *
	 * @param Store                     $store      Store.
	 * @param Languages                 $languages  Languages.
	 * @param RoutingCapabilityRegistry $capabilities Capabilities.
	 */
	public function __construct(
		private Store $store,
		private Languages $languages,
		private RoutingCapabilityRegistry $capabilities
	) {
	}

	/**
	 * Whether a prepared route may be published for this object/language.
	 *
	 * @param WP_Post $post        Source post.
	 * @param int     $language_id Language id.
	 * @return true|WP_Error
	 */
	public function is_route_publishable( WP_Post $post, int $language_id ) {
		$lang = $this->languages->find( $language_id );
		if ( null === $lang || Languages::STATUS_PUBLISHED !== (string) ( $lang->status ?? '' ) ) {
			return new WP_Error( 'aiml_slug_language_not_published', __( 'Language is not published.', 'ai-multilingual' ) );
		}

		if ( ! in_array( (string) $post->post_status, array( 'publish', 'private' ), true ) ) {
			return new WP_Error( 'aiml_slug_source_not_public', __( 'Source object is not publicly available.', 'ai-multilingual' ) );
		}

		if ( ! $this->capabilities->supports_post( $post ) ) {
			return new WP_Error( 'aiml_slug_capability_unsupported', __( 'Routing capability does not support this object.', 'ai-multilingual' ) );
		}

		if ( ! $this->has_overlay_bundle( (int) $post->ID, $language_id ) ) {
			return new WP_Error( 'aiml_slug_overlay_bundle_empty', __( 'Object/language has no overlay-eligible segments.', 'ai-multilingual' ) );
		}

		$candidate = $this->store->get( Store::SOURCE_POST, (int) $post->ID, $language_id, Extractor::FIELD_SLUG );
		$text      = is_object( $candidate ) ? trim( (string) ( $candidate->translated_text ?? '' ) ) : '';
		if ( '' === $text || ( is_object( $candidate ) && Store::STATUS_MISSING === (string) ( $candidate->status ?? '' ) ) ) {
			return new WP_Error( 'aiml_slug_candidate_missing', __( 'Slug candidate is missing.', 'ai-multilingual' ) );
		}

		return true;
	}

	/**
	 * Discoverability is false while public routing is off (MSEO.1).
	 *
	 * @param WP_Post $post        Unused.
	 * @param int     $language_id Unused.
	 */
	public function is_discoverable( WP_Post $post, int $language_id ): bool {
		unset( $post, $language_id );

		return false;
	}

	/**
	 * Helper.
	 *
	 * @param int $source_id   Post id.
	 * @param int $language_id Language id.
	 */
	private function has_overlay_bundle( int $source_id, int $language_id ): bool {
		$map = $this->store->load_object( Store::SOURCE_POST, $source_id, $language_id );
		if ( ! is_array( $map ) ) {
			return false;
		}

		foreach ( $map as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			if ( Extractor::FIELD_SLUG === (string) ( $row->segment_key ?? '' ) ) {
				continue;
			}
			if ( Store::is_publicly_overlay_eligible( $row ) ) {
				return true;
			}
		}

		return false;
	}
}
