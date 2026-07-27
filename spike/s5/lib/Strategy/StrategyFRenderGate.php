<?php
/**
 * Spike S5 — Strategy F render eligibility gate.
 *
 * A translation renders only when UUID continuity is provable and unambiguous.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyFRenderGate {

	/**
	 * @param array<string, mixed>|null $row
	 * @param array{uuid: string, block_name: string, text: string} $segment
	 * @param array{
	 *   duplicate_uuids?: array<string, bool>,
	 *   regenerated_uuids?: array<string, bool>
	 * } $context
	 * @return array{
	 *   renders: bool,
	 *   reason: string,
	 *   translated_text: ?string,
	 *   source_fallback: bool
	 * }
	 */
	public static function resolve( string $key, ?array $row, array $segment, array $context = array() ): array {
		$uuid = (string) ( $segment['uuid'] ?? '' );

		if ( '' === $uuid ) {
			return self::fallback( StrategyFSuppressionReason::MISSING_UUID );
		}

		if ( ! StrategyFContract::is_valid_uuid( $uuid ) ) {
			return self::fallback( StrategyFSuppressionReason::MALFORMED_UUID );
		}

		$dupes = $context['duplicate_uuids'] ?? array();
		if ( isset( $dupes[ $uuid ] ) ) {
			return self::fallback( StrategyFSuppressionReason::DUPLICATE_UUID );
		}

		$regenerated = $context['regenerated_uuids'] ?? array();
		if ( isset( $regenerated[ $uuid ] ) ) {
			return self::fallback( StrategyFSuppressionReason::REGENERATED_UUID );
		}

		if ( null === $row ) {
			return self::fallback( StrategyFSuppressionReason::UNKNOWN_UUID );
		}

		if ( ReconciliationSimulator::STATUS_IGNORED === ( $row['status'] ?? '' ) ) {
			return self::fallback( StrategyFSuppressionReason::ORPHANED_ROW );
		}

		if ( ( $row['block_name'] ?? '' ) !== $segment['block_name'] ) {
			return self::fallback( StrategyFSuppressionReason::BLOCK_TYPE_MISMATCH );
		}

		$current_hash = ReconciliationSimulator::source_hash( $segment['text'] );
		if ( (string) ( $row['source_hash'] ?? '' ) !== $current_hash ) {
			return self::fallback( StrategyFSuppressionReason::STALE_HASH );
		}

		$translation = (string) ( $row['translated_text'] ?? '' );
		if ( '' === $translation ) {
			return self::fallback( StrategyFSuppressionReason::EMPTY_TRANSLATION );
		}

		return array(
			'renders'         => true,
			'reason'          => StrategyFSuppressionReason::ELIGIBLE,
			'translated_text' => $translation,
			'source_fallback' => false,
		);
	}

	/** @return array{renders: bool, reason: string, translated_text: ?string, source_fallback: bool} */
	private static function fallback( string $reason ): array {
		return array(
			'renders'         => false,
			'reason'          => $reason,
			'translated_text' => null,
			'source_fallback' => true,
		);
	}
}
