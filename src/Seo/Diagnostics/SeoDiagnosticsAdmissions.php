<?php
/**
 * Frozen A.SEOf SF admissions (ASEOF.1 lock).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Seo\Diagnostics;

/**
 * Authoritative Supported / Partial / Deferred-upstream sets for A.SEOf.
 *
 * Implementation must not widen these without a new planning freeze.
 */
final class SeoDiagnosticsAdmissions {

	/**
	 * Fully Supported SF candidates.
	 *
	 * @return list<string>
	 */
	public static function supported(): array {
		return array(
			'SF1',
			'SF2',
			'SF3',
			'SF4',
			'SF5',
			'SF6',
			'SF7',
			'SF8',
			'SF9',
			'SF10',
			'SF11',
			'SF12',
			'SF13',
			'SF14',
		);
	}

	/**
	 * Partially Supported SF candidates (Supported portion only).
	 *
	 * @return list<string>
	 */
	public static function partially_supported(): array {
		return array( 'SF15' );
	}

	/**
	 * Upstream Deferred surfaces A.SEOf must not invent.
	 *
	 * @return list<string>
	 */
	public static function deferred_upstream(): array {
		return array( 'SE11', 'SD12' );
	}

	/**
	 * Whether an SF id is admitted for implementation (Supported or Partial).
	 *
	 * @param string $sf_id Candidate id (e.g. SF9).
	 */
	public static function is_admitted( string $sf_id ): bool {
		$sf_id = strtoupper( $sf_id );
		return in_array( $sf_id, self::supported(), true )
			|| in_array( $sf_id, self::partially_supported(), true );
	}
}
