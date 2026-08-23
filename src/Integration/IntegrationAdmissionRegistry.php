<?php
/**
 * Integration-owned chrome CPT admission registry (M5-A).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

use AIMultilingual\Integration\Identity\PluginIdentity;

/**
 * Collects companion chrome declarations, validates after CPT registration, activates narrowly.
 *
 * Invalid declarations disable only that surface and never fail the integration registry.
 */
final class IntegrationAdmissionRegistry {

	/**
	 * Pending declarations collected at integration registration time.
	 *
	 * @var list<array{integration_id:string,declaration:ChromeOwnedSurfaceDeclaration}>
	 */
	private array $pending = array();

	/**
	 * Activated declarations keyed by integration_id + "\0" + post_type.
	 *
	 * @var array<string, ChromeOwnedSurfaceDeclaration>
	 */
	private array $activated = array();

	/**
	 * Activated post types (unique).
	 *
	 * @var list<string>
	 */
	private array $activated_post_types = array();

	/**
	 * Whether validate_and_activate() has completed for this request.
	 *
	 * @var bool
	 */
	private bool $validated = false;

	/**
	 * Builds the admission registry.
	 *
	 * @param IntegrationDiagnostics|null $diagnostics Diagnostics sink.
	 * @param PluginIdentity|null         $identity    Token grammar helper.
	 */
	public function __construct(
		private ?IntegrationDiagnostics $diagnostics = null,
		private ?PluginIdentity $identity = null,
	) {
		$this->identity ??= new PluginIdentity( $this->diagnostics );
	}

	/**
	 * Collect chrome declarations from registered integrations that declare them.
	 *
	 * @param IntegrationRegistry $registry Integration registry.
	 */
	public function collect_from_registry( IntegrationRegistry $registry ): void {
		foreach ( $registry->all() as $integration ) {
			if ( ! $integration instanceof DeclaresChromeOwnedSurfaces ) {
				continue;
			}
			$integration_id = $integration->get_id();
			foreach ( $integration->get_chrome_owned_surfaces() as $declaration ) {
				if ( ! $declaration instanceof ChromeOwnedSurfaceDeclaration ) {
					$this->disable_invalid(
						$integration_id,
						'',
						'invalid_declaration_type'
					);
					continue;
				}
				$this->pending[] = array(
					'integration_id' => $integration_id,
					'declaration'    => $declaration,
				);
			}
		}
	}

	/**
	 * Validate pending declarations after CPTs exist (normally post-`init`) and activate valid ones.
	 */
	public function validate_and_activate(): void {
		if ( $this->validated ) {
			return;
		}
		$this->validated = true;

		foreach ( $this->pending as $item ) {
			$integration_id = $item['integration_id'];
			$declaration    = $item['declaration'];
			$reason         = $this->validation_failure_reason( $integration_id, $declaration );
			if ( null !== $reason ) {
				$this->disable_invalid( $integration_id, $declaration->post_type, $reason );
				continue;
			}
			$key = $this->key( $integration_id, $declaration->post_type );
			if ( isset( $this->activated[ $key ] ) ) {
				$this->disable_invalid( $integration_id, $declaration->post_type, 'duplicate_post_type' );
				continue;
			}
			$this->activated[ $key ] = $declaration;
			if ( ! in_array( $declaration->post_type, $this->activated_post_types, true ) ) {
				$this->activated_post_types[] = $declaration->post_type;
			}
			$this->diagnostics?->increment( IntegrationDiagnostics::COUNTER_CHROME_DECLARATION_ACTIVATED );
		}

		$this->pending = array();
	}

	/**
	 * Whether validation has run.
	 */
	public function is_validated(): bool {
		return $this->validated;
	}

	/**
	 * Activated chrome CPT slugs.
	 *
	 * @return list<string>
	 */
	public function activated_post_types(): array {
		return $this->activated_post_types;
	}

	/**
	 * Whether any activated declaration covers this post type.
	 *
	 * @param string $post_type Post type.
	 */
	public function admits_post_type( string $post_type ): bool {
		return in_array( $post_type, $this->activated_post_types, true );
	}

	/**
	 * Whether this post type uses integration-units-only extraction.
	 *
	 * @param string $post_type Post type.
	 */
	public function is_integration_units_only( string $post_type ): bool {
		foreach ( $this->activated as $declaration ) {
			if ( $declaration->post_type === $post_type
				&& ChromeOwnedSurfaceDeclaration::EXTRACTION_INTEGRATION_UNITS_ONLY === $declaration->extraction ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Find an activated declaration for an integration + post type.
	 *
	 * @param string $integration_id Integration ID.
	 * @param string $post_type      Post type.
	 */
	public function find_activated( string $integration_id, string $post_type ): ?ChromeOwnedSurfaceDeclaration {
		return $this->activated[ $this->key( $integration_id, $post_type ) ] ?? null;
	}

	/**
	 * Whether a `p:` segment is owned by an activated chrome declaration for this source post.
	 *
	 * @param string $segment_key Segment key.
	 * @param int    $source_id   Source post ID.
	 * @param string $post_type   Source post type.
	 */
	public function admits_chrome_segment( string $segment_key, int $source_id, string $post_type ): bool {
		$parsed = $this->identity->parse( $segment_key );
		if ( ! is_array( $parsed ) ) {
			return false;
		}
		$integration_id = (string) ( $parsed['integration_id'] ?? '' );
		$owner_type     = (string) ( $parsed['owner_type'] ?? '' );
		$owner_id       = (string) ( $parsed['owner_id'] ?? '' );
		$field          = (string) ( $parsed['field'] ?? '' );

		$declaration = $this->find_activated( $integration_id, $post_type );
		if ( null === $declaration ) {
			return false;
		}
		if ( ! $declaration->allows_owner_type( $owner_type ) ) {
			return false;
		}
		if ( (string) $source_id !== $owner_id ) {
			return false;
		}
		if ( ! $declaration->allows_field( $field ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Returns a bounded validation failure reason, or null when valid.
	 *
	 * @param string                        $integration_id Integration ID.
	 * @param ChromeOwnedSurfaceDeclaration $declaration    Declaration.
	 */
	private function validation_failure_reason( string $integration_id, ChromeOwnedSurfaceDeclaration $declaration ): ?string {
		$post_type = $declaration->post_type;
		if ( '' === $post_type || 1 !== preg_match( Contract::TOKEN_PATTERN, $post_type ) ) {
			return 'invalid_post_type_token';
		}
		if ( strlen( $post_type ) > Contract::MAX_TOKEN_LENGTH ) {
			return 'invalid_post_type_token';
		}
		if ( ! post_type_exists( $post_type ) ) {
			return 'post_type_not_registered';
		}
		if ( ChromeOwnedSurfaceDeclaration::EXTRACTION_INTEGRATION_UNITS_ONLY !== $declaration->extraction ) {
			return 'unsupported_extraction_mode';
		}
		if ( array() === $declaration->owner_types ) {
			return 'empty_owner_types';
		}
		foreach ( $declaration->owner_types as $owner_type ) {
			if ( ! is_string( $owner_type ) || 1 !== preg_match( Contract::TOKEN_PATTERN, $owner_type ) ) {
				return 'invalid_owner_type';
			}
			if ( strlen( $owner_type ) > Contract::MAX_TOKEN_LENGTH ) {
				return 'invalid_owner_type';
			}
		}
		if ( array() === $declaration->fields ) {
			return 'empty_fields';
		}
		foreach ( $declaration->fields as $field ) {
			if ( ! is_string( $field ) || 1 !== preg_match( Contract::TOKEN_PATTERN, $field ) ) {
				return 'invalid_field';
			}
			if ( strlen( $field ) > Contract::MAX_TOKEN_LENGTH ) {
				return 'invalid_field';
			}
		}
		foreach ( $this->activated as $key => $existing ) {
			if ( $existing->post_type !== $post_type ) {
				continue;
			}
			$existing_integration = explode( "\0", $key, 2 )[0] ?? '';
			if ( $existing_integration !== $integration_id ) {
				return 'post_type_claimed_by_other_integration';
			}
		}
		return null;
	}

	/**
	 * Disable one chrome-surface declaration with an authorized diagnostic.
	 *
	 * @param string $integration_id Integration ID.
	 * @param string $post_type      Post type (may be empty).
	 * @param string $reason         Bounded reason code.
	 */
	private function disable_invalid( string $integration_id, string $post_type, string $reason ): void {
		$this->diagnostics?->record_chrome_declaration_disabled( $integration_id, $post_type, $reason );
	}

	/**
	 * Builds an activated-declaration map key.
	 *
	 * @param string $integration_id Integration ID.
	 * @param string $post_type      Post type.
	 */
	private function key( string $integration_id, string $post_type ): string {
		return $integration_id . "\0" . $post_type;
	}
}
