<?php
/**
 * Document canonical + hreflang emission (A.SEOb).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Seo;

/**
 * Overlays language-aware canonical URLs and emits reciprocal hreflang.
 *
 * Rank Math remains the canonical tag owner when active; AIML filters its
 * value. Hreflang document tags are owned by AIML (SB3).
 */
final class DocumentSeoHead {

	/**
	 * SB11 relationship service.
	 *
	 * @var LanguageRelationshipService
	 */
	private LanguageRelationshipService $relationships;

	/**
	 * Builds the document SEO head emitter.
	 *
	 * @param LanguageRelationshipService $relationships SB11 contract.
	 */
	public function __construct( LanguageRelationshipService $relationships ) {
		$this->relationships = $relationships;
	}

	/**
	 * Registers head hooks.
	 */
	public function register(): void {
		add_filter( 'get_canonical_url', array( $this, 'filter_canonical_url' ), 30, 2 );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_rank_math_canonical' ), 20 );
		add_action( 'wp_head', array( $this, 'emit_hreflang' ), 2 );
	}

	/**
	 * Language-aware WP canonical (SB1/SB2).
	 *
	 * @param string        $canonical Existing canonical.
	 * @param \WP_Post|null $post     Post context.
	 * @return string
	 */
	public function filter_canonical_url( $canonical, $post = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! $this->should_manage_document_seo() ) {
			return $canonical;
		}

		return $this->resolve_canonical( is_string( $canonical ) ? $canonical : '' );
	}

	/**
	 * Language-aware Rank Math canonical (SB1/SB2).
	 *
	 * @param string $canonical Rank Math canonical.
	 * @return string
	 */
	public function filter_rank_math_canonical( $canonical ) {
		if ( ! $this->should_manage_document_seo() ) {
			return $canonical;
		}

		return $this->resolve_canonical( is_string( $canonical ) ? $canonical : '' );
	}

	/**
	 * Emits reciprocal alternate + x-default link tags (SB3/SB4).
	 */
	public function emit_hreflang(): void {
		if ( ! $this->should_manage_document_seo() ) {
			return;
		}

		$rels = $this->relationships->for_public_request();
		if ( count( $rels ) < 1 ) {
			return;
		}

		foreach ( $rels as $rel ) {
			printf(
				'<link rel="alternate" hreflang="%1$s" href="%2$s" />' . "\n",
				esc_attr( $rel->hreflang ),
				esc_url( $rel->url )
			);
		}

		$default = $this->relationships->default_public();
		if ( null !== $default ) {
			printf(
				'<link rel="alternate" hreflang="x-default" href="%1$s" />' . "\n",
				esc_url( $default->url )
			);
		}
	}

	/**
	 * SB2: keep cross-host overrides; otherwise use SB11 current URL.
	 *
	 * @param string $existing Candidate canonical.
	 */
	private function resolve_canonical( string $existing ): string {
		$current = $this->relationships->current_public();
		if ( null === $current ) {
			return $existing;
		}

		if ( '' !== $existing && $this->is_external_absolute( $existing ) ) {
			return $existing;
		}

		return $current->url;
	}

	/**
	 * True when URL host differs from site home host.
	 *
	 * @param string $url Absolute URL.
	 */
	private function is_external_absolute( string $url ): bool {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$home = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST );

		return '' !== $host && '' !== $home && 0 !== strcasecmp( $host, $home );
	}

	/**
	 * Frontend public document contexts only.
	 */
	private function should_manage_document_seo(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		return true;
	}
}
