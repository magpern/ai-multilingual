<?php
/**
 * A.SEOc Rank Math integration acceptance + deferred guards.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Database\Migrator;
use AIMultilingual\Database\Schema;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\IntegrationDiagnostics;
use AIMultilingual\Integration\IntegrationRegistry;
use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Language\Languages;
use AIMultilingual\Seo\LanguageRelationshipService;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use ReflectionClass;

/**
 * Supported SC1–SC6/SC10–SC14 + Partial SC7–SC9 characterization.
 */
final class AseocRankMathIntegrationTest extends AimlTestCase {

	public function test_extract_literal_post_seo_fields_only(): void {
		$post = $this->create_page( 'SEO Fixture', 'Body' );
		update_post_meta( (int) $post->ID, RankMathIntegration::META_TITLE, 'Explicit SEO Title' );
		update_post_meta( (int) $post->ID, RankMathIntegration::META_DESCRIPTION, 'Explicit SEO Description' );

		$integration = $this->make_compatible_integration();
		$units       = $integration->extract_for_post( $post );
		$by_field    = array();
		foreach ( $units as $unit ) {
			$by_field[ $unit->field ] = $unit;
		}

		$this->assertArrayHasKey( 'title', $by_field );
		$this->assertArrayHasKey( 'description', $by_field );
		$this->assertSame( 'Explicit SEO Title', $by_field['title']->source_text );
		$this->assertSame( 'p:rankmath:post:' . (int) $post->ID . ':title', $by_field['title']->segment_key );
		$this->assertSame( Contract::OWNERSHIP_RECORD, $by_field['title']->ownership_class );
	}

	public function test_template_only_post_emits_no_rankmath_identity(): void {
		$post = $this->create_page( 'Template Only', 'Body' );
		delete_post_meta( (int) $post->ID, RankMathIntegration::META_TITLE );
		delete_post_meta( (int) $post->ID, RankMathIntegration::META_DESCRIPTION );

		$integration = $this->make_compatible_integration();
		$this->assertSame( array(), $integration->extract_for_post( $post ) );
	}

	public function test_token_bearing_custom_title_deferred_from_identity(): void {
		$post = $this->create_page( 'Token Title Host', 'Body' );
		update_post_meta( (int) $post->ID, RankMathIntegration::META_TITLE, '%title% | Biopentra' );

		$integration = $this->make_compatible_integration();
		$this->assertSame( array(), $integration->extract_for_post( $post ) );
	}

	public function test_frontend_title_overlay_and_native_fallback(): void {
		$sv   = $this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$post = $this->create_page( 'Canonical Page', 'Body' );
		update_post_meta( (int) $post->ID, RankMathIntegration::META_TITLE, 'EN SEO Title' );

		$key = 'p:rankmath:post:' . (int) $post->ID . ':title';
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'language_id'     => (int) $sv->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $key,
				'source_text'     => 'EN SEO Title',
				'translated_text' => 'SV SEO Title',
				'text_format'     => Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$this->route( '/sv/' . $post->post_name . '/' );
		$GLOBALS['wp_query']->queried_object    = $post;
		$GLOBALS['wp_query']->queried_object_id = (int) $post->ID;
		$GLOBALS['wp_the_query']                = $GLOBALS['wp_query'];

		$integration = $this->make_compatible_integration();
		$language_id = (int) $sv->language_id;
		$source_id   = (int) $post->ID;
		$integration->register_output_hooks(
			function ( string $segment_key ) use ( $source_id, $language_id ): ?string {
				$row = $this->store->get( Store::SOURCE_POST, $source_id, $language_id, $segment_key );
				return null === $row ? null : (string) ( $row->translated_text ?? '' );
			}
		);

		$overlaid = apply_filters( RankMathIntegration::HOOK_TITLE, 'EN SEO Title' );
		$this->assertSame( 'SV SEO Title', $overlaid );

		// Empty Store hit → native Rank Math string.
		$missing = apply_filters(
			RankMathIntegration::HOOK_DESCRIPTION,
			'Native description'
		);
		$this->assertSame( 'Native description', $missing );
	}

	public function test_replacements_inherit_translated_post_title_token(): void {
		$sv   = $this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$post = $this->create_page( 'EN Post Title', 'Body' );
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'language_id'     => (int) $sv->language_id,
				'field_key'       => Extractor::FIELD_TITLE,
				'segment_key'     => Extractor::FIELD_TITLE,
				'source_text'     => 'EN Post Title',
				'translated_text' => 'SV Post Title',
				'text_format'     => Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$this->route( '/sv/' . $post->post_name . '/' );
		$integration = $this->make_compatible_integration();
		$integration->register_output_hooks(
			static function (): ?string {
				return null;
			}
		);

		$args = (object) array(
			'ID'         => (int) $post->ID,
			'post_title' => 'EN Post Title',
		);
		$out  = apply_filters(
			RankMathIntegration::HOOK_REPLACEMENTS,
			array(
				'%title%'    => 'EN Post Title',
				'%sitename%' => 'BiopentraDev',
				'%sep%'      => '-',
			),
			$args
		);

		$this->assertSame( 'SV Post Title', $out['%title%'] );
		$this->assertSame( 'BiopentraDev', $out['%sitename%'] );
	}

	public function test_schema_entity_overlays_name_not_price(): void {
		$sv   = $this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$post = $this->create_page( 'Schema Host', 'Body' );
		update_post_meta( (int) $post->ID, RankMathIntegration::META_TITLE, 'EN Schema Name' );
		update_post_meta( (int) $post->ID, RankMathIntegration::META_DESCRIPTION, 'EN Schema Desc' );

		$key_title = 'p:rankmath:post:' . (int) $post->ID . ':title';
		$key_desc  = 'p:rankmath:post:' . (int) $post->ID . ':description';
		foreach (
			array(
				$key_title => array( 'EN Schema Name', 'SV Schema Name' ),
				$key_desc  => array( 'EN Schema Desc', 'SV Schema Desc' ),
			) as $key => $pair
		) {
			$this->store->save_translation(
				array(
					'source_type'     => Store::SOURCE_POST,
					'source_id'       => (int) $post->ID,
					'language_id'     => (int) $sv->language_id,
					'field_key'       => Contract::FIELD_KEY,
					'segment_key'     => $key,
					'source_text'     => $pair[0],
					'translated_text' => $pair[1],
					'text_format'     => Store::FORMAT_PLAIN,
					'status'          => Store::STATUS_MANUALLY_EDITED,
				)
			);
		}

		$this->route( '/sv/' . $post->post_name . '/' );
		$GLOBALS['wp_query']->queried_object    = $post;
		$GLOBALS['wp_query']->queried_object_id = (int) $post->ID;

		$integration = $this->make_compatible_integration();
		$language_id = (int) $sv->language_id;
		$source_id   = (int) $post->ID;
		$integration->register_output_hooks(
			function ( string $segment_key ) use ( $source_id, $language_id ): ?string {
				$row  = $this->store->get( Store::SOURCE_POST, $source_id, $language_id, $segment_key );
				$text = null === $row ? '' : (string) ( $row->translated_text ?? '' );
				return '' === $text ? null : $text;
			}
		);

		$entity = apply_filters(
			RankMathIntegration::HOOK_SCHEMA_ENTITY,
			array(
				'@type'       => 'Product',
				'name'        => 'EN Schema Name',
				'description' => 'EN Schema Desc',
				'sku'         => 'SKU-1',
				'offers'      => array(
					'@type' => 'Offer',
					'price' => '99.00',
				),
			)
		);

		$this->assertSame( 'SV Schema Name', $entity['name'] );
		$this->assertSame( 'SV Schema Desc', $entity['description'] );
		$this->assertSame( 'SKU-1', $entity['sku'] );
		$this->assertSame( '99.00', $entity['offers']['price'] );
	}

	public function test_sb11_injected_unchanged(): void {
		$svc         = $this->make_relationships();
		$integration = new RankMathIntegration(
			new PluginIdentity(),
			$this->store,
			$this->context,
			$svc,
			true,
			true,
			'1.0.275',
			false,
			true
		);
		$this->assertSame( $svc, $integration->relationships() );

		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$this->add_language( 'de', 'de_DE', Languages::STATUS_PREVIEW );
		$this->route( '/sv/about/' );
		$codes = array_map(
			static function ( $r ) {
				return $r->language_code;
			},
			$svc->for_public_request()
		);
		$this->assertContains( 'sv', $codes );
		$this->assertNotContains( 'de', $codes );
	}

	public function test_no_rank_math_meta_writes_on_overlay(): void {
		$post = $this->create_page( 'No Mutate', 'Body' );
		update_post_meta( (int) $post->ID, RankMathIntegration::META_TITLE, 'Keep Me' );
		$before = get_post_meta( (int) $post->ID, RankMathIntegration::META_TITLE, true );

		$integration = $this->make_compatible_integration();
		$integration->register_output_hooks(
			static function (): ?string {
				return 'Overlay';
			}
		);
		apply_filters( RankMathIntegration::HOOK_TITLE, 'Keep Me' );

		$this->assertSame( $before, get_post_meta( (int) $post->ID, RankMathIntegration::META_TITLE, true ) );
	}

	public function test_extractor_registry_includes_rankmath_units(): void {
		$post = $this->create_page( 'Registry Page', 'Body' );
		update_post_meta( (int) $post->ID, RankMathIntegration::META_TITLE, 'Reg Title' );

		$diag     = new IntegrationDiagnostics();
		$identity = new PluginIdentity( $diag );
		$ff       = $this->make_compatible_integration( $identity );
		$registry = new IntegrationRegistry( $diag );
		$registry->register( $ff );

		$extractor = new Extractor( null, null, null, $registry );
		$segments  = $extractor->extract( $post );
		$key       = 'p:rankmath:post:' . (int) $post->ID . ':title';
		$this->assertArrayHasKey( $key, $segments );
	}

	private function make_compatible_integration( ?PluginIdentity $identity = null ): RankMathIntegration {
		return new RankMathIntegration(
			$identity ?? new PluginIdentity(),
			$this->store,
			$this->context,
			$this->make_relationships(),
			true,
			true,
			'1.0.275',
			false,
			true
		);
	}
}
