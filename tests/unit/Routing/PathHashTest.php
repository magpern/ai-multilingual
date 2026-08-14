<?php
/**
 * PathHash unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Routing;

use AIMultilingual\Routing\CanonicalPath;
use AIMultilingual\Routing\InvalidPathException;
use AIMultilingual\Routing\PathHash;
use PHPUnit\Framework\TestCase;

/**
 * SHA-256 path identity (R3).
 */
final class PathHashTest extends TestCase {

	public function test_deterministic_sha256_from_canonical_path(): void {
		$path = new CanonicalPath( '/hello/world' );
		$a    = PathHash::from_canonical( $path );
		$b    = PathHash::from_canonical( $path );

		$this->assertTrue( $a->equals( $b ) );
		$this->assertSame( 32, strlen( $a->raw_bytes() ) );
		$this->assertSame( 64, strlen( $a->hex() ) );
		$this->assertSame( $a->hex(), strtolower( $a->hex() ) );
	}

	public function test_different_paths_produce_different_hashes(): void {
		$a = PathHash::from_canonical( new CanonicalPath( '/a' ) );
		$b = PathHash::from_canonical( new CanonicalPath( '/b' ) );

		$this->assertFalse( $a->equals( $b ) );
	}

	public function test_hex_round_trip(): void {
		$original = PathHash::from_canonical( new CanonicalPath( '/round-trip' ) );
		$restored = PathHash::from_hex( $original->hex() );

		$this->assertTrue( $original->equals( $restored ) );
	}

	public function test_digest_containing_nul_byte(): void {
		// Construct a hash with 0x00 as first byte — proves hex/UNHEX boundary.
		$hex  = '00' . str_repeat( 'ab', 31 );
		$hash = PathHash::from_hex( $hex );

		$this->assertSame( "\0", $hash->raw_bytes()[0] );
		$this->assertSame( 32, strlen( $hash->raw_bytes() ) );
	}

	public function test_invalid_hex_rejected(): void {
		$this->expectException( InvalidPathException::class );
		PathHash::from_hex( 'not-valid-hex' );
	}
}
