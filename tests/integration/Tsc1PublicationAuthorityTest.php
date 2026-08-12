<?php
/**
 * TSC.1 PublicationService term-authority remapping and axis serialization.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Settings;
use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Surface\PostSurfaceAdapter;
use AIMultilingual\Surface\SurfaceRegistry;
use AIMultilingual\Surface\TermSurfaceAdapter;
use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\Publication\PublicationAuditLogger;
use AIMultilingual\Translation\Publication\PublicationDecision;
use AIMultilingual\Translation\Publication\PublicationMode;
use AIMultilingual\Translation\Publication\PublicationPolicy;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermAdoptionService;
use AIMultilingual\Translation\TermExtractor;
use AIMultilingual\Translation\TermTranslationResolver;
use WP_Error;

/**
 * Prove explain/publish/unpublish remap onto authoritative term rows under Store lock.
 */
final class Tsc1PublicationAuthorityTest extends AimlTestCase {

	private TermAdoptionService $adoption;

	private TermTranslationResolver $term_resolver;

	private PublicationService $publication;

	protected function setUp(): void {
		parent::setUp();

		AdmittedTaxonomies::reset_for_tests();

		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array_merge(
					Settings::defaults(),
					array(
						'segment_publication_gate_enabled' => false,
						'auto_publication_mode'            => PublicationMode::MANUAL,
					)
				)
			)
		);

		$this->term_resolver = new TermTranslationResolver( $this->store );
		$this->adoption      = new TermAdoptionService( $this->store, new TermExtractor(), $this->term_resolver );

		$surfaces = new SurfaceRegistry();
		$surfaces->register( new PostSurfaceAdapter() );
		$surfaces->register( new TermSurfaceAdapter() );

		$this->publication = new PublicationService(
			$this->store,
			new AssessmentAssembler(),
			new PublicationPolicy(),
			new PublicationAuditLogger(),
			new Settings(),
			$surfaces,
			$this->term_resolver
		);
	}

	protected function tearDown(): void {
		AdmittedTaxonomies::reset_for_tests();
		parent::tearDown();
	}

	public function test_explain_with_term_resolver_does_not_fatal_for_hosted_and_native(): void {
		$language = $this->add_language();
		$term_id  = $this->create_product_cat( 'Explain Boots' );
		$shop_id  = $this->ensure_shop_page();
		$key      = 'p:woocommerce:product_cat:' . $term_id . ':name';

		$this->seed_hosted_term_name( $shop_id, (int) $language->language_id, $key, 'Explain Boots', 'Förklara stövlar' );

		$hosted_decision = $this->publication->explain(
			Store::SOURCE_POST,
			$shop_id,
			(int) $language->language_id,
			$key,
			false
		);
		$this->assertInstanceOf( PublicationDecision::class, $hosted_decision );
		$this->assertNotInstanceOf( WP_Error::class, $hosted_decision );

		$native = $this->adoption->adopt_logical_field(
			$term_id,
			'product_cat',
			(int) $language->language_id,
			'name'
		);
		$this->assertIsObject( $native );

		$native_decision = $this->publication->explain(
			Store::SOURCE_TERM,
			$term_id,
			(int) $language->language_id,
			'name',
			false
		);
		$this->assertInstanceOf( PublicationDecision::class, $native_decision );

		$hosted_after = $this->publication->explain(
			Store::SOURCE_POST,
			$shop_id,
			(int) $language->language_id,
			$key,
			false
		);
		$this->assertInstanceOf( PublicationDecision::class, $hosted_after );
	}

	public function test_publish_hosted_address_after_adopt_mutates_native_only(): void {
		$language = $this->add_language( 'de', 'de_DE' );
		$term_id  = $this->create_product_cat( 'Publish Hats' );
		$shop_id  = $this->ensure_shop_page();
		$key      = 'p:woocommerce:product_cat:' . $term_id . ':name';

		$this->seed_hosted_term_name( $shop_id, (int) $language->language_id, $key, 'Publish Hats', 'Hüte' );

		$native = $this->adoption->adopt_logical_field(
			$term_id,
			'product_cat',
			(int) $language->language_id,
			'name'
		);
		$this->assertIsObject( $native );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $native->publish_status );

		$result = $this->publication->publish(
			Store::SOURCE_POST,
			$shop_id,
			(int) $language->language_id,
			$key,
			false,
			1,
			'manual'
		);
		$this->assertIsArray( $result );
		$this->assertSame( 'published', $result['status'] );

		$native_after = $this->store->get( Store::SOURCE_TERM, $term_id, (int) $language->language_id, 'name' );
		$this->assertNotNull( $native_after );
		$this->assertSame( Store::PUBLISH_PUBLISHED, (string) $native_after->publish_status );

		$hosted = $this->store->get( Store::SOURCE_POST, $shop_id, (int) $language->language_id, $key );
		$this->assertNotNull( $hosted );
		$this->assertSame( Store::STATUS_IGNORED, (string) $hosted->status );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $hosted->publish_status );
	}

	public function test_unpublish_hosted_address_after_adopt_clears_native_only(): void {
		$language = $this->add_language( 'fr', 'fr_FR' );
		$term_id  = $this->create_product_cat( 'Unpublish Gloves' );
		$shop_id  = $this->ensure_shop_page();
		$key      = 'p:woocommerce:product_cat:' . $term_id . ':name';

		$this->seed_hosted_term_name( $shop_id, (int) $language->language_id, $key, 'Unpublish Gloves', 'Gants' );

		$native = $this->adoption->adopt_logical_field(
			$term_id,
			'product_cat',
			(int) $language->language_id,
			'name'
		);
		$this->assertIsObject( $native );

		$published = $this->publication->publish(
			Store::SOURCE_TERM,
			$term_id,
			(int) $language->language_id,
			'name',
			false,
			1,
			'manual'
		);
		$this->assertIsArray( $published );
		$this->assertSame( 'published', $published['status'] );

		$unpub = $this->publication->unpublish(
			Store::SOURCE_POST,
			$shop_id,
			(int) $language->language_id,
			$key,
			1
		);
		$this->assertIsArray( $unpub );
		$this->assertSame( 'unpublished', $unpub['status'] );

		$native_after = $this->store->get( Store::SOURCE_TERM, $term_id, (int) $language->language_id, 'name' );
		$this->assertNotNull( $native_after );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $native_after->publish_status );

		$hosted = $this->store->get( Store::SOURCE_POST, $shop_id, (int) $language->language_id, $key );
		$this->assertNotNull( $hosted );
		$this->assertSame( Store::STATUS_IGNORED, (string) $hosted->status );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $hosted->publish_status );
	}

	/**
	 * Seeds a hosted Woo product_cat name row with review approved for TI.7.
	 *
	 * @param int    $shop_id     Shop page id.
	 * @param int    $language_id Language id.
	 * @param string $key         Hosted segment key.
	 * @param string $source      Source text.
	 * @param string $translated  Translated text.
	 */
	private function seed_hosted_term_name(
		int $shop_id,
		int $language_id,
		string $key,
		string $source,
		string $translated
	): void {
		$this->assertTrue(
			$this->store->save_translation(
				array(
					'source_type'     => Store::SOURCE_POST,
					'source_id'       => $shop_id,
					'source_subtype'  => 'page',
					'language_id'     => $language_id,
					'field_key'       => '_plugin',
					'segment_key'     => $key,
					'source_text'     => $source,
					'translated_text' => $translated,
					'status'          => Store::STATUS_MANUALLY_EDITED,
					'provider'        => 'openai',
					'model'           => 'gpt-test',
					'prompt_profile'  => 'default',
					'prompt_version'  => '1',
				)
			)
		);

		$this->assertTrue(
			$this->store->update_review_metadata(
				Store::SOURCE_POST,
				$shop_id,
				$language_id,
				$key,
				array( 'review_status' => Store::REVIEW_APPROVED )
			)
		);
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
