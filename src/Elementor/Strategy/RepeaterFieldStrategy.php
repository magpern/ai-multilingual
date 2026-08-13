<?php
/**
 * Repeater nested-field extract/render strategy (A.3).
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
 * Extracts nested string fields keyed by Elementor repeater `_id`.
 *
 * Missing/empty `_id` → skip (source). Duplicate `_id` → deny entire repeater
 * for this entry (source fallback + diagnostic).
 */
final class RepeaterFieldStrategy implements ElementorControlStrategy {

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
		$control_key  = (string) ( $entry['control_key'] ?? '' );
		$repeater_key = (string) ( $entry['repeater_key'] ?? '' );
		if ( '' === $control_key || '' === $repeater_key ) {
			$diagnostics?->inc( 'adapter_failure' );
			return array();
		}

		$rows = $settings[ $repeater_key ] ?? null;
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$ids = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$raw_id = isset( $row['_id'] ) && is_string( $row['_id'] ) ? trim( $row['_id'] ) : '';
			if ( '' === $raw_id ) {
				$diagnostics?->inc( 'missing_nested_id' );
				continue;
			}
			$ids[] = $raw_id;
		}

		$unique = array_unique( $ids );
		if ( count( $ids ) !== count( $unique ) ) {
			$diagnostics?->inc( 'duplicate_nested_id' );
			$diagnostics?->inc( 'source_fallback' );
			return array();
		}

		$format = (string) ( $entry['text_format'] ?? Store::FORMAT_PLAIN );
		$units  = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				$diagnostics?->inc( 'identity_error' );
				continue;
			}

			$nested_id = isset( $row['_id'] ) && is_string( $row['_id'] ) ? trim( $row['_id'] ) : '';
			if ( '' === $nested_id ) {
				continue;
			}

			if ( ! $identity->is_safe_token( $nested_id ) ) {
				$diagnostics?->inc( 'identity_error' );
				continue;
			}

			if ( ! array_key_exists( $control_key, $row ) ) {
				continue;
			}

			$value = $row[ $control_key ];
			if ( ! is_string( $value ) ) {
				$diagnostics?->inc( 'identity_error' );
				continue;
			}

			if ( '' === trim( $value ) ) {
				continue;
			}

			$key = $identity->build_nested( $owner_post_id, $element_id, $control_key, $nested_id );
			if ( '' === $key ) {
				$diagnostics?->inc( 'identity_error' );
				continue;
			}

			$units[] = new ElementorTranslationUnit(
				$key,
				$owner_post_id,
				$element_id,
				$widget_type,
				$control_key,
				$value,
				Store::source_hash( $value, $format ),
				$format,
				$nested_id
			);
			$diagnostics?->inc( 'supported_unit_extracted' );
			$diagnostics?->inc( 'nested_unit_extracted' );
		}

		return $units;
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
		$control_key  = (string) ( $entry['control_key'] ?? '' );
		$repeater_key = (string) ( $entry['repeater_key'] ?? '' );
		if ( '' === $control_key || '' === $repeater_key || ! isset( $settings[ $repeater_key ] ) || ! is_array( $settings[ $repeater_key ] ) ) {
			return;
		}

		$by_nested = array();
		foreach ( $units as $unit ) {
			if ( ! $unit instanceof ElementorTranslationUnit ) {
				continue;
			}
			if ( $unit->control_key !== $control_key || null === $unit->nested_item_id || '' === $unit->nested_item_id ) {
				continue;
			}
			$by_nested[ $unit->nested_item_id ] = $unit;
		}

		if ( array() === $by_nested ) {
			return;
		}

		foreach ( $settings[ $repeater_key ] as &$row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$nested_id = isset( $row['_id'] ) && is_string( $row['_id'] ) ? trim( $row['_id'] ) : '';
			if ( '' === $nested_id || ! isset( $by_nested[ $nested_id ] ) ) {
				continue;
			}

			$unit       = $by_nested[ $nested_id ];
			$translated = $overlays[ $unit->segment_key ] ?? null;
			if ( null === $translated ) {
				continue;
			}

			$sanitize     = (string) ( $entry['sanitization'] ?? ElementorControlRegistry::SANITIZE_PLAIN );
			$source_value = isset( $row[ $control_key ] ) && is_string( $row[ $control_key ] )
				? $row[ $control_key ]
				: '';

			$row[ $control_key ] = ElementorStructuralApply::apply(
				$source_value,
				$translated,
				$sanitize,
				$diagnostics
			);
			$diagnostics?->inc( 'overlay_applied' );
		}
		unset( $row );
	}
}
