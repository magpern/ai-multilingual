<?php
/**
 * Rank Math sitemap language-discovery overlays (A.SEOe).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration\RankMath;

use AIMultilingual\Seo\LanguageRelationship;
use AIMultilingual\Seo\LanguageRelationshipService;

/**
 * Official Rank Math sitemap filter overlays only.
 *
 * Preserves Rank Math as the sole sitemap XML owner. Does not register
 * providers, scrape XML, or invent a reusable SitemapDiscovery contract (SE11).
 */
final class RankMathSitemapOverlay {

	public const HOOK_URL = 'rank_math/sitemap/url';

	public const HOOK_ENTRY = 'rank_math/sitemap/entry';

	public const HOOK_INCLUDE_NOINDEX = 'rank_math/sitemap/include_noindex';

	public const HOOK_PROVIDERS = 'rank_math/sitemap/providers';

	public const XHTML_NS = 'http://www.w3.org/1999/xhtml';

	/**
	 * Sitemap types that receive xmlns:xhtml when multilingual discovery is active.
	 *
	 * @var list<string>
	 */
	private const URLSET_TYPES = array(
		'page',
		'post',
		'product',
		'product_cat',
		'product_tag',
		'category',
		'post_tag',
		'author',
	);

	/**
	 * Whether hooks were registered.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Builds the sitemap overlay helper.
	 *
	 * @param LanguageRelationshipService $relationships SB11 (consumed unchanged).
	 */
	public function __construct(
		private LanguageRelationshipService $relationships
	) {
	}

	/**
	 * Register Rank Math sitemap filters. Idempotent. Safe when Rank Math is absent.
	 */
	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		foreach ( self::URLSET_TYPES as $type ) {
			add_filter(
				"rank_math/sitemap/{$type}_urlset",
				array( $this, 'filter_urlset_xmlns_xhtml' ),
				20
			);
		}

		add_filter( self::HOOK_URL, array( $this, 'filter_sitemap_url' ), 20, 2 );
		add_filter( self::HOOK_ENTRY, array( $this, 'filter_sitemap_entry' ), 20, 3 );
		// Honesty: never force noindex objects into the sitemap.
		add_filter( self::HOOK_INCLUDE_NOINDEX, array( $this, 'filter_include_noindex' ), 20, 2 );
	}

	/**
	 * Whether sitemap hooks are registered (tests).
	 */
	public function is_registered(): bool {
		return $this->registered;
	}

	/**
	 * Add xmlns:xhtml once when public multilingual discovery applies.
	 *
	 * @param mixed $urlset Rank Math urlset opening markup.
	 * @return mixed
	 */
	public function filter_urlset_xmlns_xhtml( $urlset ) {
		if ( ! is_string( $urlset ) || ! $this->should_emit_alternates() ) {
			return $urlset;
		}

		if ( false !== strpos( $urlset, 'xmlns:xhtml=' ) ) {
			return $urlset;
		}

		// Official urlset filter receives Rank Math's opening tag string.
		if ( false === strpos( $urlset, '<urlset ' ) ) {
			return $urlset;
		}

		return str_replace(
			'<urlset ',
			'<urlset xmlns:xhtml="' . self::XHTML_NS . '" ',
			$urlset
		);
	}

	/**
	 * Inject SB11 xhtml:link alternates into an owned Rank Math `<url>` fragment.
	 *
	 * @param mixed $output Rank Math url tag XML.
	 * @param mixed $url    Sitemap url parts (must include loc).
	 * @return mixed
	 */
	public function filter_sitemap_url( $output, $url = null ) {
		if ( ! is_string( $output ) || ! is_array( $url ) ) {
			return $output;
		}

		if ( ! $this->should_emit_alternates() ) {
			return $output;
		}

		$loc = isset( $url['loc'] ) ? (string) $url['loc'] : '';
		if ( '' === $loc ) {
			return $output;
		}

		$path = $this->unprefixed_path_from_loc( $loc );
		if ( null === $path ) {
			return $output;
		}

		$relationships = $this->relationships->for_path( $path, false, true );
		if ( count( $relationships ) < 2 ) {
			return $output;
		}

		$fragment = $this->build_xhtml_link_fragment( $relationships );
		if ( '' === $fragment ) {
			return $output;
		}

		if ( false !== strpos( $output, 'xhtml:link' ) ) {
			return $output;
		}

		$pos = strrpos( $output, '</url>' );
		if ( false === $pos ) {
			return $output;
		}

		return substr( $output, 0, $pos ) . $fragment . substr( $output, $pos );
	}

	/**
	 * Preserve Rank Math entry ownership — never invent inclusion.
	 *
	 * @param mixed $url          Entry parts or empty.
	 * @param mixed $type         Entry type.
	 * @param mixed $entry_object Source object from Rank Math.
	 * @return mixed
	 */
	public function filter_sitemap_entry( $url, $type = null, $entry_object = null ) {
		unset( $type, $entry_object );

		if ( ! (bool) get_option( 'blog_public' ) ) {
			// Honesty: do not enrich discovery when the site discourages indexing.
			return $url;
		}

		return $url;
	}

	/**
	 * Never force Rank Math to include noindex objects.
	 *
	 * @param mixed $include_noindex Current include_noindex value.
	 * @param mixed $type            Sitemap type.
	 * @return mixed
	 */
	public function filter_include_noindex( $include_noindex, $type = null ) {
		unset( $type );

		if ( true === $include_noindex || 1 === $include_noindex || '1' === $include_noindex ) {
			return $include_noindex;
		}

		return false;
	}

	/**
	 * Whether public multilingual alternates should be emitted.
	 */
	private function should_emit_alternates(): bool {
		if ( ! (bool) get_option( 'blog_public' ) ) {
			return false;
		}

		return count( $this->relationships->for_path( '/', false ) ) >= 2;
	}

	/**
	 * Build xhtml:link markup for public relationships (+ x-default).
	 *
	 * @param array<int, LanguageRelationship> $relationships Public SB11 set.
	 */
	private function build_xhtml_link_fragment( array $relationships ): string {
		$seen_hreflang = array();
		$seen_url      = array();
		$lines         = array();
		$default_url   = null;

		foreach ( $relationships as $rel ) {
			if ( ! $rel instanceof LanguageRelationship ) {
				continue;
			}

			$hreflang = strtolower( trim( $rel->hreflang ) );
			$url      = esc_url( $rel->url );
			if ( '' === $hreflang || '' === $url ) {
				continue;
			}
			if ( isset( $seen_hreflang[ $hreflang ] ) || isset( $seen_url[ $url ] ) ) {
				continue;
			}
			$seen_hreflang[ $hreflang ] = true;
			$seen_url[ $url ]           = true;

			$lines[] = sprintf(
				"\t\t<xhtml:link rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n",
				esc_attr( $rel->hreflang ),
				$url
			);

			if ( $rel->is_default ) {
				$default_url = $url;
			}
		}

		if ( null !== $default_url && ! isset( $seen_hreflang['x-default'] ) ) {
			$lines[] = sprintf(
				"\t\t<xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"%s\" />\n",
				$default_url
			);
		}

		return implode( '', $lines );
	}

	/**
	 * Derive unprefixed site path from a Rank Math sitemap loc URL.
	 *
	 * @param string $loc Absolute local URL.
	 */
	private function unprefixed_path_from_loc( string $loc ): ?string {
		$path = wp_parse_url( $loc, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return null;
		}

		$home = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
		$home = trim( $home, '/' );
		if ( '' !== $home ) {
			$normalized = '/' . ltrim( $path, '/' );
			$prefix     = '/' . $home;
			if ( 0 === strpos( $normalized, $prefix ) ) {
				$path = substr( $normalized, strlen( $prefix ) );
			}
		}

		$path = '/' . ltrim( (string) $path, '/' );

		return '' === $path ? '/' : $path;
	}
}
