<?php
/**
 * PluginIdentity serializer unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Integration\Identity\PluginIdentity
 */
final class PluginIdentityTest extends TestCase {

	public function test_build_and_parse_round_trip(): void {
		$id  = new PluginIdentity();
		$key = $id->build( 'woo', 'record', '42', 'title', 'n1' );
		$this->assertSame( 'p:woo:record:42:title:n1', $key );
		$parsed = $id->parse( $key );
		$this->assertNotNull( $parsed );
		$this->assertSame( 'woo', $parsed['integration_id'] );
		$this->assertSame( array( 'n1' ), $parsed['nested'] );
	}

	public function test_rejects_length_overflow_without_truncation(): void {
		$id    = new PluginIdentity();
		$owner = str_repeat( 'a', Contract::MAX_OWNER_ID_LENGTH );
		$field = str_repeat( 'b', Contract::MAX_TOKEN_LENGTH );
		$n1    = str_repeat( 'c', Contract::MAX_TOKEN_LENGTH );
		$n2    = str_repeat( 'd', Contract::MAX_TOKEN_LENGTH );
		$n3    = str_repeat( 'e', Contract::MAX_TOKEN_LENGTH );
		$this->expectException( \InvalidArgumentException::class );
		$id->build( 'aiml_reference', 'record', $owner, $field, $n1, $n2, $n3 );
	}

	public function test_rejects_one_char_over_token_limit(): void {
		$id = new PluginIdentity();
		$this->expectException( \InvalidArgumentException::class );
		$id->build( 'x', 'record', '1', str_repeat( 'f', Contract::MAX_TOKEN_LENGTH + 1 ) );
	}

	public function test_rejects_unicode_and_empty_and_separators(): void {
		$id = new PluginIdentity();
		foreach ( array( 'tïtle', '', 'a:b' ) as $bad ) {
			try {
				$id->build( 'x', 'record', '1', $bad );
				$this->fail( 'Expected rejection for ' . $bad );
			} catch ( \InvalidArgumentException $e ) {
				$this->assertNotEmpty( $e->getMessage() );
			}
		}
		$this->expectException( \InvalidArgumentException::class );
		$id->build( 'BadCase', 'record', '1', 'title' );
	}

	public function test_does_not_collide_with_b_or_e_prefixes(): void {
		$id  = new PluginIdentity();
		$key = $id->build( 'x', 'record', '1', 'title' );
		$this->assertStringStartsWith( 'p:', $key );
		$this->assertFalse( str_starts_with( $key, 'b:' ) );
		$this->assertFalse( str_starts_with( $key, 'e:' ) );
		$this->assertNull( $id->parse( 'b:550e8400-e29b-41d4-a716-446655440000:content' ) );
		$this->assertNull( $id->parse( 'e:d:1:el:title' ) );
	}

	public function test_deterministic_output(): void {
		$id = new PluginIdentity();
		$a  = $id->build( 'x', 'record', '9', 'title' );
		$b  = $id->build( 'x', 'record', '9', 'title' );
		$this->assertSame( $a, $b );
	}

	public function test_max_nested_components(): void {
		$id = new PluginIdentity();
		$this->expectException( \InvalidArgumentException::class );
		$id->build( 'x', 'record', '1', 'f', 'a', 'b', 'c', 'd' );
	}
}
