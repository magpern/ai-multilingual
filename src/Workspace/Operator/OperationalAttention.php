<?php
/**
 * OTL.1 operational attention vocabulary and mapping.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Operator;

use AIMultilingual\Translation\Store;
use WP_Error;

/**
 * Cheap lifecycle-axis attention (not TI.5 risk, not TI.7 eligibility).
 *
 * Machine ID `needs_review` is reserved for TI.5 AssessmentCategory only.
 */
final class OperationalAttention {

	public const ID_STALE              = 'stale';
	public const ID_REVIEW_PENDING     = 'review_pending';
	public const ID_REVIEW_REJECTED    = 'review_rejected';
	public const ID_UNPUBLISHED        = 'unpublished';
	public const ID_TRANSLATION_FAILED = 'translation_failed';

	/** No-filter selection (not an attention reason). */
	public const PRESET_ALL = 'all';

	/**
	 * Frozen attention reason IDs (exclusive of `all`).
	 *
	 * @return list<string>
	 */
	public static function reason_ids(): array {
		return array(
			self::ID_STALE,
			self::ID_REVIEW_PENDING,
			self::ID_REVIEW_REJECTED,
			self::ID_UNPUBLISHED,
			self::ID_TRANSLATION_FAILED,
		);
	}

	/**
	 * Whether a string is a valid attention reason ID.
	 */
	public static function is_reason_id( string $id ): bool {
		return in_array( $id, self::reason_ids(), true );
	}

	/**
	 * Whether a preset is valid for the attention filter (`all` or a reason ID).
	 */
	public static function is_valid_preset( string $preset ): bool {
		return self::PRESET_ALL === $preset || self::is_reason_id( $preset );
	}

	/**
	 * Maps an attention preset to Store query_operations filter args.
	 *
	 * Empty / `all` → no attention filters. Unknown IDs (incl. `needs_review`) → WP_Error.
	 *
	 * @return array<string, mixed>|WP_Error Filter fragment (may be empty) or error.
	 */
	public static function preset_to_store_filters( string $preset ) {
		$preset = strtolower( preg_replace( '/[^a-z0-9_]/', '', $preset ) ?? '' );
		if ( '' === $preset || self::PRESET_ALL === $preset ) {
			return array();
		}

		if ( ! self::is_reason_id( $preset ) ) {
			return new WP_Error(
				'aiml_invalid_attention',
				__( 'Invalid attention preset.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		return match ( $preset ) {
			self::ID_STALE => array( 'is_stale' => true ),
			self::ID_REVIEW_PENDING => array( 'review_status' => Store::REVIEW_PENDING ),
			self::ID_REVIEW_REJECTED => array( 'review_status' => Store::REVIEW_REJECTED ),
			self::ID_UNPUBLISHED => array( 'publish_status' => Store::PUBLISH_UNPUBLISHED ),
			self::ID_TRANSLATION_FAILED => array( 'status' => Store::STATUS_FAILED ),
			default => new WP_Error(
				'aiml_invalid_attention',
				__( 'Invalid attention preset.', 'ai-multilingual' ),
				array( 'status' => 422 )
			),
		};
	}

	/**
	 * Derives multi-label attention reasons from a cheap Store row.
	 *
	 * @param object $row Hydrated Store row.
	 * @return list<string>
	 */
	public static function reasons_for_row( object $row ): array {
		$reasons = array();

		if ( ! empty( $row->is_stale ) ) {
			$reasons[] = self::ID_STALE;
		}

		$review = (string) ( $row->review_status ?? Store::REVIEW_NOT_SUBMITTED );
		if ( Store::REVIEW_PENDING === $review ) {
			$reasons[] = self::ID_REVIEW_PENDING;
		} elseif ( Store::REVIEW_REJECTED === $review ) {
			$reasons[] = self::ID_REVIEW_REJECTED;
		}

		$publish = (string) ( $row->publish_status ?? Store::PUBLISH_UNPUBLISHED );
		if ( Store::PUBLISH_UNPUBLISHED === $publish ) {
			$reasons[] = self::ID_UNPUBLISHED;
		}

		if ( Store::STATUS_FAILED === (string) ( $row->status ?? '' ) ) {
			$reasons[] = self::ID_TRANSLATION_FAILED;
		}

		return $reasons;
	}
}
