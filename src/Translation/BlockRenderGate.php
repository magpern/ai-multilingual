<?php
/**
 * Strategy F frontend block render gate.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\UuidValidator;
use AIMultilingual\Language\LanguageResolver;
use AIMultilingual\Language\Languages;
use AIMultilingual\Rollout\RolloutRenderGateBridge;
use WP_Post;

/**
 * Explicit allow/deny gate before Store lookup or block rendering.
 *
 * Default is deny; unknown context is deny.
 */
final class BlockRenderGate {

	public const REASON_FRONTEND_DISABLED     = 'frontend_rendering_disabled';
	public const REASON_EXTRACTION_DISABLED   = 'extraction_disabled';
	public const REASON_REGISTRATION_DISABLED = 'uuid_registration_disabled';
	public const REASON_INJECTION_DISABLED    = 'uuid_injection_disabled';
	public const REASON_ADMIN                 = 'admin_request';
	public const REASON_BLOCK_EDITOR          = 'block_editor_request';
	public const REASON_REST                  = 'rest_request';
	public const REASON_AJAX                  = 'ajax_request';
	public const REASON_CRON                  = 'cron_request';
	public const REASON_FEED                  = 'feed_request';
	public const REASON_PREVIEW               = 'preview_request';
	public const REASON_UNSUPPORTED_POST_TYPE = 'unsupported_post_type';
	public const REASON_ELEMENTOR_BODY        = 'elementor_body';
	public const REASON_NON_BLOCK_CONTENT     = 'non_block_content';
	public const REASON_MISSING_SOURCE_POST   = 'missing_source_post';
	public const REASON_SOURCE_LANGUAGE       = 'source_language';
	public const REASON_UNRESOLVED_LANGUAGE   = 'unresolved_target_language';
	public const REASON_UNSUPPORTED_LANGUAGE  = 'unsupported_language';
	public const REASON_RECURSION             = 'rendering_recursion';
	public const REASON_INCOMPLETE_IDENTITY   = 'incomplete_identity_continuity';

	/**
	 * @param RolloutRenderGateBridge|null $rollout_bridge Optional rollout layer (F12).
	 */
	public function __construct(
		private ?RolloutRenderGateBridge $rollout_bridge = null,
	) {
	}

	/**
	 * Evaluates whether frontend block rendering may proceed.
	 *
	 * @param RenderGateContext $context Request facts.
	 */
	public function evaluate( RenderGateContext $context ): RenderGateDecision {
		if ( $context->rendering_active ) {
			return RenderGateDecision::deny( self::REASON_RECURSION );
		}

		if ( ! $context->settings->block_frontend_rendering_enabled() ) {
			return RenderGateDecision::deny( self::REASON_FRONTEND_DISABLED );
		}

		if ( ! $context->settings->block_extraction_enabled() ) {
			return RenderGateDecision::deny( self::REASON_EXTRACTION_DISABLED );
		}

		if ( ! $context->settings->block_attr_registration_enabled() ) {
			return RenderGateDecision::deny( self::REASON_REGISTRATION_DISABLED );
		}

		if ( ! $context->settings->block_uuid_injection_enabled() ) {
			return RenderGateDecision::deny( self::REASON_INJECTION_DISABLED );
		}

		if ( $context->is_admin ) {
			return RenderGateDecision::deny( self::REASON_ADMIN );
		}

		if ( $context->is_block_editor ) {
			return RenderGateDecision::deny( self::REASON_BLOCK_EDITOR );
		}

		if ( $context->is_rest ) {
			return RenderGateDecision::deny( self::REASON_REST );
		}

		if ( $context->is_ajax ) {
			return RenderGateDecision::deny( self::REASON_AJAX );
		}

		if ( $context->is_cron ) {
			return RenderGateDecision::deny( self::REASON_CRON );
		}

		if ( $context->is_feed ) {
			return RenderGateDecision::deny( self::REASON_FEED );
		}

		if ( $context->is_preview ) {
			return RenderGateDecision::deny( self::REASON_PREVIEW );
		}

		if ( ! $context->post instanceof WP_Post ) {
			return RenderGateDecision::deny( self::REASON_MISSING_SOURCE_POST );
		}

		if ( ! in_array( (string) $context->post->post_type, RenderGateContext::SUPPORTED_POST_TYPES, true ) ) {
			return RenderGateDecision::deny( self::REASON_UNSUPPORTED_POST_TYPE );
		}

		if ( $context->language->is_default() ) {
			return RenderGateDecision::deny( self::REASON_SOURCE_LANGUAGE );
		}

		if ( 0 === $context->language->current_id() ) {
			return RenderGateDecision::deny( self::REASON_UNRESOLVED_LANGUAGE );
		}

		$current = $context->language->current();
		if ( null !== $current ) {
			$resolver = new LanguageResolver();
			if ( ! $resolver->is_routable( $current, false ) ) {
				return RenderGateDecision::deny( self::REASON_UNSUPPORTED_LANGUAGE );
			}

			if ( Languages::STATUS_DISABLED === (string) ( $current->status ?? '' ) ) {
				return RenderGateDecision::deny( self::REASON_UNSUPPORTED_LANGUAGE );
			}
		}

		$body_status = $context->extractor->body_status( $context->post );

		if ( Extractor::BODY_ELEMENTOR === $body_status ) {
			return RenderGateDecision::deny( self::REASON_ELEMENTOR_BODY );
		}

		if ( Extractor::BODY_BLOCKS !== $body_status ) {
			return RenderGateDecision::deny( self::REASON_NON_BLOCK_CONTENT );
		}

		if ( ! function_exists( 'has_blocks' ) || ! has_blocks( $context->content ) ) {
			return RenderGateDecision::deny( self::REASON_NON_BLOCK_CONTENT );
		}

		if ( $this->has_duplicate_uuids( $context->content ) ) {
			return RenderGateDecision::deny( self::REASON_INCOMPLETE_IDENTITY );
		}

		if ( null !== $this->rollout_bridge ) {
			return $this->rollout_bridge->evaluate( $context );
		}

		return RenderGateDecision::allow();
	}

	/**
	 * Whether the content contains duplicate persistent UUIDs.
	 *
	 * Same-post duplicate UUIDs break identity continuity and must fall back to
	 * source content for the entire body.
	 *
	 * @param string $content Serialized block content.
	 */
	private function has_duplicate_uuids( string $content ): bool {
		if ( ! function_exists( 'parse_blocks' ) ) {
			return true;
		}

		$seen = array();

		return $this->collect_duplicate_uuids( parse_blocks( $content ), $seen );
	}

	/**
	 * Walks blocks and returns true when a UUID appears more than once.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param array<string, bool>              $seen   UUIDs already observed.
	 */
	private function collect_duplicate_uuids( array $blocks, array &$seen ): bool {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$uuid  = isset( $attrs[ Contract::ATTR_NAME ] ) ? (string) $attrs[ Contract::ATTR_NAME ] : '';

			if ( UuidValidator::is_valid_non_empty( $uuid ) ) {
				if ( isset( $seen[ $uuid ] ) ) {
					return true;
				}

				$seen[ $uuid ] = true;
			}

			$inner = $block['innerBlocks'] ?? array();
			if ( is_array( $inner ) && array() !== $inner && $this->collect_duplicate_uuids( $inner, $seen ) ) {
				return true;
			}
		}

		return false;
	}
}
