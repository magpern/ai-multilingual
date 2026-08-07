<?php
/**
 * Request-time Elementor frontend overlay bridge.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Settings;

/**
 * Applies Store overlays via Elementor builder_content_data (no HTML scrape).
 */
final class ElementorFrontendBridge {

	public const HOOK = 'elementor/frontend/builder_content_data';

	/**
	 * Builds the frontend bridge.
	 *
	 * @param Settings                  $settings      Plugin settings.
	 * @param LanguageContext           $language      Request language.
	 * @param ElementorCompatibility    $compatibility Compatibility boundary.
	 * @param ElementorDocumentDetector $detector      Document detector.
	 * @param ElementorExtractor        $extractor     Unit extractor.
	 * @param ElementorOverlayResolver  $resolver      Store resolver.
	 * @param ElementorOverlayApplier   $applier       Tree applier.
	 * @param ElementorDiagnostics|null $diagnostics   Optional diagnostics.
	 */
	public function __construct(
		private Settings $settings,
		private LanguageContext $language,
		private ElementorCompatibility $compatibility,
		private ElementorDocumentDetector $detector,
		private ElementorExtractor $extractor,
		private ElementorOverlayResolver $resolver,
		private ElementorOverlayApplier $applier,
		private ?ElementorDiagnostics $diagnostics = null
	) {}

	/**
	 * Register frontend hooks when Elementor overlays are enabled.
	 */
	public function register(): void {
		if ( ! $this->settings->elementor_frontend_rendering_enabled() ) {
			return;
		}

		if ( ! $this->compatibility->overlays_allowed() ) {
			return;
		}

		add_filter( self::HOOK, array( $this, 'filter_builder_content_data' ), 20, 2 );
	}

	/**
	 * Overlay allowlisted settings on Elementor document data for the request language.
	 *
	 * @param mixed $data     Elementor data tree.
	 * @param mixed $document Elementor document object.
	 * @return mixed
	 */
	public function filter_builder_content_data( $data, $document = null ) {
		$original = $data;

		try {
			if ( ! is_array( $data ) ) {
				return $data;
			}

			if ( function_exists( 'is_admin' ) && is_admin() && ! ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
				return $data;
			}

			if ( ! $this->settings->elementor_frontend_rendering_enabled() ) {
				return $data;
			}

			if ( ! $this->compatibility->overlays_allowed() ) {
				return $data;
			}

			$language_id = (int) $this->language->current_id();
			if ( $language_id <= 0 || $this->language->is_default() ) {
				return $data;
			}

			$post_id = $this->resolve_document_post_id( $document );
			if ( $post_id <= 0 || ! $this->detector->is_elementor_document( $post_id ) ) {
				return $data;
			}

			$units = $this->extractor->extract( $post_id );
			if ( array() === $units ) {
				return $data;
			}

			$overlays = $this->resolver->resolve( $post_id, $language_id, $units );
			if ( array() === $overlays ) {
				return $data;
			}

			return $this->applier->apply( $data, $overlays, $units );
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			$this->diagnostics?->inc( 'source_fallback' );
			return $original;
		}
	}

	/**
	 * Resolve owning post ID from an Elementor document object.
	 *
	 * @param mixed $document Elementor document or null.
	 */
	private function resolve_document_post_id( $document ): int {
		if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
			$id = (int) $document->get_main_id();
			if ( $id > 0 ) {
				return $id;
			}
		}

		if ( is_object( $document ) && method_exists( $document, 'get_post' ) ) {
			$post = $document->get_post();
			if ( $post instanceof \WP_Post ) {
				return (int) $post->ID;
			}
		}

		return function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
	}
}
