<?php
/**
 * SHA-256 path identity for MSEO route tables.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

/**
 * Opaque SHA-256 digest of a canonical path (BINARY(32) in the database).
 *
 * Distinct from Store segment SHA-1 hashes (ADR-0023).
 */
final class PathHash {

	/**
	 * Raw 32-byte digest.
	 *
	 * @var string
	 */
	private string $raw;

	/**
	 * Private constructor — use factory methods.
	 *
	 * @param string $raw Exactly 32 raw bytes.
	 */
	private function __construct( string $raw ) {
		$this->raw = $raw;
	}

	/**
	 * Derives a path hash from a canonical path.
	 *
	 * @param CanonicalPath $path Canonical path.
	 * @throws InvalidPathException When hashing fails.
	 */
	public static function from_canonical( CanonicalPath $path ): self {
		$raw = hash( 'sha256', $path->to_string(), true );
		if ( false === $raw || 32 !== strlen( $raw ) ) {
			throw new InvalidPathException( 'Failed to compute SHA-256 path hash.' );
		}

		return new self( $raw );
	}

	/**
	 * Reconstructs from lowercase hex (64 characters).
	 *
	 * @param string $hex Lowercase hex digest.
	 * @throws InvalidPathException When hex is invalid.
	 */
	public static function from_hex( string $hex ): self {
		$hex = strtolower( trim( $hex ) );
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $hex ) ) {
			throw new InvalidPathException( 'Path hash hex must be 64 lowercase hex characters.' );
		}

		$raw = hex2bin( $hex );
		if ( false === $raw || 32 !== strlen( $raw ) ) {
			throw new InvalidPathException( 'Invalid path hash hex.' );
		}

		return new self( $raw );
	}

	/**
	 * Raw 32-byte digest for internal use.
	 */
	public function raw_bytes(): string {
		return $this->raw;
	}

	/**
	 * Lowercase hex for SQL UNHEX(%s) binding.
	 */
	public function hex(): string {
		return bin2hex( $this->raw );
	}

	/**
	 * Compares two path hashes in constant time.
	 *
	 * @param PathHash $other Other hash.
	 */
	public function equals( PathHash $other ): bool {
		return hash_equals( $this->raw, $other->raw );
	}
}
