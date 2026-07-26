<?php
/**
 * Language resolution priority.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit;

use AIMultilingual\Language\LanguageResolver;
use PHPUnit\Framework\TestCase;

/**
 * Routing rules, pinned without a WordPress bootstrap.
 *
 * Milestone 1 priority is exactly two entries: URL prefix, then the default
 * language. No cookie, no Accept-Language — so these cases are the whole
 * contract.
 */
final class LanguageResolverTest extends TestCase {

	private LanguageResolver $resolver;

	protected function setUp(): void {
		parent::setUp();

		$this->resolver = new LanguageResolver();
	}

	/**
	 * Builds a language row of the shape the store returns.
	 *
	 * @param string $code       URL code.
	 * @param string $status     Language state.
	 * @param bool   $is_default Whether this is the source language.
	 */
	private function language( string $code, string $status = 'published', bool $is_default = false ): object {
		return (object) array(
			'language_id' => abs( crc32( $code ) ) % 1000,
			'code'        => $code,
			'locale'      => $code . '_XX',
			'name'        => strtoupper( $code ),
			'native_name' => '',
			'direction'   => 'ltr',
			'is_default'  => $is_default,
			'status'      => $status,
			'sort_order'  => 0,
		);
	}

	/**
	 * @return object[]
	 */
	private function languages(): array {
		return array(
			$this->language( 'en', 'published', true ),
			$this->language( 'sv', 'published' ),
			$this->language( 'de', 'preview' ),
			$this->language( 'fr', 'disabled' ),
		);
	}

	public function test_published_prefix_resolves_and_is_stripped(): void {
		$result = $this->resolver->resolve( '/sv/about/', $this->languages() );

		$this->assertSame( 'sv', $result['language']->code );
		$this->assertSame( '/about/', $result['path'] );
		$this->assertTrue( $result['prefixed'] );
	}

	public function test_unprefixed_path_is_the_default_language(): void {
		$result = $this->resolver->resolve( '/about/', $this->languages() );

		$this->assertSame( 'en', $result['language']->code );
		$this->assertSame( '/about/', $result['path'] );
		$this->assertFalse( $result['prefixed'] );
	}

	public function test_root_resolves_to_default(): void {
		$result = $this->resolver->resolve( '/', $this->languages() );

		$this->assertSame( 'en', $result['language']->code );
		$this->assertSame( '/', $result['path'] );
	}

	public function test_bare_language_prefix_resolves_to_its_root(): void {
		$result = $this->resolver->resolve( '/sv/', $this->languages() );

		$this->assertSame( 'sv', $result['language']->code );
		$this->assertSame( '/', $result['path'] );
		$this->assertTrue( $result['prefixed'] );
	}

	public function test_language_prefix_without_trailing_slash(): void {
		$result = $this->resolver->resolve( '/sv', $this->languages() );

		$this->assertSame( 'sv', $result['language']->code );
		$this->assertSame( '/', $result['path'] );
	}

	/**
	 * The default language owns the unprefixed root, so an explicit /en/ is not
	 * a route in Milestone 1 and must fall through for WordPress to handle.
	 */
	public function test_default_language_prefix_is_not_a_route(): void {
		$result = $this->resolver->resolve( '/en/about/', $this->languages() );

		$this->assertSame( 'en', $result['language']->code );
		$this->assertFalse( $result['prefixed'] );
		$this->assertSame( '/en/about/', $result['path'] );
	}

	public function test_preview_language_is_hidden_from_anonymous_visitors(): void {
		$result = $this->resolver->resolve( '/de/about/', $this->languages(), false );

		$this->assertSame( 'en', $result['language']->code );
		$this->assertFalse( $result['prefixed'] );
		$this->assertSame( '/de/about/', $result['path'] );
	}

	public function test_preview_language_resolves_for_a_capable_viewer(): void {
		$result = $this->resolver->resolve( '/de/about/', $this->languages(), true );

		$this->assertSame( 'de', $result['language']->code );
		$this->assertTrue( $result['prefixed'] );
		$this->assertSame( '/about/', $result['path'] );
	}

	public function test_disabled_language_never_resolves(): void {
		foreach ( array( false, true ) as $can_preview ) {
			$result = $this->resolver->resolve( '/fr/about/', $this->languages(), $can_preview );

			$this->assertSame( 'en', $result['language']->code );
			$this->assertFalse( $result['prefixed'] );
		}
	}

	public function test_unknown_prefix_falls_through_untouched(): void {
		$result = $this->resolver->resolve( '/xx/about/', $this->languages() );

		$this->assertSame( 'en', $result['language']->code );
		$this->assertFalse( $result['prefixed'] );
		$this->assertSame( '/xx/about/', $result['path'] );
	}

	/**
	 * A slug that merely begins with the language code must not be truncated.
	 */
	public function test_slug_starting_with_a_language_code_is_not_a_prefix(): void {
		$result = $this->resolver->resolve( '/svenska-sidan/', $this->languages() );

		$this->assertSame( 'en', $result['language']->code );
		$this->assertFalse( $result['prefixed'] );
		$this->assertSame( '/svenska-sidan/', $result['path'] );
	}

	public function test_nested_paths_keep_their_remainder(): void {
		$result = $this->resolver->resolve( '/sv/shop/tea/green/', $this->languages() );

		$this->assertSame( '/shop/tea/green/', $result['path'] );
	}

	public function test_query_string_and_fragment_are_ignored(): void {
		$result = $this->resolver->resolve( '/sv/about/?page=2', $this->languages() );

		$this->assertSame( 'sv', $result['language']->code );
		$this->assertSame( '/about/', $result['path'] );
	}

	public function test_missing_leading_slash_is_tolerated(): void {
		$result = $this->resolver->resolve( 'sv/about/', $this->languages() );

		$this->assertSame( 'sv', $result['language']->code );
		$this->assertSame( '/about/', $result['path'] );
	}

	public function test_empty_path_resolves_to_default(): void {
		$result = $this->resolver->resolve( '', $this->languages() );

		$this->assertSame( 'en', $result['language']->code );
		$this->assertSame( '/', $result['path'] );
	}

	public function test_no_configured_languages_yields_no_language(): void {
		$result = $this->resolver->resolve( '/about/', array() );

		$this->assertNull( $result['language'] );
		$this->assertFalse( $result['prefixed'] );
	}

	public function test_is_routable_matches_the_state_model(): void {
		$this->assertTrue( $this->resolver->is_routable( $this->language( 'sv', 'published' ) ) );
		$this->assertFalse( $this->resolver->is_routable( $this->language( 'de', 'preview' ) ) );
		$this->assertTrue( $this->resolver->is_routable( $this->language( 'de', 'preview' ), true ) );
		$this->assertFalse( $this->resolver->is_routable( $this->language( 'fr', 'disabled' ), true ) );
	}
}
