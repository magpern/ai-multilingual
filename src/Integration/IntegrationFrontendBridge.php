<?php
/**
 * Visitor overlay bridge for Integration API v1.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Store;

/**
 * Registers integration output hooks when a non-default language is active.
 */
final class IntegrationFrontendBridge {

	/**
	 * Builds the frontend overlay bridge.
	 *
	 * @param Settings               $settings    Settings.
	 * @param LanguageContext        $context     Language context.
	 * @param IntegrationRegistry    $registry    Registry.
	 * @param Store                  $store       Store.
	 * @param IntegrationDiagnostics $diagnostics Diagnostics.
	 */
	public function __construct(
		private Settings $settings,
		private LanguageContext $context,
		private IntegrationRegistry $registry,
		private Store $store,
		private IntegrationDiagnostics $diagnostics,
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
		if ( is_admin() || $this->context->is_default() ) {
			return;
		}

		$language = $this->context->current();
		if ( null === $language ) {
			return;
		}

		$source_id = $this->resolve_source_id( get_queried_object() );
		if ( $source_id <= 0 ) {
			return;
		}

		$language_id = (int) $language->language_id;

		$resolve = function ( string $segment_key ) use ( $source_id, $language_id ): ?string {
			$row = $this->store->get( 'post', $source_id, $language_id, $segment_key );
			if ( null === $row ) {
				$this->diagnostics->increment( IntegrationDiagnostics::COUNTER_SOURCE_FALLBACK );
				return null;
			}
			$text = (string) ( $row->translated_text ?? '' );
			if ( '' === $text ) {
				$this->diagnostics->increment( IntegrationDiagnostics::COUNTER_SOURCE_FALLBACK );
				return null;
			}
			$this->diagnostics->increment( IntegrationDiagnostics::COUNTER_OVERLAY_APPLIED );
			return $text;
		};

		$this->registry->register_output_hooks( $resolve );
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
