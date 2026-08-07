<?php
/**
 * Namespaced plugin identity serializer (`p:`).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration\Identity;

use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\IntegrationDiagnostics;

/**
 * Framework-owned Option B serializer for Integration API v1.
 *
 * Integrations supply typed components; they must not concatenate keys.
 */
final class PluginIdentity {

	/**
	 * Builds the serializer.
	 *
	 * @param IntegrationDiagnostics|null $diagnostics Optional diagnostics.
	 */
	public function __construct(
		private ?IntegrationDiagnostics $diagnostics = null,
	) {
	}

	/**
	 * Build a `p:` segment key.
	 *
	 * @param string $integration_id Integration ID.
	 * @param string $owner_type     Owner type.
	 * @param string $owner_id       Owner ID.
	 * @param string $field          Field ID.
	 * @param string ...$nested      Optional nested IDs.
	 * @throws \InvalidArgumentException On invalid components or length overflow.
	 */
	public function build(
		string $integration_id,
		string $owner_type,
		string $owner_id,
		string $field,
		string ...$nested
	): string {
		$this->assert_integration_id( $integration_id );
		$this->assert_token( $owner_type, Contract::MAX_TOKEN_LENGTH, 'owner_type' );
		$this->assert_token( $owner_id, Contract::MAX_OWNER_ID_LENGTH, 'owner_id' );
		$this->assert_token( $field, Contract::MAX_TOKEN_LENGTH, 'field' );

		if ( count( $nested ) > Contract::MAX_NESTED_COMPONENTS ) {
			$this->fail( 'Too many nested identity components.' );
		}

		$parts = array( Contract::SEGMENT_KEY_PREFIX, $integration_id, $owner_type, $owner_id, $field );
		foreach ( $nested as $index => $nested_id ) {
			$this->assert_token( $nested_id, Contract::MAX_TOKEN_LENGTH, 'nested[' . $index . ']' );
			$parts[] = $nested_id;
		}

		$key = implode( ':', $parts );
		if ( strlen( $key ) > Contract::MAX_SEGMENT_KEY_LENGTH ) {
			$this->fail( 'Serialized identity exceeds Store segment_key limit.' );
		}

		return $key;
	}

	/**
	 * Parse a `p:` key into components.
	 *
	 * @param string $segment_key Key.
	 * @return array{integration_id:string,owner_type:string,owner_id:string,field:string,nested:list<string>}|null
	 */
	public function parse( string $segment_key ): ?array {
		if ( '' === $segment_key || strlen( $segment_key ) > Contract::MAX_SEGMENT_KEY_LENGTH ) {
			return null;
		}

		$parts = explode( ':', $segment_key );
		if ( count( $parts ) < 5 ) {
			return null;
		}
		if ( Contract::SEGMENT_KEY_PREFIX !== $parts[0] ) {
			return null;
		}

		$integration_id = $parts[1];
		$owner_type     = $parts[2];
		$owner_id       = $parts[3];
		$field          = $parts[4];
		$nested         = array_slice( $parts, 5 );

		if ( count( $nested ) > Contract::MAX_NESTED_COMPONENTS ) {
			return null;
		}

		if ( 1 !== preg_match( Contract::INTEGRATION_ID_PATTERN, $integration_id ) ) {
			return null;
		}
		if ( strlen( $integration_id ) > Contract::MAX_INTEGRATION_ID_LENGTH ) {
			return null;
		}
		if ( ! $this->is_safe_token( $owner_type, Contract::MAX_TOKEN_LENGTH )
			|| ! $this->is_safe_token( $owner_id, Contract::MAX_OWNER_ID_LENGTH )
			|| ! $this->is_safe_token( $field, Contract::MAX_TOKEN_LENGTH ) ) {
			return null;
		}
		foreach ( $nested as $nested_id ) {
			if ( ! $this->is_safe_token( $nested_id, Contract::MAX_TOKEN_LENGTH ) ) {
				return null;
			}
		}

		return array(
			'integration_id' => $integration_id,
			'owner_type'     => $owner_type,
			'owner_id'       => $owner_id,
			'field'          => $field,
			'nested'         => array_values( $nested ),
		);
	}

	/**
	 * Whether the key uses the plugin-integration family.
	 *
	 * @param string $segment_key Key.
	 */
	public function is_plugin_key( string $segment_key ): bool {
		return 0 === strpos( $segment_key, Contract::SEGMENT_KEY_PREFIX . ':' );
	}

	/**
	 * Validates a non-integration token.
	 *
	 * @param string $token Token.
	 * @param int    $max   Max length.
	 * @param string $label Label for errors.
	 */
	private function assert_token( string $token, int $max, string $label ): void {
		if ( ! $this->is_safe_token( $token, $max ) ) {
			$this->fail( 'Invalid identity component: ' . $label );
		}
	}

	/**
	 * Validates an integration ID token.
	 *
	 * @param string $id Integration ID.
	 */
	private function assert_integration_id( string $id ): void {
		if ( 1 !== preg_match( Contract::INTEGRATION_ID_PATTERN, $id )
			|| strlen( $id ) < 1
			|| strlen( $id ) > Contract::MAX_INTEGRATION_ID_LENGTH ) {
			$this->fail( 'Invalid integration_id.' );
		}
	}

	/**
	 * Whether a token matches the ASCII allowlist and length bound.
	 *
	 * @param string $token Token.
	 * @param int    $max   Max length.
	 */
	private function is_safe_token( string $token, int $max ): bool {
		if ( '' === $token || strlen( $token ) > $max ) {
			return false;
		}
		// TOKEN_PATTERN is ASCII-only — Unicode / multibyte tokens are rejected.
		return 1 === preg_match( Contract::TOKEN_PATTERN, $token );
	}

	/**
	 * Records an identity error and throws.
	 *
	 * @param string $message Error message.
	 * @throws \InvalidArgumentException Always.
	 */
	private function fail( string $message ): void {
		$this->diagnostics?->increment( IntegrationDiagnostics::COUNTER_IDENTITY_ERROR );
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
		throw new \InvalidArgumentException( $message );
	}
}
