<?php
/**
 * Flush Elementor file caches after AIML overlay saves (language-safe).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

/**
 * Minimum cache awareness for Elementor overlays — does not enable AIML render cache.
 */
final class ElementorCacheInvalidation {

	/**
	 * Builds the invalidation hook.
	 *
	 * @param ElementorDocumentDetector $detector      Document detector.
	 * @param ElementorCompatibility    $compatibility Compatibility boundary.
	 */
	public function __construct(
		private ElementorDocumentDetector $detector,
		private ElementorCompatibility $compatibility
	) {}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'aiml_translation_saved', array( $this, 'on_translation_saved' ), 20, 3 );
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

		$this->clear_elementor_files_cache();
	}

	/**
	 * Best-effort Elementor files manager clear — never throws.
	 */
	public function clear_elementor_files_cache(): void {
		try {
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
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Local failure: overlays still request-scoped via LanguageContext.
		}
	}
}
