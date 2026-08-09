<?php
/**
 * A.SEOb Deferred / out-of-scope guardrails.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Database\Migrator;
use AIMultilingual\Database\Schema;
use AIMultilingual\Translation\Store;
use ReflectionClass;

/**
 * Proves A.SEOb did not ship Deferred leaf-slug / history / scrape scope.
 */
final class AseobDeferredGuardTest extends AimlTestCase {

	public function test_target_remains_six(): void {
		$this->assertSame( 6, Migrator::TARGET );
	}

	public function test_no_url_history_or_relationship_tables(): void {
		foreach ( Schema::all_tables() as $table ) {
			$this->assertDoesNotMatchRegularExpression(
				'/slug|redirect_history|url_history|hreflang_graph|seo_relationship/i',
				(string) $table
			);
		}
	}

	public function test_no_reverse_slug_lookup_on_store(): void {
		foreach ( get_class_methods( Store::class ) as $method ) {
			$this->assertDoesNotMatchRegularExpression(
				'/by_slug|reverse|lookup_slug|find_by_translated/i',
				$method
			);
		}
	}

	public function test_no_deferred_seo_emitter_classes(): void {
		foreach ( array(
			'AIMultilingual\\Seo\\SitemapEmitter',
			'AIMultilingual\\Seo\\OpenGraphEmitter',
			'AIMultilingual\\Seo\\RankMathTitleBridge',
			'AIMultilingual\\Seo\\UrlHistoryStore',
		) as $class ) {
			$this->assertFalse( class_exists( $class ), $class );
		}
	}

	public function test_sb11_service_has_no_future_wave_deps(): void {
		$ref    = new ReflectionClass( \AIMultilingual\Seo\LanguageRelationshipService::class );
		$source = (string) file_get_contents( (string) $ref->getFileName() );
		$this->assertStringNotContainsString( 'RankMath\\', $source );
		$this->assertStringNotContainsString( 'OpenGraph', $source );
		$this->assertStringNotContainsString( 'SitemapEmitter', $source );
		$this->assertDoesNotMatchRegularExpression( '/use\s+AIMultilingual\\\\Seo\\\\(?!LanguageRelationship)/', $source );
	}
}
