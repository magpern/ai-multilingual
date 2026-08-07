<?php
/**
 * Pure Elementor settings overlay application.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

use AIMultilingual\Elementor\Strategy\ElementorStrategyFactory;

/**
 * Applies Store translations onto an Elementor data tree (in memory only).
 */
final class ElementorOverlayApplier {

	/**
	 * Strategy resolver.
	 *
	 * @var ElementorStrategyFactory
	 */
	private ElementorStrategyFactory $strategies;

	/**
	 * Builds the applier.
	 *
	 * @param ElementorControlRegistry      $registry     Control registry.
	 * @param ElementorDiagnostics|null     $diagnostics  Optional diagnostics.
	 * @param ElementorStrategyFactory|null $strategies   Optional strategy factory.
	 */
	public function __construct(
		private ElementorControlRegistry $registry,
		private ?ElementorDiagnostics $diagnostics = null,
		?ElementorStrategyFactory $strategies = null
	) {
		$this->strategies = $strategies ?? new ElementorStrategyFactory();
	}

	/**
	 * Mutate a copy of the tree with overlays; never throws for local unit failures.
	 *
	 * @param array<int, mixed>     $nodes     Elementor nodes.
	 * @param array<string, string> $overlays  segment_key => translated text.
	 * @param array                 $units     Translation units.
	 * @return array<int, mixed>
	 */
	public function apply( array $nodes, array $overlays, array $units ): array {
		$by_element = array();
		foreach ( $units as $unit ) {
			if ( $unit instanceof ElementorTranslationUnit ) {
				$by_element[ $unit->element_id ][] = $unit;
			}
		}

		$this->walk( $nodes, $overlays, $by_element );
		return $nodes;
	}

	/**
	 * Walk nodes applying overlays.
	 *
	 * @param array<int, mixed>                             $nodes      Elementor nodes.
	 * @param array<string, string>                         $overlays   Overlays.
	 * @param array<string, list<ElementorTranslationUnit>> $by_element Units by element ID.
	 */
	private function walk( array &$nodes, array $overlays, array $by_element ): void {
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
				$element_units = $by_element[ $element_id ] ?? array();
				foreach ( $this->registry->all() as $entry ) {
					if ( ( $entry['widget_type'] ?? '' ) !== $widget_type ) {
						continue;
					}

					$control_key = (string) ( $entry['control_key'] ?? '' );
					$matching    = array();
					foreach ( $element_units as $unit ) {
						if ( $unit->control_key === $control_key ) {
							$matching[] = $unit;
						}
					}
					if ( array() === $matching ) {
						continue;
					}

					$strategy = $this->strategies->for_entry( $entry );
					if ( null === $strategy ) {
						$this->diagnostics?->inc( 'adapter_failure' );
						continue;
					}

					$strategy->apply( $node['settings'], $entry, $overlays, $matching, $this->diagnostics );
				}
			}

			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->walk( $node['elements'], $overlays, $by_element );
			}
		}
		unset( $node );
	}
}
