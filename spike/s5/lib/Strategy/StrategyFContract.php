<?php
/**
 * Spike S5 — Strategy F UUID attribute contract.
 *
 * Planning docs (AI_MULTILINGUAL_PLANNING.md § Strategies) specify:
 *   - Strategy F: injected persistent UUID attribute
 *   - Segment key shape: b:<uuid>:content
 *
 * They do NOT specify the Gutenberg attribute name or UUID format. This spike
 * uses the recommendation below, documented for ADR-0013. Changing either
 * constant invalidates spike evidence until re-run.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyFContract {

	/**
	 * Gutenberg block attribute storing the persistent block identity.
	 *
	 * Recommendation: camelCase, aiml-prefixed, distinct from core attrs.
	 */
	public const ATTR_NAME = 'aimlBlockId';

	/** Segment field suffix (matches Strategy C convention). */
	public const FIELD_SUFFIX = 'content';

	/**
	 * RFC 4122 version-4 UUID, lowercase hex with hyphens.
	 * Example: 550e8400-e29b-41d4-a716-446655440000
	 */
	public const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

	/** Primary duplicate repair policy for Strategy F implementation. */
	public const REPAIR_POLICY_FIRST_WINS = 'first_wins';

	public static function segment_key( string $uuid ): string {
		return 'b:' . $uuid . ':' . self::FIELD_SUFFIX;
	}

	public static function is_valid_uuid( string $uuid ): bool {
		return 1 === preg_match( self::UUID_V4_PATTERN, $uuid );
	}
}
