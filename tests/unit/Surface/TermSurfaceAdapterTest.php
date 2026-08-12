<?php
/**
 * TermSurfaceAdapter unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Surface;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Surface\PostSurfaceAdapter;
use AIMultilingual\Surface\RequestLocalInvalidationCoordinator;
use AIMultilingual\Surface\SurfaceCapabilityNames;
use AIMultilingual\Surface\SurfaceRegistry;
use AIMultilingual\Surface\TermSurfaceAdapter;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermExtractor;
use PHPUnit\Framework\TestCase;
use WP_Term;

/**
 * @covers \AIMultilingual\Surface\TermSurfaceAdapter
 */
final class TermSurfaceAdapterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['aiml_unit_terms']            = array();
		$GLOBALS['aiml_unit_taxonomies']       = array();
		$GLOBALS['aiml_unit_user_can']         = array();
		$GLOBALS['aiml_unit_current_user_can'] = array();
		AdmittedTaxonomies::reset_for_tests();
	}

	protected function tearDown(): void {
		$GLOBALS['aiml_unit_terms']            = array();
		$GLOBALS['aiml_unit_taxonomies']       = array();
		$GLOBALS['aiml_unit_user_can']         = array();
		$GLOBALS['aiml_unit_current_user_can'] = array();
		AdmittedTaxonomies::reset_for_tests();
		parent::tearDown();
	}

	public function test_source_type_and_capability_declarations(): void {
		$adapter = new TermSurfaceAdapter();

		$this->assertSame( Store::SOURCE_TERM, $adapter->source_type() );
		foreach ( SurfaceCapabilityNames::all() as $capability ) {
			$this->assertTrue( $adapter->supports( $capability ) );
		}
		$this->assertFalse( $adapter->supports( 'publish_policy' ) );
	}

	public function test_rank_math_allowlist_matches_the_post_surface(): void {
		$this->assertSame(
			PostSurfaceAdapter::RANK_MATH_SEO_META_KEYS,
			TermSurfaceAdapter::RANK_MATH_SEO_META_KEYS
		);
	}

	public function test_feature_implemented_covers_rank_math_only(): void {
		$adapter = new TermSurfaceAdapter();

		$this->assertTrue( $adapter->feature_implemented( 'rank_math_seo' ) );
		$this->assertFalse( $adapter->feature_implemented( 'block_extraction' ) );
		$this->assertFalse( $adapter->feature_activated( 'rank_math_seo' ) );
	}

	public function test_exists_and_subtype_follow_admission(): void {
		$this->add_term( 7, 'product_cat', 'Shoes' );
		$this->add_term( 8, 'nav_menu', 'Primary' );

		$adapter = new TermSurfaceAdapter();

		$this->assertTrue( $adapter->exists( 7 ) );
		$this->assertSame( 'product_cat', $adapter->source_subtype( 7 ) );

		$this->assertFalse( $adapter->exists( 8 ), 'A forbidden taxonomy is not a term surface.' );
		$this->assertSame( '', $adapter->source_subtype( 8 ) );
		$this->assertFalse( $adapter->exists( 404 ) );
	}

	public function test_visitor_visibility_requires_a_public_taxonomy(): void {
		$this->add_term( 7, 'product_cat', 'Shoes' );
		$adapter = new TermSurfaceAdapter();

		$GLOBALS['aiml_unit_taxonomies']['product_cat'] = array( 'public' => true );
		$this->assertTrue( $adapter->is_visitor_public( 7 ) );

		$GLOBALS['aiml_unit_taxonomies']['product_cat'] = array(
			'public'             => true,
			'publicly_queryable' => false,
		);
		$this->assertFalse( $adapter->is_visitor_public( 7 ) );

		$GLOBALS['aiml_unit_taxonomies']['product_cat'] = array( 'public' => false );
		$this->assertFalse( $adapter->is_visitor_public( 7 ) );
	}

	public function test_user_can_edit_source_uses_the_edit_term_capability(): void {
		$GLOBALS['aiml_unit_user_can'][5]['edit_term']      = true;
		$GLOBALS['aiml_unit_current_user_can']['edit_term'] = true;

		$adapter = new TermSurfaceAdapter();

		$this->assertTrue( $adapter->user_can_edit_source( 5, 7 ) );
		$this->assertFalse( $adapter->user_can_edit_source( 6, 7 ) );
		$this->assertTrue( $adapter->user_can_edit_source( 0, 7 ) );
		$this->assertFalse( $adapter->user_can_edit_source( 0, 0 ) );
	}

	public function test_extract_segments_delegates_to_the_term_extractor(): void {
		$this->add_term( 7, 'category', 'News', '<p>Latest</p>' );

		$adapter = new TermSurfaceAdapter( new TermExtractor() );

		$segments = $adapter->extract_segments( 7 );

		$this->assertSame(
			array( TermExtractor::FIELD_NAME, TermExtractor::FIELD_DESCRIPTION ),
			array_keys( $segments )
		);
		$this->assertSame( 'News', $segments[ TermExtractor::FIELD_NAME ]['source_text'] );
		$this->assertSame( Store::FORMAT_PLAIN, $segments[ TermExtractor::FIELD_NAME ]['text_format'] );
		$this->assertSame( Store::FORMAT_HTML, $segments[ TermExtractor::FIELD_DESCRIPTION ]['text_format'] );
	}

	public function test_extract_segments_is_empty_without_an_extractor(): void {
		$this->add_term( 7, 'category', 'News' );

		$this->assertSame( array(), ( new TermSurfaceAdapter() )->extract_segments( 7 ) );
	}

	public function test_register_invalidation_events_accepts_coordinator(): void {
		$coordinator = new RequestLocalInvalidationCoordinator(
			new Store( new Cache() ),
			new SurfaceRegistry()
		);

		( new TermSurfaceAdapter( new TermExtractor() ) )->register_invalidation_events( $coordinator );

		$this->assertSame( 0, $coordinator->dirty_count() );
	}

	/**
	 * @param int    $term_id     Term id.
	 * @param string $taxonomy    Taxonomy slug.
	 * @param string $name        Term name.
	 * @param string $description Term description.
	 */
	private function add_term( int $term_id, string $taxonomy, string $name, string $description = '' ): void {
		$term              = new WP_Term();
		$term->term_id     = $term_id;
		$term->taxonomy    = $taxonomy;
		$term->name        = $name;
		$term->description = $description;

		$GLOBALS['aiml_unit_terms'][ $term_id ] = $term;
	}
}
