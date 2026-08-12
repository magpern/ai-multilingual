<?php
/**
 * TSC.1 characterization and adoption invariants.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermAdoptionService;
use AIMultilingual\Translation\TermExtractor;
use AIMultilingual\Translation\TermTranslationResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Translation\TermAdoptionService
 * @covers \AIMultilingual\Translation\Store
 */
final class Tsc1AdoptionCharacterizationTest extends TestCase {

	/**
	 * SOURCE_TERM constant and identity shape.
	 */
	public function test_source_term_constant_and_native_keys(): void {
		$this->assertSame( 'term', Store::SOURCE_TERM );
		$this->assertSame( 'name', TermExtractor::FIELD_NAME );
		$this->assertSame( 'description', TermExtractor::FIELD_DESCRIPTION );
	}

	/**
	 * Resolver is read-only (no write APIs).
	 */
	public function test_resolver_has_no_write_methods(): void {
		$methods = get_class_methods( TermTranslationResolver::class );
		foreach ( $methods as $method ) {
			$this->assertDoesNotMatchRegularExpression(
				'/^(save|write|adopt|mutate|lock|update|insert|delete)/i',
				$method
			);
		}
	}

	/**
	 * Adoption service exposes content-write ensure API.
	 */
	public function test_adoption_service_content_write_api(): void {
		$this->assertTrue( method_exists( TermAdoptionService::class, 'ensure_native_before_content_write' ) );
		$this->assertTrue( method_exists( TermAdoptionService::class, 'native_write_identity' ) );
		$this->assertTrue( method_exists( Store::class, 'adopt_row_to_identity' ) );
		$this->assertTrue( method_exists( Store::class, 'with_term_compat_authority' ) );
		$this->assertTrue( method_exists( Store::class, 'mutate_under_term_compat_authority' ) );
	}

	/**
	 * No broad get_term mutation filter registration helper exists on visitor overlay.
	 */
	public function test_term_visitor_overlay_does_not_filter_get_term(): void {
		$source = file_get_contents( dirname( __DIR__, 3 ) . '/src/Integration/TermVisitorOverlay.php' );
		$this->assertIsString( $source );
		$this->assertStringNotContainsString( "add_filter( 'get_term'", $source );
		$this->assertStringNotContainsString( 'add_filter( "get_term"', $source );
		$this->assertStringContainsString( "add_filter( 'single_term_title'", $source );
		$this->assertStringContainsString( "add_filter( 'term_description'", $source );
		$this->assertStringNotContainsString( 'get_the_archive_title', $source );
		$this->assertStringNotContainsString( 'woocommerce_attribute_label', $source );
	}

	/**
	 * Adopt path must not go through save_translation (AC11).
	 */
	public function test_adopt_implementation_bypasses_save_translation(): void {
		$store = file_get_contents( dirname( __DIR__, 3 ) . '/src/Translation/Store.php' );
		$this->assertIsString( $store );

		$start = strpos( $store, 'function adopt_row_to_identity' );
		$this->assertNotFalse( $start );
		$chunk = substr( $store, (int) $start, 3500 );

		$this->assertStringNotContainsString( 'save_translation(', $chunk );
		$this->assertStringContainsString( 'insert_raw(', $chunk );
		$this->assertStringContainsString( 'retire_hosted_compat_row(', $chunk );
		$this->assertStringContainsString( 'with_term_compat_authority(', $chunk );
	}

	/**
	 * Honest hosted retirement uses ignored + empty error_code, not orphaned.
	 */
	public function test_retire_hosted_uses_ignored_without_orphaned(): void {
		$store = file_get_contents( dirname( __DIR__, 3 ) . '/src/Translation/Store.php' );
		$this->assertIsString( $store );

		$start = strpos( $store, 'function retire_hosted_compat_row' );
		$this->assertNotFalse( $start );
		$chunk = substr( $store, (int) $start, 900 );

		$this->assertStringContainsString( 'self::STATUS_IGNORED', $chunk );
		$this->assertStringContainsString( "'error_code'    => ''", $chunk );
		$this->assertStringNotContainsString( 'orphaned', $chunk );
	}
}
