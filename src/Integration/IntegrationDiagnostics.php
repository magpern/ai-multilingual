<?php
/**
 * Bounded Integration API diagnostics counters.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

/**
 * Request-scoped counters. No source/target bodies. No unbounded object IDs.
 */
final class IntegrationDiagnostics {

	public const COUNTER_INTEGRATION_REGISTERED   = 'integration_registered';
	public const COUNTER_INTEGRATION_AVAILABLE    = 'integration_available';
	public const COUNTER_INTEGRATION_INCOMPATIBLE = 'integration_incompatible';
	public const COUNTER_UNIT_EXTRACTED           = 'unit_extracted';
	public const COUNTER_UNIT_SKIPPED             = 'unit_skipped';
	public const COUNTER_OVERLAY_APPLIED          = 'overlay_applied';
	public const COUNTER_SOURCE_FALLBACK          = 'source_fallback';
	public const COUNTER_IDENTITY_ERROR           = 'identity_error';
	public const COUNTER_COMPATIBILITY_ERROR      = 'compatibility_error';

	public const ACTION_LOG = 'aiml_integration_diagnostics_log';

	/**
	 * Request-scoped counter map.
	 *
	 * @var array<string, int>
	 */
	private array $counters = array();

	/**
	 * Increment a bounded counter.
	 *
	 * @param string $key Counter key.
	 */
	public function increment( string $key ): void {
		if ( ! isset( $this->counters[ $key ] ) ) {
			$this->counters[ $key ] = 0;
		}
		++$this->counters[ $key ];

		/**
		 * Fires when an integration diagnostics counter increments.
		 *
		 * @since 1.1.0
		 *
		 * @param string               $key     Counter key.
		 * @param array<string, mixed> $context Bounded context (no bodies/secrets).
		 */
		do_action(
			'aiml_integration_diagnostics_log',
			$key,
			array(
				'count' => $this->counters[ $key ],
			)
		);
	}

	/**
	 * Snapshot of counters for the current request.
	 *
	 * @return array<string, int>
	 */
	public function snapshot(): array {
		return $this->counters;
	}

	/**
	 * Reset request-scoped counters.
	 */
	public function reset(): void {
		$this->counters = array();
	}
}
