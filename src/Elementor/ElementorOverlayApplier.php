<?php
/**
 * Pure Elementor settings overlay application.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

/**
 * Applies Store translations onto an Elementor data tree (in memory only).
 */
final class ElementorOverlayApplier {

	/**
	 * Builds the applier.
	 *
	 * @param ElementorControlRegistry  $registry     Control registry.
	 * @param ElementorDiagnostics|null $diagnostics Optional diagnostics.
	 */
	public function __construct(
		private ElementorControlRegistry $registry,
		private ?ElementorDiagnostics $diagnostics = null
	) {}

	/**
	 * Mutate a copy of the tree with overlays; never throws for local unit failures.
	 *
	 * @param array<int, mixed>     $nodes     Elementor nodes.
	 * @param array<string, string> $overlays  segment_key => translated text.
	 * @param array                 $units     Translation units.
	 * @return array<int, mixed>
	 */
	public function apply( array $nodes, array $overlays, array $units ): array {
		$index = array();
		foreach ( $units as $unit ) {
			if ( $unit instanceof ElementorTranslationUnit ) {
				$index[ $unit->element_id . "\0" . $unit->control_key ] = $unit;
			}
		}

		$this->walk( $nodes, $overlays, $index );
		return $nodes;
	}

	/**
	 * Walk nodes applying overlays.
	 *
	 * @param array<int, mixed>                       $nodes    Elementor nodes.
	 * @param array<string, string>                   $overlays Overlays.
	 * @param array<string, ElementorTranslationUnit> $index    Unit index.
	 */
	private function walk( array &$nodes, array $overlays, array $index ): void {
		foreach ( $nodes as &$node ) {
			if ( ! is_array( $node ) ) {
				$this->diagnostics?->inc( 'source_fallback' );
				continue;
			}

			$widget_type = isset( $node['widgetType'] ) && is_string( $node['widgetType'] )
				? $node['widgetType']
				: '';
			$element_id  = isset( $node['id'] ) && is_string( $node['id'] ) ? $node['id'] : '';

			if ( '' !== $widget_type && '' !== $element_id && isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
				foreach ( $this->registry->all() as $entry ) {
					if ( ( $entry['widget_type'] ?? '' ) !== $widget_type ) {
						continue;
					}

					$control_key = (string) ( $entry['control_key'] ?? '' );
					$unit        = $index[ $element_id . "\0" . $control_key ] ?? null;
					if ( null === $unit ) {
						continue;
					}

					$translated = $overlays[ $unit->segment_key ] ?? null;
					if ( null === $translated ) {
						continue;
					}

					try {
						$node['settings'][ $control_key ] = $this->sanitize(
							$translated,
							(string) ( $entry['sanitization'] ?? ElementorControlRegistry::SANITIZE_PLAIN )
						);
						$this->diagnostics?->inc( 'overlay_applied' );
					} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
						$this->diagnostics?->inc( 'source_fallback' );
					}
				}
			}

			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->walk( $node['elements'], $overlays, $index );
			}
		}
		unset( $node );
	}

	/**
	 * Sanitize overlay text for a control.
	 *
	 * @param string $text     Translated text.
	 * @param string $strategy Sanitization strategy.
	 */
	private function sanitize( string $text, string $strategy ): string {
		if ( ElementorControlRegistry::SANITIZE_HTML === $strategy ) {
			return function_exists( 'wp_kses_post' ) ? wp_kses_post( $text ) : $text;
		}

		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $text ) : trim( $text );
	}
}
