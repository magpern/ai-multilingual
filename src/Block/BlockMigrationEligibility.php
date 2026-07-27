<?php
/**
 * Strategy F block migration eligibility.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\RenderGateContext;
use WP_Post;

/**
 * Canonical post eligibility for Strategy F identity migration.
 *
 * Reuses {@see Extractor} body classification and the supported post types
 * from frontend rendering so eligibility stays consistent across milestones.
 */
final class BlockMigrationEligibility {

	public const REASON_MISSING_POST          = 'missing_post';
	public const REASON_UNSUPPORTED_POST_TYPE = 'unsupported_post_type';
	public const REASON_REVISION              = 'revision';
	public const REASON_AUTOSAVE              = 'autosave';
	public const REASON_TRASHED               = 'trashed';
	public const REASON_AUTO_DRAFT            = 'auto_draft';
	public const REASON_EMPTY_CONTENT         = 'empty_content';
	public const REASON_NON_BLOCK_CONTENT     = 'non_block_content';
	public const REASON_ELEMENTOR_BODY        = 'elementor_body';
	public const REASON_ALREADY_COMPLIANT     = 'already_compliant';

	/**
	 * Post statuses eligible for migration.
	 *
	 * @var list<string>
	 */
	public const ALLOWED_STATUSES = array( 'publish', 'draft', 'private', 'pending', 'future' );

	/**
	 * Evaluates whether a post may be migrated.
	 *
	 * @param WP_Post|null $post      Canonical post.
	 * @param Extractor    $extractor Body classifier.
	 */
	public static function evaluate( ?WP_Post $post, Extractor $extractor ): ?string {
		if ( ! $post instanceof WP_Post ) {
			return self::REASON_MISSING_POST;
		}

		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post ) ) {
			return self::REASON_AUTOSAVE;
		}

		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post ) ) {
			return self::REASON_REVISION;
		}

		if ( 'revision' === (string) $post->post_type ) {
			return self::REASON_REVISION;
		}

		if ( ! in_array( (string) $post->post_type, RenderGateContext::SUPPORTED_POST_TYPES, true ) ) {
			return self::REASON_UNSUPPORTED_POST_TYPE;
		}

		if ( 'trash' === (string) $post->post_status ) {
			return self::REASON_TRASHED;
		}

		if ( 'auto-draft' === (string) $post->post_status ) {
			return self::REASON_AUTO_DRAFT;
		}

		if ( ! in_array( (string) $post->post_status, self::ALLOWED_STATUSES, true ) ) {
			return self::REASON_UNSUPPORTED_POST_TYPE;
		}

		$content = (string) $post->post_content;
		if ( '' === trim( $content ) ) {
			return self::REASON_EMPTY_CONTENT;
		}

		if ( Extractor::BODY_ELEMENTOR === $extractor->body_status( $post ) ) {
			return self::REASON_ELEMENTOR_BODY;
		}

		if ( ! function_exists( 'has_blocks' ) || ! has_blocks( $content ) ) {
			return self::REASON_NON_BLOCK_CONTENT;
		}

		return null;
	}
}
