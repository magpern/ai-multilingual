<?php
/**
 * Bounded Elementor Foundation diagnostics.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

/**
 * In-request counters — no bodies, no high-cardinality metrics.
 */
final class ElementorDiagnostics {

	/**
	 * Bounded counter map.
	 *
	 * @var array<string, int>
	 */
	private array $counters = array(
		'eligible_document'           => 0,
		'supported_unit_extracted'    => 0,
		'nested_unit_extracted'       => 0,
		'unsupported_widget_skipped'  => 0,
		'unsupported_control_skipped' => 0,
		'missing_nested_id'           => 0,
		'duplicate_nested_id'         => 0,
		'adapter_failure'             => 0,
		'store_hit'                   => 0,
		'store_miss'                  => 0,
		'stale_translation'           => 0,
		'overlay_applied'             => 0,
		'source_fallback'             => 0,
		'identity_error'              => 0,
		'cache_isolation_failure'     => 0,
		'structural_rejected'         => 0,
	);

	/**
	 * Increment a known counter.
	 *
	 * @param string $key Counter key.
	 * @param int    $by  Delta.
	 */
	public function inc( string $key, int $by = 1 ): void {
		if ( ! isset( $this->counters[ $key ] ) ) {
			return;
		}
		$this->counters[ $key ] += $by;
	}

	/**
	 * Current counter snapshot.
	 *
	 * @return array<string, int>
	 */
	public function snapshot(): array {
		return $this->counters;
	}

	/**
	 * Reset for tests.
	 */
	public function reset(): void {
		foreach ( array_keys( $this->counters ) as $key ) {
			$this->counters[ $key ] = 0;
		}
	}
}
