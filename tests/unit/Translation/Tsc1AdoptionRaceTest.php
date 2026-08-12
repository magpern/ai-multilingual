<?php
/**
 * TSC.1 adoption race matrix unit coverage.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermAdoptionService;
use AIMultilingual\Translation\TermExtractor;
use AIMultilingual\Translation\TermTranslationResolver;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_Term;

require_once __DIR__ . '/AimlUnitWpdb.php';

/**
 * Frozen Store adoption race / failure matrix (AC7–AC17, AC33–AC35) with an
 * in-memory wpdb harness so adopt paths do not need a WordPress bootstrap.
 *
 * @covers \AIMultilingual\Translation\TermAdoptionService
 * @covers \AIMultilingual\Translation\Store
 * @covers \AIMultilingual\Translation\TermTranslationResolver
 */
final class Tsc1AdoptionRaceTest extends TestCase {

	private const LANGUAGE_ID = 2;
	private const TERM_ID     = 7;
	private const SHOP_ID     = 99;
	private const POSTS_ID    = 55;

	private AimlUnitWpdb $wpdb;

	private Cache $cache;

	private Store $store;

	private TermAdoptionService $adoption;

	private TermTranslationResolver $resolver;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['aiml_unit_object_cache']            = array();
		$GLOBALS['aiml_unit_options']                 = array( 'page_for_posts' => self::POSTS_ID );
		$GLOBALS['aiml_unit_wc_pages']                = array( 'shop' => self::SHOP_ID );
		$GLOBALS['aiml_unit_wc_attribute_taxonomies'] = array();
		$GLOBALS['aiml_unit_taxonomies']              = array(
			'product_cat' => array( 'public' => true ),
			'category'    => array( 'public' => true ),
			'nav_menu'    => array( 'public' => false ),
		);
		$GLOBALS['aiml_unit_terms']                   = array();
		AdmittedTaxonomies::reset_for_tests();

		$term           = new WP_Term();
		$term->term_id  = self::TERM_ID;
		$term->taxonomy = 'product_cat';
		$term->name     = 'Shoes';
		$term->description = 'Footwear';
		$GLOBALS['aiml_unit_terms'][ self::TERM_ID ] = $term;

		$this->wpdb              = new AimlUnitWpdb();
		$GLOBALS['wpdb']         = $this->wpdb;
		$this->cache             = new Cache();
		$this->store             = new Store( $this->cache );
		$this->resolver          = new TermTranslationResolver( $this->store );
		$this->adoption          = new TermAdoptionService( $this->store, new TermExtractor(), $this->resolver );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		$GLOBALS['aiml_unit_object_cache']            = array();
		$GLOBALS['aiml_unit_options']                 = array();
		$GLOBALS['aiml_unit_wc_pages']                = array();
		$GLOBALS['aiml_unit_wc_attribute_taxonomies'] = array();
		$GLOBALS['aiml_unit_taxonomies']              = array();
		$GLOBALS['aiml_unit_terms']                   = array();
		AdmittedTaxonomies::reset_for_tests();
		parent::tearDown();
	}

	/**
	 * AC7 / AC17 — hosted-only adopt creates native; hosted retired ignored with empty error_code.
	 */
	public function test_hosted_only_adopt_creates_native_and_retires_hosted(): void {
		$hosted_id = $this->seed_hosted(
			'p:woocommerce:product_cat:7:name',
			array(
				'translated_text'  => 'Skor',
				'review_status'    => Store::REVIEW_APPROVED,
				'publish_status'   => Store::PUBLISH_PUBLISHED,
				'published_at'     => '2026-01-01 00:00:00',
				'published_by'     => 3,
				'submitted_translation_hash' => Store::translation_hash( 'Skor' ),
				'translation_hash' => Store::translation_hash( 'Skor' ),
			)
		);

		$result = $this->adoption->adopt_logical_field( self::TERM_ID, 'product_cat', self::LANGUAGE_ID, 'name' );

		$this->assertIsObject( $result );
		$this->assertSame( Store::SOURCE_TERM, $result->source_type );
		$this->assertSame( self::TERM_ID, (int) $result->source_id );
		$this->assertSame( 'name', $result->segment_key );
		$this->assertSame( 'Skor', $result->translated_text );
		$this->assertSame( Store::REVIEW_APPROVED, $result->review_status );
		$this->assertSame( Store::PUBLISH_PUBLISHED, $result->publish_status );

		$hosted = $this->wpdb->row( $hosted_id );
		$this->assertNotNull( $hosted );
		$this->assertSame( Store::STATUS_IGNORED, $hosted->status );
		$this->assertSame( '', (string) $hosted->error_code );
		$this->assertSame( '', (string) ( $hosted->error_message ?? '' ) );
	}

	/**
	 * AC8 — native-only adopt is a no-op that returns the existing native row.
	 */
	public function test_native_only_adopt_is_noop(): void {
		$native_id = $this->seed_native(
			'name',
			array(
				'translated_text' => 'Native',
			)
		);

		$result = $this->adoption->adopt_logical_field( self::TERM_ID, 'product_cat', self::LANGUAGE_ID, 'name' );

		$this->assertIsObject( $result );
		$this->assertSame( $native_id, (int) $result->translation_id );
		$this->assertSame( 'Native', $result->translated_text );
		$this->assertCount( 1, $this->wpdb->all_rows() );
	}

	/**
	 * AC9 — both exist → native wins; hosted retired; native text not overwritten.
	 */
	public function test_both_exist_native_wins_without_overwrite(): void {
		$this->seed_native(
			'name',
			array(
				'translated_text' => 'NativeWins',
			)
		);
		$hosted_id = $this->seed_hosted(
			'p:woocommerce:product_cat:7:name',
			array(
				'translated_text' => 'HostedLoser',
			)
		);

		$result = $this->adoption->adopt_logical_field( self::TERM_ID, 'product_cat', self::LANGUAGE_ID, 'name' );

		$this->assertIsObject( $result );
		$this->assertSame( 'NativeWins', $result->translated_text );
		$this->assertSame( Store::SOURCE_TERM, $result->source_type );

		$hosted = $this->wpdb->row( $hosted_id );
		$this->assertSame( Store::STATUS_IGNORED, $hosted->status );
		$this->assertSame( '', (string) $hosted->error_code );
		$this->assertSame( 'HostedLoser', $hosted->translated_text, 'Hosted text stays for history; native is not overwritten.' );
	}

	/**
	 * AC11 / AC13 / AC14 — adopt does not clear review/publish the way save_translation would.
	 */
	public function test_adopt_preserves_published_and_approved_axes(): void {
		$hash = Store::translation_hash( 'PublishedName' );
		$this->seed_hosted(
			'p:woocommerce:product_cat:7:name',
			array(
				'translated_text'            => 'PublishedName',
				'translation_hash'           => $hash,
				'submitted_translation_hash' => $hash,
				'review_status'              => Store::REVIEW_APPROVED,
				'reviewed_by'                => 9,
				'reviewed_at'                => '2026-02-02 00:00:00',
				'publish_status'             => Store::PUBLISH_PUBLISHED,
				'published_at'               => '2026-02-03 00:00:00',
				'published_by'               => 9,
			)
		);

		$native = $this->adoption->adopt_logical_field( self::TERM_ID, 'product_cat', self::LANGUAGE_ID, 'name' );

		$this->assertIsObject( $native );
		$this->assertSame( Store::REVIEW_APPROVED, $native->review_status );
		$this->assertSame( Store::PUBLISH_PUBLISHED, $native->publish_status );
		$this->assertSame( 9, (int) $native->reviewed_by );
		$this->assertSame( 9, (int) $native->published_by );
		$this->assertSame( $hash, $native->submitted_translation_hash );

		// Contrast: save_translation with a text change would clear both axes.
		$cleared = array_merge( Store::review_clear_fields(), Store::publish_clear_fields() );
		$this->assertSame( Store::REVIEW_NOT_SUBMITTED, $cleared['review_status'] );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, $cleared['publish_status'] );
		$this->assertNotSame( $cleared['review_status'], $native->review_status );
		$this->assertNotSame( $cleared['publish_status'], $native->publish_status );
	}

	/**
	 * AC12 — segment_hash matches native field/segment keys after adopt.
	 */
	public function test_segment_hash_matches_native_keys(): void {
		$this->seed_hosted(
			'p:woocommerce:product_cat:7:description',
			array(
				'field_key'       => Contract::FIELD_KEY,
				'translated_text' => 'Beskrivning',
				'text_format'     => Store::FORMAT_HTML,
			)
		);

		$native = $this->adoption->adopt_logical_field( self::TERM_ID, 'product_cat', self::LANGUAGE_ID, 'description' );

		$this->assertIsObject( $native );
		$this->assertSame( 'description', $native->field_key );
		$this->assertSame( 'description', $native->segment_key );
		$this->assertSame(
			Store::segment_hash( 'description', 'description' ),
			$native->segment_hash
		);
	}

	/**
	 * AC33 / AC34 — resolver native-first, hosted fallback.
	 */
	public function test_resolver_native_first_then_hosted_fallback(): void {
		$this->seed_segments(
			Store::SOURCE_POST,
			self::SHOP_ID,
			array(
				'p:woocommerce:product_cat:7:name' => $this->cache_row( 'Hosted' ),
			)
		);

		$hosted = $this->resolver->resolve( self::TERM_ID, 'product_cat', 'name', self::LANGUAGE_ID );
		$this->assertNotNull( $hosted );
		$this->assertSame( TermTranslationResolver::IDENTITY_COMPATIBILITY, $hosted['identity'] );

		$this->seed_segments(
			Store::SOURCE_TERM,
			self::TERM_ID,
			array(
				'name' => $this->cache_row( 'Native' ),
			)
		);

		$native = $this->resolver->resolve( self::TERM_ID, 'product_cat', 'name', self::LANGUAGE_ID );
		$this->assertNotNull( $native );
		$this->assertSame( TermTranslationResolver::IDENTITY_NATIVE, $native['identity'] );
		$this->assertSame( 'Native', $native['row']->translated_text );
	}

	/**
	 * AC50 pattern — Rank Math keys retained on native identity via TermCompatRef.
	 */
	public function test_rank_math_key_retention_on_compat_ref_and_adopt(): void {
		$key = $this->resolver->rank_math_segment_key( self::TERM_ID, 'title' );
		$this->assertSame( 'p:rankmath:term:7:title', $key );

		$ref = $this->resolver->compat_ref( self::TERM_ID, 'category', $key, self::LANGUAGE_ID );
		$this->assertNotNull( $ref );
		$this->assertSame( $key, $ref->native_segment_key );
		$this->assertSame( $key, $ref->hosted_segment_key );
		$this->assertSame( Contract::FIELD_KEY, $ref->native_field_key );
		$this->assertSame( self::POSTS_ID, $ref->hosted_source_id );

		$this->seed_hosted(
			$key,
			array(
				'source_id'       => self::POSTS_ID,
				'field_key'       => Contract::FIELD_KEY,
				'translated_text' => 'SEO Title',
			),
			'category'
		);

		$native = $this->adoption->adopt_logical_field( self::TERM_ID, 'category', self::LANGUAGE_ID, $key );

		$this->assertIsObject( $native );
		$this->assertSame( Store::SOURCE_TERM, $native->source_type );
		$this->assertSame( $key, $native->segment_key );
		$this->assertSame( Contract::FIELD_KEY, $native->field_key );
		$this->assertSame(
			Store::segment_hash( Contract::FIELD_KEY, $key ),
			$native->segment_hash
		);
	}

	/**
	 * AC4 — AdmittedTaxonomies rejects forbidden taxonomy on the adoption service.
	 */
	public function test_adoption_rejects_forbidden_taxonomy(): void {
		$result = $this->adoption->adopt_logical_field( self::TERM_ID, 'nav_menu', self::LANGUAGE_ID, 'name' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'aiml_term_not_admitted', $result->get_error_code() );
	}

	/**
	 * AC32 unit analogue — WP_Error from with_term_compat_authority rolls back writes.
	 */
	public function test_authority_callback_wp_error_rolls_back(): void {
		$hosted_id = $this->seed_hosted(
			'p:woocommerce:product_cat:7:name',
			array(
				'translated_text' => 'KeepMe',
			)
		);

		$ref = $this->resolver->compat_ref( self::TERM_ID, 'product_cat', 'name', self::LANGUAGE_ID );
		$this->assertNotNull( $ref );

		$result = $this->store->with_term_compat_authority(
			$ref->to_store_ref(),
			static function () {
				return new WP_Error( 'aiml_test_force_fail', 'forced' );
			}
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$hosted = $this->wpdb->row( $hosted_id );
		$this->assertSame( Store::STATUS_MANUALLY_EDITED, $hosted->status );
		$this->assertSame( 'KeepMe', $hosted->translated_text );
	}

	/**
	 * Seeds a hosted compatibility row into the in-memory table.
	 *
	 * @param string               $segment_key Hosted segment key.
	 * @param array<string, mixed> $overrides   Column overrides.
	 * @param string               $taxonomy    Taxonomy for source text defaults.
	 */
	private function seed_hosted( string $segment_key, array $overrides = array(), string $taxonomy = 'product_cat' ): int {
		$source_id = (int) ( $overrides['source_id'] ?? ( 'category' === $taxonomy || 'post_tag' === $taxonomy ? self::POSTS_ID : self::SHOP_ID ) );
		$field_key = (string) ( $overrides['field_key'] ?? Contract::FIELD_KEY );
		$text      = (string) ( $overrides['translated_text'] ?? 'Hosted' );
		$hash      = (string) ( $overrides['translation_hash'] ?? Store::translation_hash( $text ) );

		return $this->wpdb->seed_row(
			array_merge(
				array(
					'source_type'                => Store::SOURCE_POST,
					'source_id'                  => $source_id,
					'source_subtype'             => 'page',
					'language_id'                => self::LANGUAGE_ID,
					'field_key'                  => $field_key,
					'segment_key'                => $segment_key,
					'segment_hash'               => Store::segment_hash( $field_key, $segment_key ),
					'segment_kind'               => Store::KIND_FIELD,
					'segment_order'              => 0,
					'text_format'                => Store::FORMAT_PLAIN,
					'source_text'                => 'Shoes',
					'source_hash'                => Store::source_hash( 'Shoes', Store::FORMAT_PLAIN ),
					'norm_version'               => Store::NORM_VERSION,
					'translated_text'            => $text,
					'translation_hash'           => $hash,
					'status'                     => Store::STATUS_MANUALLY_EDITED,
					'is_stale'                   => 0,
					'provider'                   => '',
					'model'                      => '',
					'prompt_profile'             => '',
					'prompt_version'             => '',
					'glossary_version'           => 0,
					'tm_id'                      => null,
					'translated_by'              => 1,
					'review_status'              => Store::REVIEW_NOT_SUBMITTED,
					'review_submitted_by'        => null,
					'review_submitted_at'        => null,
					'submitted_translation_hash' => '',
					'reviewed_by'                => null,
					'reviewed_at'                => null,
					'rejection_reason'           => '',
					'rejected_by'                => null,
					'rejected_at'                => null,
					'publish_status'             => Store::PUBLISH_UNPUBLISHED,
					'published_at'               => null,
					'published_by'               => null,
					'error_code'                 => '',
					'error_message'              => '',
					'created_at'                 => '2026-01-01 00:00:00',
					'updated_at'                 => '2026-01-01 00:00:00',
				),
				$overrides,
				array(
					'segment_key'  => $segment_key,
					'segment_hash' => Store::segment_hash(
						(string) ( $overrides['field_key'] ?? $field_key ),
						$segment_key
					),
				)
			)
		);
	}

	/**
	 * Seeds a native term row.
	 *
	 * @param string               $segment_key Native segment key.
	 * @param array<string, mixed> $overrides   Column overrides.
	 */
	private function seed_native( string $segment_key, array $overrides = array() ): int {
		$field_key = (string) ( $overrides['field_key'] ?? $segment_key );
		$text      = (string) ( $overrides['translated_text'] ?? 'Native' );

		return $this->wpdb->seed_row(
			array_merge(
				array(
					'source_type'                => Store::SOURCE_TERM,
					'source_id'                  => self::TERM_ID,
					'source_subtype'             => 'product_cat',
					'language_id'                => self::LANGUAGE_ID,
					'field_key'                  => $field_key,
					'segment_key'                => $segment_key,
					'segment_hash'               => Store::segment_hash( $field_key, $segment_key ),
					'segment_kind'               => Store::KIND_FIELD,
					'segment_order'              => 0,
					'text_format'                => Store::FORMAT_PLAIN,
					'source_text'                => 'Shoes',
					'source_hash'                => Store::source_hash( 'Shoes', Store::FORMAT_PLAIN ),
					'norm_version'               => Store::NORM_VERSION,
					'translated_text'            => $text,
					'translation_hash'           => Store::translation_hash( $text ),
					'status'                     => Store::STATUS_MANUALLY_EDITED,
					'is_stale'                   => 0,
					'provider'                   => '',
					'model'                      => '',
					'prompt_profile'             => '',
					'prompt_version'             => '',
					'glossary_version'           => 0,
					'tm_id'                      => null,
					'translated_by'              => 1,
					'review_status'              => Store::REVIEW_NOT_SUBMITTED,
					'review_submitted_by'        => null,
					'review_submitted_at'        => null,
					'submitted_translation_hash' => '',
					'reviewed_by'                => null,
					'reviewed_at'                => null,
					'rejection_reason'           => '',
					'rejected_by'                => null,
					'rejected_at'                => null,
					'publish_status'             => Store::PUBLISH_UNPUBLISHED,
					'published_at'               => null,
					'published_by'               => null,
					'error_code'                 => '',
					'error_message'              => '',
					'created_at'                 => '2026-01-01 00:00:00',
					'updated_at'                 => '2026-01-01 00:00:00',
				),
				$overrides
			)
		);
	}

	/**
	 * Seeds the Store segment cache (resolver read path).
	 *
	 * @param string                $source_type Source type.
	 * @param int                   $source_id   Source id.
	 * @param array<string, object> $segments    Rows keyed by segment key.
	 */
	private function seed_segments( string $source_type, int $source_id, array $segments ): void {
		$this->cache->set( sprintf( 'seg:%s:%d', $source_type, $source_id ), self::LANGUAGE_ID, $segments );
	}

	/**
	 * Minimal cache row.
	 *
	 * @param string $translated_text Translated text.
	 */
	private function cache_row( string $translated_text ): object {
		return (object) array(
			'translation_id'  => 1,
			'language_id'     => self::LANGUAGE_ID,
			'translated_text' => $translated_text,
			'status'          => Store::STATUS_MANUALLY_EDITED,
		);
	}
}
