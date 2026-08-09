<?php
/**
 * A.SEOd OpenGraph / Twitter Rank Math overlays.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Language\Languages;
use AIMultilingual\Seo\LanguageRelationshipService;
use AIMultilingual\Translation\Store;
use ReflectionMethod;

/**
 * Supported SD1–SD3/SD5–SD8/SD11 characterization.
 */
final class AseodOpenGraphTest extends AimlTestCase {

	public function test_extract_literal_facebook_and_twitter_fields(): void {
		$post = $this->create_page( 'Social Fixture', 'Body' );
		update_post_meta( (int) $post->ID, RankMathIntegration::META_FACEBOOK_TITLE, 'FB Title' );
		update_post_meta( (int) $post->ID, RankMathIntegration::META_FACEBOOK_DESCRIPTION, 'FB Desc' );
		update_post_meta( (int) $post->ID, RankMathIntegration::META_TWITTER_TITLE, 'TW Title' );

		$integration = $this->make_compatible_integration();
		$units       = $integration->extract_for_post( $post );
		$by_field    = array();
		foreach ( $units as $unit ) {
			$by_field[ $unit->field ] = $unit;
		}

		$this->assertArrayHasKey( 'facebook_title', $by_field );
		$this->assertArrayHasKey( 'facebook_description', $by_field );
		$this->assertArrayHasKey( 'twitter_title', $by_field );
		$this->assertSame( 'p:rankmath:post:' . (int) $post->ID . ':facebook_title', $by_field['facebook_title']->segment_key );
	}

	public function test_og_title_reuses_seo_identity_when_facebook_empty(): void {
		$sv   = $this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$post = $this->create_page( 'OG Page', 'Body' );
		update_post_meta( (int) $post->ID, RankMathIntegration::META_TITLE, 'EN SEO Title' );
		delete_post_meta( (int) $post->ID, RankMathIntegration::META_FACEBOOK_TITLE );

		$key = 'p:rankmath:post:' . (int) $post->ID . ':title';
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'language_id'     => (int) $sv->language_id,
				'field_key'       => \AIMultilingual\Integration\Contract::FIELD_KEY,
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

		$this->assertSame( 'SV SEO Title', apply_filters( RankMathIntegration::HOOK_OG_TITLE, 'EN SEO Title' ) );
		$this->assertSame( 'SV SEO Title', apply_filters( RankMathIntegration::HOOK_TWITTER_TITLE, 'EN SEO Title' ) );
	}

	public function test_og_url_reinforced_from_sb11_current(): void {
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$post = $this->create_page( 'URL Page', 'Body' );
		$this->route( '/sv/' . $post->post_name . '/' );

		$integration = $this->make_compatible_integration();
		$integration->register_output_hooks(
			static function (): ?string {
				return null;
			}
		);

		$url = apply_filters( RankMathIntegration::HOOK_OG_URL, 'https://example.test/wrong/' );
		$this->assertStringContainsString( '/sv/', (string) $url );
		$this->assertStringContainsString( $post->post_name, (string) $url );
	}

	public function test_locale_alternates_exclude_current_and_preview(): void {
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$this->add_language( 'de', 'de_DE', Languages::STATUS_PREVIEW );
		$post = $this->create_page( 'Alt Page', 'Body' );
		$this->route( '/' . $post->post_name . '/' );

		$integration = $this->make_compatible_integration();
		$og          = new class() {
			/** @var list<array{0:string,1:string}> */
			public array $tags = array();
			public function tag( string $property, string $content ): bool {
				$this->tags[] = array( $property, $content );
				return true;
			}
		};

		$method = new ReflectionMethod( RankMathIntegration::class, 'emit_locale_alternates' );
		$method->setAccessible( true );
		$method->invoke( $integration, $og );

		$locales = array_map(
			static fn( array $row ): string => $row[1],
			$og->tags
		);
		$this->assertContains( 'sv_SE', $locales );
		$this->assertNotContains( 'de_DE', $locales );
		$this->assertNotContains( 'en_US', $locales ); // Current default language is skipped.
	}

	public function test_public_social_hooks_register_on_default_language(): void {
		$this->add_language( 'sv', 'sv_SE', Languages::STATUS_PUBLISHED );
		$post = $this->create_page( 'Default Lang Social', 'Body' );
		$this->route( '/' . $post->post_name . '/' );
		$this->assertTrue( $this->context->is_default() );

		$integration = $this->make_compatible_integration();
		$integration->register_public_social_hooks();

		$this->assertNotFalse( has_filter( RankMathIntegration::HOOK_OG_URL ) );
		$this->assertNotFalse( has_filter( RankMathIntegration::HOOK_OG_LOCALE ) );
		$this->assertNotFalse( has_action( RankMathIntegration::HOOK_OG_FACEBOOK ) );

		$og = new class() {
			/** @var list<array{0:string,1:string}> */
			public array $tags = array();
			public function tag( string $property, string $content ): bool {
				$this->tags[] = array( $property, $content );
				return true;
			}
		};
		do_action( RankMathIntegration::HOOK_OG_FACEBOOK, $og );
		$locales = array_map( static fn( array $row ): string => $row[1], $og->tags );
		$this->assertContains( 'sv_SE', $locales );
	}

	public function test_inactive_rank_math_registers_no_og_hooks(): void {
		$integration = new RankMathIntegration(
			new PluginIdentity(),
			$this->store,
			$this->context,
			new LanguageRelationshipService( $this->languages, $this->context ),
			true,
			false,
			'1.0.275',
			false,
			true
		);
		$integration->register_output_hooks(
			static function (): ?string {
				return 'x';
			}
		);
		$this->assertFalse( (bool) has_filter( RankMathIntegration::HOOK_OG_TITLE ) );
	}

	private function make_compatible_integration(): RankMathIntegration {
		return new RankMathIntegration(
			new PluginIdentity(),
			$this->store,
			$this->context,
			new LanguageRelationshipService( $this->languages, $this->context ),
			true,
			true,
			'1.0.275',
			false,
			true
		);
	}
}
