<?php
/**
 * Hash semantics and segment identity.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit;

use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Pins down what each hash means, and the normalization-version contract that
 * stops a future change to the rules from marking an entire translated site
 * stale overnight.
 */
final class HashSemanticsTest extends TestCase {

	public function test_source_hash_is_a_sha1(): void {
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{40}$/', Store::source_hash( 'Tea' ) );
	}

	/**
	 * The translation hash is an integrity marker over the stored bytes, not a
	 * normalized comparison: it has to detect any change at all, including ones
	 * that source normalization would forgive.
	 */
	public function test_translation_hash_is_not_normalized(): void {
		$this->assertNotSame(
			Store::translation_hash( 'Rött te' ),
			Store::translation_hash( 'Rött  te' )
		);

		$this->assertSame( sha1( 'Rött te' ), Store::translation_hash( 'Rött te' ) );
	}

	public function test_segment_identity_is_stable(): void {
		$this->assertSame(
			Store::segment_hash( 'post_content', 'block:2' ),
			Store::segment_hash( 'post_content', 'block:2' )
		);
	}

	public function test_segment_identity_distinguishes_field_and_segment(): void {
		$this->assertNotSame(
			Store::segment_hash( 'post_title', 'post_title' ),
			Store::segment_hash( 'post_content', 'post_content' )
		);

		$this->assertNotSame(
			Store::segment_hash( 'post_content', 'block:1' ),
			Store::segment_hash( 'post_content', 'block:2' )
		);
	}

	/**
	 * The separator matters: without it, ('ab', 'c') and ('a', 'bc') would be
	 * the same segment.
	 */
	public function test_segment_identity_cannot_be_confused_by_concatenation(): void {
		$this->assertNotSame(
			Store::segment_hash( 'ab', 'c' ),
			Store::segment_hash( 'a', 'bc' )
		);
	}

	/**
	 * Rows record the algorithm version that produced their hash. A row written
	 * under version 1 must keep comparing equal under version 1 even once a
	 * version 2 exists, which is what stops a rules change from invalidating the
	 * whole corpus at once.
	 */
	public function test_normalization_version_is_recorded_and_stable(): void {
		$this->assertSame( 1, Store::NORM_VERSION );

		$before = Store::source_hash( "Red  Tea\r\n", Store::FORMAT_PLAIN );
		$after  = Store::source_hash( 'Red Tea', Store::FORMAT_PLAIN );

		$this->assertSame(
			$before,
			$after,
			'Version 1 normalization must remain exactly as specified; changing it requires bumping NORM_VERSION.'
		);
	}

	public function test_statuses_cover_the_documented_provenance_model(): void {
		$this->assertSame(
			array( 'missing', 'machine_translated', 'manually_edited', 'reviewed', 'failed', 'ignored' ),
			Store::statuses()
		);
	}

	public function test_formats_cover_the_documented_set(): void {
		$this->assertSame(
			array( 'plain', 'html', 'json', 'code', 'slug' ),
			Store::formats()
		);
	}
}
