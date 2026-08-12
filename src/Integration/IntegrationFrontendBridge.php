<?php
/**
 * Visitor overlay bridge for Integration API v1.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Settings;
use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermTranslationResolver;

/**
 * Registers integration output hooks when a non-default language is active.
 */
final class IntegrationFrontendBridge {

	/**
	 * Builds the frontend overlay bridge.
	 *
	 * @param Settings                     $settings      Settings.
	 * @param LanguageContext              $context       Language context.
	 * @param IntegrationRegistry          $registry      Registry.
	 * @param Store                        $store         Store.
	 * @param IntegrationDiagnostics       $diagnostics   Diagnostics.
	 * @param TermTranslationResolver|null $term_resolver Term native/hosted resolver (TSC.1).
	 */
	public function __construct(
		private Settings $settings,
		private LanguageContext $context,
		private IntegrationRegistry $registry,
		private Store $store,
		private IntegrationDiagnostics $diagnostics,
		private ?TermTranslationResolver $term_resolver = null,
	) {
	}

	/**
	 * Register the frontend bridge.
	 */
	public function register(): void {
		// Before Rank Math Frontend::integrations() on `wp` (priority 10) so
		// title/description/token filters are present before Paper caches values.
		add_action( 'wp', array( $this, 'on_wp' ), 5 );
	}

	/**
	 * Attach overlay hooks once the main query is available.
	 */
	public function on_wp(): void {
		if ( is_admin() ) {
			return;
		}

		// A.SEOd SD3/SD5/SD6: public social document hooks must run on every
		// published language, including the site default (locale alternates).
		// A.SEOe sitemap hooks are registered at Plugin boot (parse_query timing);
		// re-call here is idempotent.
		foreach ( $this->registry->all() as $integration ) {
			if ( $integration instanceof RankMathIntegration ) {
				$integration->register_public_social_hooks();
				$integration->register_sitemap_hooks();
			}
		}

		if ( $this->context->is_default() ) {
			return;
		}

		$language = $this->context->current();
		if ( null === $language ) {
			return;
		}

		$queried     = get_queried_object();
		$source_id   = $this->resolve_source_id( $queried );
		$language_id = (int) $language->language_id;
		$term_id     = 0;
		$taxonomy    = '';

		if ( is_object( $queried ) && isset( $queried->taxonomy, $queried->term_id ) ) {
			$taxonomy = (string) $queried->taxonomy;
			$term_id  = (int) $queried->term_id;
			if ( $term_id <= 0 || ! AdmittedTaxonomies::admits( $taxonomy ) ) {
				$term_id  = 0;
				$taxonomy = '';
			}
		}

		if ( $source_id <= 0 && $term_id <= 0 ) {
			return;
		}

		$resolve = function ( string $segment_key ) use ( $source_id, $language_id, $term_id, $taxonomy ): ?string {
			$row = $this->resolve_overlay_row( $segment_key, $source_id, $language_id, $term_id, $taxonomy );
			if ( null === $row || ! Store::is_publicly_overlay_eligible( $row ) ) {
				$this->diagnostics->increment( IntegrationDiagnostics::COUNTER_SOURCE_FALLBACK );
				return null;
			}
			$text = (string) ( $row->translated_text ?? '' );
			$this->diagnostics->increment( IntegrationDiagnostics::COUNTER_OVERLAY_APPLIED );
			return $text;
		};

		$this->registry->register_output_hooks( $resolve );
	}

	/**
	 * Native-first term row, else hosted post row for the segment key.
	 *
	 * @param string $segment_key Segment key.
	 * @param int    $source_id   Hosted post source id.
	 * @param int    $language_id Language id.
	 * @param int    $term_id     Queried term id when on a term archive.
	 * @param string $taxonomy    Queried taxonomy.
	 */
	private function resolve_overlay_row(
		string $segment_key,
		int $source_id,
		int $language_id,
		int $term_id,
		string $taxonomy
	): ?object {
		if ( $term_id > 0 && '' !== $taxonomy && null !== $this->term_resolver ) {
			$field = $this->term_resolver->term_field_for_segment_key( $segment_key );
			if ( null !== $field ) {
				$logical = (string) ( $field['logical_field'] ?? '' );
				if ( '' !== $logical ) {
					$resolved = $this->term_resolver->resolve( $term_id, $taxonomy, $logical, $language_id );
					if ( null !== $resolved ) {
						return $resolved['row'];
					}
				}
			}

			// Native name/description keys (post-adoption).
			if ( in_array( $segment_key, array( 'name', 'description' ), true ) ) {
				$resolved = $this->term_resolver->resolve( $term_id, $taxonomy, $segment_key, $language_id );
				if ( null !== $resolved ) {
					return $resolved['row'];
				}
			}
		}

		if ( $source_id <= 0 ) {
			return null;
		}

		return $this->store->get( Store::SOURCE_POST, $source_id, $language_id, $segment_key );
	}

	/**
	 * Resolve Store source_id for the current query.
	 *
	 * Posts/pages/products use the queried post ID. WooCommerce product_cat /
	 * product_tag archives and product search resolve against the shop page
	 * technical Store anchor (A.7a catalog + A.7b archive chrome).
	 *
	 * @param mixed $queried Queried object.
	 */
	private function resolve_source_id( $queried ): int {
		if ( $queried instanceof \WP_Post ) {
			return (int) $queried->ID;
		}

		if ( is_object( $queried ) && isset( $queried->taxonomy, $queried->term_id ) ) {
			$taxonomy = (string) $queried->taxonomy;
			if ( 'product_cat' === $taxonomy || 'product_tag' === $taxonomy ) {
				return $this->shop_page_source_id();
			}
			if ( 'category' === $taxonomy || 'post_tag' === $taxonomy ) {
				return $this->posts_page_source_id();
			}
			if ( AdmittedTaxonomies::admits( $taxonomy ) ) {
				return $this->shop_page_source_id() ?: $this->posts_page_source_id();
			}
		}

		if ( $this->is_woocommerce_product_search() ) {
			return $this->shop_page_source_id();
		}

		return 0;
	}

	/**
	 * WooCommerce shop page ID for technical Store anchoring.
	 */
	private function shop_page_source_id(): int {
		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop_id = (int) wc_get_page_id( 'shop' );
			return $shop_id > 0 ? $shop_id : 0;
		}
		return 0;
	}

	/**
	 * Posts page ID for category/post_tag Rank Math SEO hosting (A.SEOc SC5/SC6).
	 */
	private function posts_page_source_id(): int {
		$posts_page = (int) get_option( 'page_for_posts' );
		return $posts_page > 0 ? $posts_page : 0;
	}

	/**
	 * Whether the main query is a WooCommerce product search.
	 */
	private function is_woocommerce_product_search(): bool {
		if ( ! function_exists( 'is_search' ) || ! is_search() ) {
			return false;
		}
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : '';
		return 'product' === $post_type;
	}
}
