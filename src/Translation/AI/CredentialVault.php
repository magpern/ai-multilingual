<?php
/**
 * Encrypts provider credentials at rest (ADR-0010).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

/**
 * Symmetric encryption for API keys. Never returned to JavaScript.
 */
final class CredentialVault {

	private const CIPHER = 'aes-256-cbc';
	private const PREFIX = 'aiml1:';

	/**
	 * Optional override salt (tests). Empty uses WordPress salts when available.
	 *
	 * @var string
	 */
	private string $salt_override;

	/**
	 * Builds the vault.
	 *
	 * @param string $salt_override Optional fixed salt for unit tests.
	 */
	public function __construct( string $salt_override = '' ) {
		$this->salt_override = $salt_override;
	}

	/**
	 * Encrypts plaintext. Returns empty string for empty input.
	 *
	 * @param string $plaintext Raw API key.
	 */
	public function encrypt( string $plaintext ): string {
		$plaintext = trim( $plaintext );
		if ( '' === $plaintext ) {
			return '';
		}

		$key = $this->key_bytes();
		$iv  = random_bytes( 16 );
		$raw = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $raw ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- AES payload encoding, not obfuscation.
		return self::PREFIX . base64_encode( $iv . $raw );
	}

	/**
	 * Decrypts a vault ciphertext.
	 *
	 * @param string $ciphertext Stored value.
	 */
	public function decrypt( string $ciphertext ): string {
		$ciphertext = trim( $ciphertext );
		if ( '' === $ciphertext ) {
			return '';
		}

		if ( ! str_starts_with( $ciphertext, self::PREFIX ) ) {
			// Legacy / mis-stored plaintext — treat as empty for safety.
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- AES payload decoding, not obfuscation.
		$decoded = base64_decode( substr( $ciphertext, strlen( self::PREFIX ) ), true );
		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return '';
		}

		$iv  = substr( $decoded, 0, 16 );
		$raw = substr( $decoded, 16 );
		$key = $this->key_bytes();
		$out = openssl_decrypt( $raw, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		return false === $out ? '' : $out;
	}

	/**
	 * Whether a stored value looks like an encrypted key.
	 *
	 * @param string $value Stored option value.
	 */
	public function is_encrypted( string $value ): bool {
		return str_starts_with( trim( $value ), self::PREFIX );
	}

	/**
	 * Derives a 32-byte key from WordPress salts or the override.
	 */
	private function key_bytes(): string {
		$material = '' !== $this->salt_override
			? $this->salt_override
			: $this->wordpress_material();

		return hash( 'sha256', $material, true );
	}

	/**
	 * WordPress salt material; falls back to a non-secret placeholder outside WP.
	 */
	private function wordpress_material(): string {
		if ( defined( 'AIML_AI_CREDENTIAL_KEY' ) && is_string( AIML_AI_CREDENTIAL_KEY ) && '' !== AIML_AI_CREDENTIAL_KEY ) {
			return AIML_AI_CREDENTIAL_KEY;
		}

		$parts = array();
		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $constant ) {
			if ( defined( $constant ) ) {
				$parts[] = (string) constant( $constant );
			}
		}

		if ( array() === $parts && function_exists( 'wp_salt' ) ) {
			return (string) wp_salt( 'auth' );
		}

		return array() === $parts ? 'aiml-insecure-fallback-not-for-production' : implode( '|', $parts );
	}
}
