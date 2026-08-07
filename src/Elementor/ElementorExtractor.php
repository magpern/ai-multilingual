<?php
/**
 * Extract translation units from Elementor documents.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

use AIMultilingual\Translation\Store;

/**
 * Read-only extractor over `_elementor_data`.
 */
final class ElementorExtractor {

	/**
	 * Builds the extractor.
	 *
	 * @param ElementorDocumentDetector $detector    Document detector.
	 * @param ElementorControlRegistry  $registry    Control registry.
	 * @param ElementorIdentity         $identity    Identity builder.
	 * @param ElementorDiagnostics|null $diagnostics Optional diagnostics.
	 */
	public function __construct(
		private ElementorDocumentDetector $detector,
		private ElementorControlRegistry $registry,
		private ElementorIdentity $identity,
		private ?ElementorDiagnostics $diagnostics = null
	) {}

	/**
	 * Extract supported units from a document.
	 *
	 * Local failure: malformed units are skipped; extraction continues.
	 *
	 * @param int $post_id Owner post ID.
	 * @return list<ElementorTranslationUnit>
	 */
	public function extract( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return array();
		}

		if ( ! $this->detector->is_elementor_document( $post_id ) ) {
			return array();
		}

		$this->diagnostics?->inc( 'eligible_document' );

		$elements = $this->detector->decode_elements( $post_id );
		if ( null === $elements ) {
			$this->diagnostics?->inc( 'identity_error' );
			return array();
		}

		return $this->extract_from_elements( $post_id, $elements );
	}

	/**
	 * Extract from a decoded Elementor tree (unit-testable without post meta).
	 *
	 * @param int               $post_id  Owner post ID.
	 * @param array<int, mixed> $elements Decoded tree.
	 * @return list<ElementorTranslationUnit>
	 */
	public function extract_from_elements( int $post_id, array $elements ): array {
		$units = array();
		$this->walk( $elements, $post_id, $units );
		return $units;
	}

	/**
	 * Recursively walk Elementor nodes.
	 *
	 * @param array<int, mixed> $nodes   Elementor nodes.
	 * @param int               $post_id Owner post ID.
	 * @param array             $units   Accumulator.
	 */
	private function walk( array $nodes, int $post_id, array &$units ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				$this->diagnostics?->inc( 'identity_error' );
				continue;
			}

			$widget_type = isset( $node['widgetType'] ) && is_string( $node['widgetType'] )
				? $node['widgetType']
				: '';
			$element_id  = isset( $node['id'] ) && is_string( $node['id'] ) ? $node['id'] : '';
			$settings    = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();

			if ( '' !== $widget_type ) {
				if ( ! $this->registry->is_supported_widget( $widget_type ) ) {
					$this->diagnostics?->inc( 'unsupported_widget_skipped' );
				} else {
					$this->extract_controls( $post_id, $element_id, $widget_type, $settings, $units );
				}
			}

			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->walk( $node['elements'], $post_id, $units );
			}
		}
	}

	/**
	 * Extract allowlisted controls from one widget.
	 *
	 * @param int                  $post_id     Owner post ID.
	 * @param string               $element_id  Element ID.
	 * @param string               $widget_type Widget type.
	 * @param array<string, mixed> $settings    Widget settings.
	 * @param array                $units       Accumulator.
	 */
	private function extract_controls(
		int $post_id,
		string $element_id,
		string $widget_type,
		array $settings,
		array &$units
	): void {
		foreach ( $this->registry->all() as $entry ) {
			if ( ( $entry['widget_type'] ?? '' ) !== $widget_type ) {
				continue;
			}

			$control_key = (string) ( $entry['control_key'] ?? '' );
			if ( '' === $control_key || ! array_key_exists( $control_key, $settings ) ) {
				continue;
			}

			$value = $settings[ $control_key ];
			if ( ! is_string( $value ) ) {
				$this->diagnostics?->inc( 'identity_error' );
				continue;
			}

			if ( '' === trim( $value ) ) {
				continue;
			}

			$key = $this->identity->build( $post_id, $element_id, $control_key );
			if ( '' === $key ) {
				$this->diagnostics?->inc( 'identity_error' );
				continue;
			}

			$format  = (string) ( $entry['text_format'] ?? Store::FORMAT_PLAIN );
			$units[] = new ElementorTranslationUnit(
				$key,
				$post_id,
				$element_id,
				$widget_type,
				$control_key,
				$value,
				Store::source_hash( $value, $format ),
				$format
			);
			$this->diagnostics?->inc( 'supported_unit_extracted' );
		}

		// Count unsupported controls present on supported widgets (best-effort).
		foreach ( array_keys( $settings ) as $setting_key ) {
			if ( ! is_string( $setting_key ) ) {
				continue;
			}
			if ( ! $this->registry->is_supported( $widget_type, $setting_key ) && is_string( $settings[ $setting_key ] ?? null ) ) {
				// Only count string-looking controls once per unsupported key presence — bounded.
				$this->diagnostics?->inc( 'unsupported_control_skipped' );
			}
		}
	}
}
