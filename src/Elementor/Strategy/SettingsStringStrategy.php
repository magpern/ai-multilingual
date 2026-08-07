<?php
/**
 * Flat settings-string extract/render strategy (A.2).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor\Strategy;

use AIMultilingual\Elementor\ElementorControlRegistry;
use AIMultilingual\Elementor\ElementorDiagnostics;
use AIMultilingual\Elementor\ElementorIdentity;
use AIMultilingual\Elementor\ElementorTranslationUnit;
use AIMultilingual\Translation\Store;

/**
 * Reads/writes a single string setting by control key.
 */
final class SettingsStringStrategy implements ElementorControlStrategy {

		/**
		 * Extract zero or more units for one registry entry.
		 *
		 * @param int                       $owner_post_id Owner post.
		 * @param string                    $element_id    Element ID.
		 * @param string                    $widget_type   Widget type.
		 * @param array<string, mixed>      $settings      Widget settings.
		 * @param array<string, mixed>      $entry         Registry entry.
		 * @param ElementorIdentity         $identity      Identity builder.
		 * @param ElementorDiagnostics|null $diagnostics   Optional diagnostics.
		 * @return list<ElementorTranslationUnit>
		 */
	public function extract(
		int $owner_post_id,
		string $element_id,
		string $widget_type,
		array $settings,
		array $entry,
		ElementorIdentity $identity,
		?ElementorDiagnostics $diagnostics = null
	): array {
		$control_key = (string) ( $entry['control_key'] ?? '' );
		if ( '' === $control_key || ! array_key_exists( $control_key, $settings ) ) {
			return array();
		}

		$value = $settings[ $control_key ];
		if ( ! is_string( $value ) ) {
			$diagnostics?->inc( 'identity_error' );
			return array();
		}

		if ( '' === trim( $value ) ) {
			return array();
		}

		$key = $identity->build( $owner_post_id, $element_id, $control_key );
		if ( '' === $key ) {
			$diagnostics?->inc( 'identity_error' );
			return array();
		}

		$format = (string) ( $entry['text_format'] ?? Store::FORMAT_PLAIN );
		$diagnostics?->inc( 'supported_unit_extracted' );

		return array(
			new ElementorTranslationUnit(
				$key,
				$owner_post_id,
				$element_id,
				$widget_type,
				$control_key,
				$value,
				Store::source_hash( $value, $format ),
				$format
			),
		);
	}

		/**
		 * Apply overlays onto settings for matching units.
		 *
		 * @param array<string, mixed>                 $settings    Widget settings (by ref).
		 * @param array<string, mixed>                 $entry       Registry entry.
		 * @param array<string, string>                $overlays    segment_key => text.
		 * @param array<int, ElementorTranslationUnit> $units       Units for this element/control.
		 * @param ElementorDiagnostics|null            $diagnostics Optional diagnostics.
		 */
	public function apply(
		array &$settings,
		array $entry,
		array $overlays,
		array $units,
		?ElementorDiagnostics $diagnostics = null
	): void {
		$control_key = (string) ( $entry['control_key'] ?? '' );
		if ( '' === $control_key ) {
			return;
		}

		foreach ( $units as $unit ) {
			if ( ! $unit instanceof ElementorTranslationUnit ) {
				continue;
			}
			if ( $unit->control_key !== $control_key || null !== $unit->nested_item_id ) {
				continue;
			}

			$translated = $overlays[ $unit->segment_key ] ?? null;
			if ( null === $translated ) {
				continue;
			}

			$settings[ $control_key ] = ElementorSanitize::apply(
				$translated,
				(string) ( $entry['sanitization'] ?? ElementorControlRegistry::SANITIZE_PLAIN )
			);
			$diagnostics?->inc( 'overlay_applied' );
		}
	}
}
