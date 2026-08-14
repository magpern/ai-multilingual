<?php
/**
 * PathCanonicalizer unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Routing;

use AIMultilingual\Routing\InvalidPathException;
use AIMultilingual\Routing\PathCanonicalizer;
use PHPUnit\Framework\TestCase;

/**
 * Path-only canonicalization (R4).
 */
final class PathCanonicalizerTest extends TestCase {

	private PathCanonicalizer $canonicalizer;

	protected function setUp(): void {
		parent::setUp();
		$this->canonicalizer = new PathCanonicalizer();
	}

	public function test_basic_path_gets_leading_slash(): void {
		$result = $this->canonicalizer->canonicalize( 'hello' );
		$this->assertSame( '/hello', $result->to_string() );
	}

	public function test_duplicate_slashes_collapsed(): void {
		$result = $this->canonicalizer->canonicalize( '/foo//bar///baz' );
		$this->assertSame( '/foo/bar/baz', $result->to_string() );
	}

	public function test_trailing_slash_removed_except_root(): void {
		$this->assertSame( '/foo', $this->canonicalizer->canonicalize( '/foo/' )->to_string() );
		$this->assertSame( '/', $this->canonicalizer->canonicalize( '/' )->to_string() );
	}

	public function test_unicode_path_preserved(): void {
		$result = $this->canonicalizer->canonicalize( '/café/日本語' );
		$this->assertSame( '/café/日本語', $result->to_string() );
	}

	public function test_valid_percent_encoding_normalized(): void {
		$result = $this->canonicalizer->canonicalize( '/hello%20world' );
		$this->assertSame( '/hello world', $result->to_string() );
	}

	public function test_malformed_percent_encoding_rejected(): void {
		$this->expectException( InvalidPathException::class );
		$this->canonicalizer->canonicalize( '/bad%2' );
	}

	public function test_encoded_slash_rejected(): void {
		$this->expectException( InvalidPathException::class );
		$this->canonicalizer->canonicalize( '/foo%2Fbar' );
	}

	public function test_backslash_rejected(): void {
		$this->expectException( InvalidPathException::class );
		$this->canonicalizer->canonicalize( '/foo\\bar' );
	}

	public function test_null_byte_rejected(): void {
		$this->expectException( InvalidPathException::class );
		$this->canonicalizer->canonicalize( "/foo\0bar" );
	}

	public function test_query_string_rejected(): void {
		$this->expectException( InvalidPathException::class );
		$this->canonicalizer->canonicalize( '/path?query=1' );
	}

	public function test_fragment_rejected(): void {
		$this->expectException( InvalidPathException::class );
		$this->canonicalizer->canonicalize( '/path#frag' );
	}

	public function test_overlong_path_rejected(): void {
		$this->expectException( InvalidPathException::class );
		$this->canonicalizer->canonicalize( '/' . str_repeat( 'a', PathCanonicalizer::MAX_LENGTH ) );
	}

	public function test_idempotent(): void {
		$first  = $this->canonicalizer->canonicalize( '/foo/bar/' );
		$second = $this->canonicalizer->canonicalize( $first->to_string() );
		$this->assertTrue( $first->equals( $second ) );
	}

	public function test_does_not_slugify_arbitrary_segments(): void {
		$input  = '/Hello World';
		$result = $this->canonicalizer->canonicalize( $input );
		$this->assertSame( '/Hello World', $result->to_string() );
		$this->assertNotSame( '/hello-world', $result->to_string() );
	}
}
