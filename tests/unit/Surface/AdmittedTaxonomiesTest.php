<?php
/**
 * AdmittedTaxonomies unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Surface;

use AIMultilingual\Surface\AdmittedTaxonomies;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Surface\AdmittedTaxonomies
 */
final class AdmittedTaxonomiesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['aiml_unit_taxonomies']              = array();
		$GLOBALS['aiml_unit_wc_attribute_taxonomies'] = array();
		AdmittedTaxonomies::reset_for_tests();
	}

	protected function tearDown(): void {
		$GLOBALS['aiml_unit_taxonomies']              = array();
		$GLOBALS['aiml_unit_wc_attribute_taxonomies'] = array();
		AdmittedTaxonomies::reset_for_tests();
		parent::tearDown();
	}

	public function test_core_and_catalog_taxonomies_are_admitted(): void {
		$this->assertTrue( AdmittedTaxonomies::admits( 'category' ) );
		$this->assertTrue( AdmittedTaxonomies::admits( 'post_tag' ) );
		$this->assertTrue( AdmittedTaxonomies::admits( 'product_cat' ) );
		$this->assertTrue( AdmittedTaxonomies::admits( 'product_tag' ) );
	}

	public function test_unlisted_taxonomies_are_refused(): void {
		$this->assertFalse( AdmittedTaxonomies::admits( 'nav_menu' ) );
		$this->assertFalse( AdmittedTaxonomies::admits( 'post_format' ) );
		$this->assertFalse( AdmittedTaxonomies::admits( 'product_shipping_class' ) );
		$this->assertFalse( AdmittedTaxonomies::admits( '' ) );
	}

	public function test_public_woocommerce_attribute_taxonomy_is_admitted(): void {
		$GLOBALS['aiml_unit_wc_attribute_taxonomies'] = array( array( 'attribute_name' => 'color' ) );
		$GLOBALS['aiml_unit_taxonomies']['pa_color']  = array(
			'public'             => true,
			'publicly_queryable' => true,
		);

		$this->assertTrue( AdmittedTaxonomies::admits( 'pa_color' ) );
		$this->assertContains( 'pa_color', AdmittedTaxonomies::all() );
	}

	public function test_non_public_attribute_taxonomy_is_refused(): void {
		$GLOBALS['aiml_unit_wc_attribute_taxonomies']   = array( array( 'attribute_name' => 'internal' ) );
		$GLOBALS['aiml_unit_taxonomies']['pa_internal'] = array( 'public' => false );

		$this->assertFalse( AdmittedTaxonomies::admits( 'pa_internal' ) );
	}

	public function test_prefixed_taxonomy_woocommerce_does_not_own_is_refused(): void {
		$GLOBALS['aiml_unit_taxonomies']['pa_stranger'] = array( 'public' => true );

		$this->assertFalse( AdmittedTaxonomies::admits( 'pa_stranger' ) );
	}

	public function test_admitted_set_is_unique_and_memoized(): void {
		$first = AdmittedTaxonomies::all();

		$GLOBALS['aiml_unit_wc_attribute_taxonomies'] = array( array( 'attribute_name' => 'size' ) );
		$GLOBALS['aiml_unit_taxonomies']['pa_size']   = array( 'public' => true );

		$this->assertSame( $first, AdmittedTaxonomies::all(), 'The set is resolved once per request.' );

		AdmittedTaxonomies::reset_for_tests();

		$refreshed = AdmittedTaxonomies::all();
		$this->assertContains( 'pa_size', $refreshed );
		$this->assertSame( array_values( array_unique( $refreshed ) ), $refreshed );
	}
}
