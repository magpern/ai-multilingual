<?php
/**
 * Flush Elementor file/document caches for language-safe overlays.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Settings;

/**
 * Minimum cache awareness for Elementor overlays — does not enable AIML render cache.
 *
 * Elementor's `_elementor_element_cache` document HTML cache is language-agnostic.
 * When AIML overlays are enabled it must not be served across languages.
 */
final class ElementorCacheInvalidation {

	public const DOCUMENT_CACHE_META = '_elementor_element_cache';

	/**
	 * Builds the invalidation hook.
	 *
	 * @param ElementorDocumentDetector $detector      Document detector.
	 * @param ElementorCompatibility    $compatibility Compatibility boundary.
	 * @param Settings                  $settings      Plugin settings.
	 * @param LanguageContext|null      $language      Request language (optional).
	 */
	public function __construct(
		private ElementorDocumentDetector $detector,
		private ElementorCompatibility $compatibility,
		private Settings $settings,
		private ?LanguageContext $language = null
	) {}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'aiml_translation_saved', array( $this, 'on_translation_saved' ), 20, 3 );

		if ( $this->settings->elementor_frontend_rendering_enabled() ) {
			// Elementor's document HTML cache is language-agnostic; disable it while
			// AIML overlays are active so EN/SV cannot share rendered HTML.
			add_filter( 'pre_option_elementor_element_cache_ttl', array( $this, 'disable_elementor_element_cache' ), 10, 1 );
			add_action( 'elementor/frontend/before_get_builder_content', array( $this, 'bust_document_element_cache' ), 1, 2 );
			add_filter( 'elementor/element_cache/unique_id', array( $this, 'language_unique_id' ), 20 );
		}
	}

	/**
	 * Force Elementor element-cache TTL to disabled while AIML overlays are on.
	 *
	 * @param mixed $value Existing option short-circuit value.
	 * @return mixed
	 */
	public function disable_elementor_element_cache( $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return 'disable';
	}

	/**
	 * Bust Elementor file/CSS caches for Elementor documents after overlay save.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 * @param int    $language_id Language id.
	 */
	public function on_translation_saved( string $source_type, int $source_id, int $language_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( 'post' !== $source_type || $source_id <= 0 ) {
			return;
		}

		if ( ! $this->compatibility->is_elementor_available() ) {
			return;
		}

		if ( ! $this->detector->is_elementor_document( $source_id ) ) {
			return;
		}

		$this->delete_document_element_cache( $source_id );
		$this->clear_elementor_files_cache();
	}

	/**
	 * Drop language-agnostic Elementor document HTML cache before render.
	 *
	 * @param mixed $document Elementor document.
	 * @param mixed $_is_excerpt Excerpt flag.
	 */
	public function bust_document_element_cache( $document, $_is_excerpt = false ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! $this->settings->elementor_frontend_rendering_enabled() ) {
			return;
		}

		$post_id = 0;
		if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
			$post_id = (int) $document->get_main_id();
		}

		if ( $post_id <= 0 ) {
			return;
		}

		$this->delete_document_element_cache( $post_id );
	}

	/**
	 * Make Elementor shortcode element-cache unique IDs language-aware.
	 *
	 * @param string $unique_id Existing unique id.
	 */
	public function language_unique_id( string $unique_id ): string {
		$code = 'default';
		if ( null !== $this->language && null !== $this->language->current() ) {
			$code = (string) ( $this->language->current()->code ?? 'default' );
		}

		return $unique_id . '|aiml:' . $code;
	}

	/**
	 * Delete `_elementor_element_cache` for one post.
	 *
	 * @param int $post_id Post ID.
	 */
	public function delete_document_element_cache( int $post_id ): void {
		if ( $post_id <= 0 ) {
			return;
		}

		delete_post_meta( $post_id, self::DOCUMENT_CACHE_META );
	}

	/**
	 * Best-effort Elementor files manager clear — never throws.
	 */
	public function clear_elementor_files_cache(): void {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		$plugin = \Elementor\Plugin::$instance ?? null;
		if ( null === $plugin || ! isset( $plugin->files_manager ) ) {
			return;
		}

		if ( is_object( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
			$plugin->files_manager->clear_cache();
		}
	}
}
