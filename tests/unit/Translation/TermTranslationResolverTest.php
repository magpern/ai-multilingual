<?php
/**
 * TermTranslationResolver unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Translation;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermTranslationResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Translation\TermTranslationResolver
 */
final class TermTranslationResolverTest extends TestCase {

	private const LANGUAGE_ID = 2;
	private const TERM_ID     = 7;
	private const SHOP_ID     = 99;
	private const POSTS_ID    = 55;

	private Cache $cache;

	private Store $store;

	private TermTranslationResolver $resolver;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['aiml_unit_object_cache']            = array();
		$GLOBALS['aiml_unit_options']                 = array( 'page_for_posts' => self::POSTS_ID );
		$GLOBALS['aiml_unit_wc_pages']                = array( 'shop' => self::SHOP_ID );
		$GLOBALS['aiml_unit_wc_attribute_taxonomies'] = array();
		$GLOBALS['aiml_unit_taxonomies']              = array();
		AdmittedTaxonomies::reset_for_tests();

		$this->cache    = new Cache();
		$this->store    = new Store( $this->cache );
		$this->resolver = new TermTranslationResolver( $this->store );
	}

	protected function tearDown(): void {
		$GLOBALS['aiml_unit_object_cache']            = array();
		$GLOBALS['aiml_unit_options']                 = array();
		$GLOBALS['aiml_unit_wc_pages']                = array();
		$GLOBALS['aiml_unit_wc_attribute_taxonomies'] = array();
		$GLOBALS['aiml_unit_taxonomies']              = array();
		AdmittedTaxonomies::reset_for_tests();
		parent::tearDown();
	}

	public function test_native_row_wins_over_a_still_present_hosted_row(): void {
		$this->seed_segments( Store::SOURCE_TERM, self::TERM_ID, array( 'name' => $this->row( 'Native' ) ) );
		$this->seed_segments(
			Store::SOURCE_POST,
			self::SHOP_ID,
			array( 'p:woocommerce:product_cat:7:name' => $this->row( 'Hosted' ) )
		);

		$resolved = $this->resolver->resolve( self::TERM_ID, 'product_cat', 'name', self::LANGUAGE_ID );

		$this->assertNotNull( $resolved );
		$this->assertSame( TermTranslationResolver::IDENTITY_NATIVE, $resolved['identity'] );
		$this->assertSame( Store::SOURCE_TERM, $resolved['source_type'] );
		$this->assertSame( self::TERM_ID, $resolved['source_id'] );
		$this->assertSame( 'name', $resolved['segment_key'] );
		$this->assertSame( 'Native', $resolved['row']->translated_text );
	}

	public function test_hosted_row_answers_until_the_term_is_adopted(): void {
		$this->seed_segments( Store::SOURCE_TERM, self::TERM_ID, array() );
		$this->seed_segments(
			Store::SOURCE_POST,
			self::SHOP_ID,
			array( 'p:woocommerce:product_cat:7:description' => $this->row( 'Hosted' ) )
		);

		$resolved = $this->resolver->resolve( self::TERM_ID, 'product_cat', 'description', self::LANGUAGE_ID );

		$this->assertNotNull( $resolved );
		$this->assertSame( TermTranslationResolver::IDENTITY_COMPATIBILITY, $resolved['identity'] );
		$this->assertSame( Store::SOURCE_POST, $resolved['source_type'] );
		$this->assertSame( self::SHOP_ID, $resolved['source_id'] );
		$this->assertSame( 'p:woocommerce:product_cat:7:description', $resolved['segment_key'] );
	}

	public function test_rank_math_term_seo_is_hosted_on_the_posts_page_for_core_taxonomies(): void {
		$key = 'p:rankmath:term:7:title';

		$this->seed_segments( Store::SOURCE_TERM, self::TERM_ID, array() );
		$this->seed_segments( Store::SOURCE_POST, self::POSTS_ID, array( $key => $this->row( 'Hosted SEO' ) ) );

		$resolved = $this->resolver->resolve( self::TERM_ID, 'category', $key, self::LANGUAGE_ID );

		$this->assertNotNull( $resolved );
		$this->assertSame( TermTranslationResolver::IDENTITY_COMPATIBILITY, $resolved['identity'] );
		$this->assertSame( self::POSTS_ID, $resolved['source_id'] );
		$this->assertSame( $key, $resolved['segment_key'], 'Rank Math keys survive adoption unchanged.' );
	}

	public function test_core_term_name_has_no_hosted_address(): void {
		$this->seed_segments( Store::SOURCE_TERM, self::TERM_ID, array() );

		$ref = $this->resolver->compat_ref( self::TERM_ID, 'category', 'name', self::LANGUAGE_ID );

		$this->assertNotNull( $ref );
		$this->assertFalse( $ref->has_hosted_address() );
		$this->assertNull( $this->resolver->resolve( self::TERM_ID, 'category', 'name', self::LANGUAGE_ID ) );
	}

	public function test_attribute_term_values_are_native_only(): void {
		$GLOBALS['aiml_unit_wc_attribute_taxonomies'] = array( array( 'attribute_name' => 'color' ) );
		$GLOBALS['aiml_unit_taxonomies']['pa_color']  = array( 'public' => true );
		AdmittedTaxonomies::reset_for_tests();

		$ref = $this->resolver->compat_ref( self::TERM_ID, 'pa_color', 'name', self::LANGUAGE_ID );

		$this->assertNotNull( $ref );
		$this->assertFalse( $ref->has_hosted_address() );
		$this->assertSame( 'name', $ref->native_segment_key );
	}

	public function test_forbidden_taxonomies_and_fields_never_resolve(): void {
		$this->assertNull( $this->resolver->compat_ref( self::TERM_ID, 'nav_menu', 'name', self::LANGUAGE_ID ) );
		$this->assertNull( $this->resolver->compat_ref( self::TERM_ID, 'category', 'slug', self::LANGUAGE_ID ) );
		$this->assertNull( $this->resolver->compat_ref( 0, 'category', 'name', self::LANGUAGE_ID ) );
		$this->assertNull( $this->resolver->compat_ref( self::TERM_ID, 'category', 'name', 0 ) );
	}

	public function test_compat_ref_describes_both_addresses_for_catalog_terms(): void {
		$ref = $this->resolver->compat_ref( self::TERM_ID, 'product_cat', 'name', self::LANGUAGE_ID );

		$this->assertNotNull( $ref );
		$this->assertTrue( $ref->has_hosted_address() );
		$this->assertSame( Store::SOURCE_POST, $ref->hosted_source_type );
		$this->assertSame( self::SHOP_ID, $ref->hosted_source_id );
		$this->assertSame( 'p:woocommerce:product_cat:7:name', $ref->hosted_segment_key );

		$identity = $ref->to_native_identity();
		$this->assertSame( Store::SOURCE_TERM, $identity['source_type'] );
		$this->assertSame( 'product_cat', $identity['source_subtype'] );
		$this->assertSame( 'name', $identity['segment_key'] );
	}

	public function test_rank_math_segment_key_is_built_once_here(): void {
		$this->assertSame(
			'p:rankmath:term:7:description',
			$this->resolver->rank_math_segment_key( self::TERM_ID, 'description' )
		);
		$this->assertSame( '', $this->resolver->rank_math_segment_key( 0, 'description' ) );
		$this->assertSame( '', $this->resolver->rank_math_segment_key( self::TERM_ID, '' ) );
	}

	/**
	 * Seeds the Store segment cache so reads never touch the database.
	 *
	 * @param string                $source_type Source type.
	 * @param int                   $source_id   Source id.
	 * @param array<string, object> $segments    Rows keyed by segment key.
	 */
	private function seed_segments( string $source_type, int $source_id, array $segments ): void {
		$this->cache->set( sprintf( 'seg:%s:%d', $source_type, $source_id ), self::LANGUAGE_ID, $segments );
	}

	/**
	 * Builds a minimal stored row.
	 *
	 * @param string $translated_text Translated text.
	 */
	private function row( string $translated_text ): object {
		return (object) array(
			'translation_id'  => 1,
			'language_id'     => self::LANGUAGE_ID,
			'translated_text' => $translated_text,
			'status'          => Store::STATUS_MANUALLY_EDITED,
		);
	}
}
