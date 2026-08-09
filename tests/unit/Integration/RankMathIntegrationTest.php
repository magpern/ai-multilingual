<?php
/**
 * Unit tests for Rank Math A.SEOc integration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * @covers \AIMultilingual\Integration\RankMath\RankMathIntegration
 */
final class RankMathIntegrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		remove_all_filters( RankMathIntegration::HOOK_TITLE );
		remove_all_filters( RankMathIntegration::HOOK_DESCRIPTION );
		remove_all_filters( RankMathIntegration::HOOK_REPLACEMENTS );
		remove_all_filters( RankMathIntegration::HOOK_SCHEMA_ENTITY );
	}

	public function test_identity_keys_match_plan_shape(): void {
		$identity = new PluginIdentity();
		$this->assertSame(
			'p:rankmath:post:3594:title',
			$identity->build( 'rankmath', 'post', '3594', 'title' )
		);
		$this->assertSame(
			'p:rankmath:post:3594:description',
			$identity->build( 'rankmath', 'post', '3594', 'description' )
		);
		$this->assertSame(
			'p:rankmath:term:36:title',
			$identity->build( 'rankmath', 'term', '36', 'title' )
		);
		foreach (
			array(
				'p:rankmath:post:3594:title',
				'p:rankmath:post:3594:description',
				'p:rankmath:term:36:title',
			) as $key
		) {
			$this->assertLessThanOrEqual( Contract::MAX_SEGMENT_KEY_LENGTH, strlen( $key ) );
			$this->assertSame( 'p', explode( ':', $key )[0] );
		}
	}

	public function test_literal_seo_field_rejects_tokens(): void {
		$this->assertTrue( RankMathIntegration::is_literal_seo_field( 'BPC-157 Research Peptide | Biopentra' ) );
		$this->assertFalse( RankMathIntegration::is_literal_seo_field( '' ) );
		$this->assertFalse( RankMathIntegration::is_literal_seo_field( '%title% %sep% %sitename%' ) );
		$this->assertFalse( RankMathIntegration::is_literal_seo_field( 'Hello %excerpt%' ) );
	}

	public function test_compatibility_matrix(): void {
		$integration = $this->make_integration();
		$integration->configure( true, true, '1.0.275', false, true );
		$this->assertSame( Contract::STATE_COMPATIBLE, $integration->get_compatibility()->state() );

		$integration->configure( false, null, null, null, null );
		$this->assertSame( Contract::STATE_UNAVAILABLE, $integration->get_compatibility()->state() );

		$integration->configure( true, false, null, null, null );
		$this->assertSame( Contract::STATE_UNAVAILABLE, $integration->get_compatibility()->state() );

		$integration->configure( null, true, '1.0.100', null, null );
		$this->assertSame( Contract::STATE_UNSUPPORTED_VERSION, $integration->get_compatibility()->state() );

		$integration->configure( null, null, '1.0.275', true, null );
		$this->assertSame( Contract::STATE_DISABLED, $integration->get_compatibility()->state() );

		$integration->configure( null, null, null, false, false );
		$this->assertSame( Contract::STATE_MISSING_REQUIRED_HOOK, $integration->get_compatibility()->state() );
	}

	public function test_inactive_rank_math_skips_hooks_and_extract(): void {
		$integration = $this->make_integration();
		$integration->configure( true, false, '1.0.275', false, true );

		$post = $this->fake_post( 10 );
		$this->assertSame( array(), $integration->extract_for_post( $post ) );
		$this->assertFalse( $integration->get_compatibility()->allows_overlay() );

		$integration->register_output_hooks(
			static function (): ?string {
				return 'nope';
			}
		);
		// Inactive → no overlay registration; native string preserved when filter absent.
		$this->assertSame( 'Native', apply_filters( RankMathIntegration::HOOK_TITLE, 'Native' ) );
	}

	public function test_title_overlay_uses_resolve_and_falls_back(): void {
		$integration = $this->make_integration();
		$integration->configure( true, true, '1.0.275', false, true );

		$map = array(
			'p:rankmath:post:42:title' => 'SV SEO Title',
		);
		$integration->register_output_hooks(
			static function ( string $key ) use ( $map ): ?string {
				return $map[ $key ] ?? null;
			}
		);

		// Without a queried post with literal meta, overlay is a no-op (native fallback).
		$out = apply_filters( RankMathIntegration::HOOK_TITLE, 'Native Title' );
		$this->assertSame( 'Native Title', $out );
	}

	public function test_api_version_is_v1(): void {
		$integration = $this->make_integration();
		$this->assertSame( Contract::API_VERSION, $integration->get_api_version() );
		$this->assertSame( RankMathIntegration::ID, $integration->get_id() );
	}

	private function make_integration(): RankMathIntegration {
		return new RankMathIntegration(
			new PluginIdentity(),
			new Store( new Cache() ),
			new LanguageContext(),
			null,
			true,
			true,
			'1.0.275',
			false,
			true
		);
	}

	private function fake_post( int $id ): WP_Post {
		$post = new WP_Post(
			(object) array(
				'ID'           => $id,
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Title',
				'post_content' => '',
			)
		);
		return $post;
	}
}
