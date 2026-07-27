<?php
/**
 * Spike S5 — Strategy E render eligibility gate.
 *
 * A stored translation renders only when continuity is provable. This class
 * implements render eligibility only — not reconciliation, not evaluation.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyERenderGate {

	/**
	 * @param array<string, mixed>|null $row
	 * @param array{block_name: string, text: string} $segment
	 * @param array{
	 *   ambiguous_new_keys?: array<string, bool>,
	 *   unresolved_new_keys?: array<string, bool>
	 * } $context
	 * @return array{
	 *   renders: bool,
	 *   reason: string,
	 *   translated_text: ?string,
	 *   source_fallback: bool
	 * }
	 */
	public static function resolve( string $key, ?array $row, array $segment, array $context = array() ): array {
		$ambiguous   = $context['ambiguous_new_keys'] ?? array();
		$unresolved  = $context['unresolved_new_keys'] ?? array();

		if ( null === $row ) {
			return self::fallback( StrategyESuppressionReason::NO_ROW );
		}

		if ( ReconciliationSimulator::STATUS_IGNORED === ( $row['status'] ?? '' ) ) {
			return self::fallback( StrategyESuppressionReason::IGNORED );
		}

		if ( isset( $ambiguous[ $key ] ) ) {
			return self::fallback( StrategyESuppressionReason::AMBIGUOUS_REMATCH );
		}

		if ( isset( $unresolved[ $key ] ) ) {
			return self::fallback( StrategyESuppressionReason::UNRESOLVED_REMATCH );
		}

		if ( ( $row['block_name'] ?? '' ) !== $segment['block_name'] ) {
			return self::fallback( StrategyESuppressionReason::BLOCK_TYPE_MISMATCH );
		}

		$current_hash = ReconciliationSimulator::source_hash( $segment['text'] );
		$stored_hash  = (string) ( $row['source_hash'] ?? '' );

		if ( 'displaced' === ( $row['error_code'] ?? '' ) ) {
			return self::fallback( StrategyESuppressionReason::DISPLACED );
		}

		if ( $stored_hash !== $current_hash ) {
			return self::fallback( StrategyESuppressionReason::STALE_HASH );
		}

		if ( ! empty( $row['is_stale'] ) ) {
			return self::fallback( StrategyESuppressionReason::STALE_FLAG );
		}

		$translation = (string) ( $row['translated_text'] ?? '' );
		if ( '' === $translation ) {
			return self::fallback( StrategyESuppressionReason::EMPTY_TRANSLATION );
		}

		return array(
			'renders'         => true,
			'reason'          => StrategyESuppressionReason::ELIGIBLE,
			'translated_text' => $translation,
			'source_fallback' => false,
		);
	}

	/**
	 * @return array{renders: bool, reason: string, translated_text: ?string, source_fallback: bool}
	 */
	private static function fallback( string $reason ): array {
		return array(
			'renders'         => false,
			'reason'          => $reason,
			'translated_text' => null,
			'source_fallback' => true,
		);
	}
}
