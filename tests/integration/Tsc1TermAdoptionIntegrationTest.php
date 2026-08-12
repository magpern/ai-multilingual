<?php
/**
 * TSC.1 term adoption integration proofs against a real Store table.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermAdoptionService;
use AIMultilingual\Translation\TermExtractor;
use AIMultilingual\Translation\TermTranslationResolver;
use WP_Error;

/**
 * Real-DB adopt / authority remap / rollback / content-write ensure.
 */
final class Tsc1TermAdoptionIntegrationTest extends AimlTestCase {

	private TermAdoptionService $adoption;

	private TermTranslationResolver $term_resolver;

	protected function setUp(): void {
		parent::setUp();

		AdmittedTaxonomies::reset_for_tests();
		$this->term_resolver = new TermTranslationResolver( $this->store );
		$this->adoption      = new TermAdoptionService( $this->store, new TermExtractor(), $this->term_resolver );
	}

	protected function tearDown(): void {
		AdmittedTaxonomies::reset_for_tests();
		parent::tearDown();
	}

	public function test_real_adopt_via_term_adoption_service(): void {
		$language = $this->add_language();
		$term_id  = $this->create_product_cat( 'Boots' );
		$shop_id  = $this->ensure_shop_page();
		$key      = 'p:woocommerce:product_cat:' . $term_id . ':name';
		$hash     = Store::translation_hash( 'Stövlar' );

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $shop_id,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => '_plugin',
				'segment_key'     => $key,
				'source_text'     => 'Boots',
				'translated_text' => 'Stövlar',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		// Re-apply lifecycle axes after save_translation (which clears them on insert).
		$this->assertTrue(
			$this->store->update_review_metadata(
				Store::SOURCE_POST,
				$shop_id,
				(int) $language->language_id,
				$key,
				array(
					'review_status'              => Store::REVIEW_APPROVED,
					'submitted_translation_hash' => $hash,
					'reviewed_by'                => 1,
					'reviewed_at'                => current_time( 'mysql', true ),
				)
			)
		);
		$this->assertTrue(
			$this->store->update_publish_metadata(
				Store::SOURCE_POST,
				$shop_id,
				(int) $language->language_id,
				$key,
				array(
					'publish_status' => Store::PUBLISH_PUBLISHED,
					'published_at'   => current_time( 'mysql', true ),
					'published_by'   => 1,
				)
			)
		);

		$native = $this->adoption->adopt_logical_field(
			$term_id,
			'product_cat',
			(int) $language->language_id,
			'name'
		);

		$this->assertIsObject( $native );
		$this->assertSame( Store::SOURCE_TERM, $native->source_type );
		$this->assertSame( $term_id, (int) $native->source_id );
		$this->assertSame( 'name', $native->segment_key );
		$this->assertSame( 'Stövlar', $native->translated_text );
		$this->assertSame( Store::REVIEW_APPROVED, $native->review_status );
		$this->assertSame( Store::PUBLISH_PUBLISHED, $native->publish_status );

		$hosted = $this->store->get( Store::SOURCE_POST, $shop_id, (int) $language->language_id, $key );
		$this->assertNotNull( $hosted );
		$this->assertSame( Store::STATUS_IGNORED, $hosted->status );
		$this->assertSame( '', (string) ( $hosted->error_code ?? '' ) );
	}

	public function test_mutate_under_term_compat_authority_remaps_to_native_after_adopt(): void {
		$language = $this->add_language( 'de', 'de_DE' );
		$term_id  = $this->create_product_cat( 'Hats' );
		$shop_id  = $this->ensure_shop_page();
		$key      = 'p:woocommerce:product_cat:' . $term_id . ':name';

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $shop_id,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => '_plugin',
				'segment_key'     => $key,
				'source_text'     => 'Hats',
				'translated_text' => 'Hüte',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$adopted = $this->adoption->adopt_logical_field(
			$term_id,
			'product_cat',
			(int) $language->language_id,
			'name'
		);
		$this->assertIsObject( $adopted );

		$ref = $this->term_resolver->compat_ref( $term_id, 'product_cat', 'name', (int) $language->language_id );
		$this->assertNotNull( $ref );

		$seen = null;
		$result = $this->store->mutate_under_term_compat_authority(
			$ref->to_store_ref(),
			static function ( string $source_type, int $source_id, int $language_id, string $segment_key, object $row ) use ( &$seen ) {
				$seen = array(
					'source_type' => $source_type,
					'source_id'   => $source_id,
					'segment_key' => $segment_key,
					'row_id'      => (int) $row->translation_id,
				);

				return true;
			}
		);

		$this->assertTrue( $result );
		$this->assertIsArray( $seen );
		$this->assertSame( Store::SOURCE_TERM, $seen['source_type'] );
		$this->assertSame( $term_id, $seen['source_id'] );
		$this->assertSame( 'name', $seen['segment_key'] );
		$this->assertSame( (int) $adopted->translation_id, $seen['row_id'] );
	}

	public function test_rollback_on_wp_error_leaves_hosted_intact(): void {
		$language = $this->add_language( 'fr', 'fr_FR' );
		$term_id  = $this->create_product_cat( 'Gloves' );
		$shop_id  = $this->ensure_shop_page();
		$key      = 'p:woocommerce:product_cat:' . $term_id . ':name';

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $shop_id,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => '_plugin',
				'segment_key'     => $key,
				'source_text'     => 'Gloves',
				'translated_text' => 'Gants',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$ref = $this->term_resolver->compat_ref( $term_id, 'product_cat', 'name', (int) $language->language_id );
		$this->assertNotNull( $ref );

		$result = $this->store->with_term_compat_authority(
			$ref->to_store_ref(),
			static function () {
				return new WP_Error( 'aiml_test_rollback', 'force rollback' );
			}
		);

		$this->assertInstanceOf( WP_Error::class, $result );

		$hosted = $this->store->get( Store::SOURCE_POST, $shop_id, (int) $language->language_id, $key );
		$this->assertNotNull( $hosted );
		$this->assertSame( Store::STATUS_MANUALLY_EDITED, $hosted->status );
		$this->assertSame( 'Gants', $hosted->translated_text );
		$this->assertNull(
			$this->store->get( Store::SOURCE_TERM, $term_id, (int) $language->language_id, 'name' )
		);
	}

	public function test_ensure_native_before_content_write_adopts_then_allows_native_get(): void {
		$language = $this->add_language( 'nl', 'nl_NL' );
		$term_id  = $this->create_product_cat( 'Scarves' );
		$shop_id  = $this->ensure_shop_page();
		$key      = 'p:woocommerce:product_cat:' . $term_id . ':name';

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $shop_id,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => '_plugin',
				'segment_key'     => $key,
				'source_text'     => 'Scarves',
				'translated_text' => 'Sjaals',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$ok = $this->adoption->ensure_native_before_content_write(
			$term_id,
			'product_cat',
			(int) $language->language_id,
			'name'
		);
		$this->assertTrue( $ok );

		$native = $this->store->get( Store::SOURCE_TERM, $term_id, (int) $language->language_id, 'name' );
		$this->assertNotNull( $native );
		$this->assertSame( 'Sjaals', $native->translated_text );

		$identity = $this->adoption->native_write_identity(
			Store::SOURCE_POST,
			$shop_id,
			(int) $language->language_id,
			$key
		);
		$this->assertIsArray( $identity );
		$this->assertSame( Store::SOURCE_TERM, $identity['source_type'] );
		$this->assertSame( $term_id, (int) $identity['source_id'] );
		$this->assertSame( 'name', $identity['segment_key'] );
	}

	/**
	 * Ensures the WooCommerce shop page exists and returns its id.
	 */
	private function ensure_shop_page(): int {
		$shop_id = (int) wc_get_page_id( 'shop' );
		if ( $shop_id > 0 ) {
			return $shop_id;
		}

		$page = $this->create_page( 'Shop', '<p>Shop</p>' );
		update_option( 'woocommerce_shop_page_id', (int) $page->ID );

		return (int) $page->ID;
	}

	/**
	 * Creates a product_cat term.
	 *
	 * @param string $name Term name.
	 */
	private function create_product_cat( string $name ): int {
		$result = wp_insert_term( $name, 'product_cat' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'term_id', $result );

		return (int) $result['term_id'];
	}
}
