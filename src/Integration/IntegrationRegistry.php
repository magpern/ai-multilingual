<?php
/**
 * Central Integration API v1 registry.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

use WP_Post;

/**
 * Code-owned registry of typed plugin integrations.
 *
 * No database callbacks. Duplicate IDs rejected. Missing integrations are a no-op.
 */
final class IntegrationRegistry {

	/**
	 * Registered integrations keyed by ID.
	 *
	 * @var array<string, PluginIntegrationInterface>
	 */
	private array $integrations = array();

	/**
	 * Registration order (deterministic iteration).
	 *
	 * @var list<string>
	 */
	private array $order = array();

	/**
	 * Optional diagnostics sink.
	 *
	 * @var IntegrationDiagnostics|null
	 */
	private ?IntegrationDiagnostics $diagnostics;

	/**
	 * Builds the registry.
	 *
	 * @param IntegrationDiagnostics|null $diagnostics Diagnostics sink.
	 */
	public function __construct( ?IntegrationDiagnostics $diagnostics = null ) {
		$this->diagnostics = $diagnostics;
	}

	/**
	 * Register a typed integration.
	 *
	 * @param PluginIntegrationInterface $integration Integration instance.
	 * @throws \InvalidArgumentException On invalid or duplicate ID / API version.
	 */
	public function register( PluginIntegrationInterface $integration ): void {
		$id = $integration->get_id();
		if ( ! is_string( $id ) || 1 !== preg_match( Contract::INTEGRATION_ID_PATTERN, $id ) ) {
			throw new \InvalidArgumentException( 'Invalid integration ID.' );
		}
		if ( strlen( $id ) > Contract::MAX_INTEGRATION_ID_LENGTH ) {
			throw new \InvalidArgumentException( 'Integration ID exceeds maximum length.' );
		}
		if ( Contract::API_VERSION !== $integration->get_api_version() ) {
			throw new \InvalidArgumentException( 'Unsupported Integration API version.' );
		}
		if ( isset( $this->integrations[ $id ] ) ) {
			throw new \InvalidArgumentException( 'Duplicate integration ID.' );
		}

		$this->integrations[ $id ] = $integration;
		$this->order[]             = $id;
		$this->diagnostics?->increment( IntegrationDiagnostics::COUNTER_INTEGRATION_REGISTERED );
	}

	/**
	 * Integrations in registration order.
	 *
	 * @return list<PluginIntegrationInterface>
	 */
	public function all(): array {
		$out = array();
		foreach ( $this->order as $id ) {
			$out[] = $this->integrations[ $id ];
		}
		return $out;
	}

	/**
	 * Lookup by integration ID.
	 *
	 * @param string $id Integration ID.
	 */
	public function get( string $id ): ?PluginIntegrationInterface {
		return $this->integrations[ $id ] ?? null;
	}

	/**
	 * Whether any integrations are registered.
	 */
	public function is_empty(): bool {
		return array() === $this->integrations;
	}

	/**
	 * Public API version string.
	 */
	public function api_version(): string {
		return Contract::API_VERSION;
	}

	/**
	 * Extract units from all compatible integrations for a post.
	 *
	 * @param WP_Post $post Canonical post.
	 * @return list<TranslationUnitDescriptor>
	 */
	public function extract_for_post( WP_Post $post ): array {
		$units = array();
		foreach ( $this->all() as $integration ) {
			$status = $integration->get_compatibility();
			if ( ! $status->allows_operation() ) {
				$this->diagnostics?->increment( IntegrationDiagnostics::COUNTER_INTEGRATION_INCOMPATIBLE );
				continue;
			}
			$this->diagnostics?->increment( IntegrationDiagnostics::COUNTER_INTEGRATION_AVAILABLE );
			try {
				foreach ( $integration->extract_for_post( $post ) as $unit ) {
					if ( ! $unit instanceof TranslationUnitDescriptor ) {
						$this->diagnostics?->increment( IntegrationDiagnostics::COUNTER_UNIT_SKIPPED );
						continue;
					}
					$units[] = $unit;
					$this->diagnostics?->increment( IntegrationDiagnostics::COUNTER_UNIT_EXTRACTED );
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				$this->diagnostics?->increment( IntegrationDiagnostics::COUNTER_SOURCE_FALLBACK );
			}
		}
		return $units;
	}

	/**
	 * Register output hooks for overlay-capable integrations.
	 *
	 * @param callable(string): (?string) $resolve Segment key resolver.
	 */
	public function register_output_hooks( callable $resolve ): void {
		foreach ( $this->all() as $integration ) {
			$status = $integration->get_compatibility();
			if ( ! $status->allows_overlay() ) {
				$this->diagnostics?->increment( IntegrationDiagnostics::COUNTER_COMPATIBILITY_ERROR );
				continue;
			}
			try {
				$integration->register_output_hooks( $resolve );
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				$this->diagnostics?->increment( IntegrationDiagnostics::COUNTER_SOURCE_FALLBACK );
			}
		}
	}
}
