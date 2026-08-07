<?php
/**
 * Elementor control extract/render strategy contract.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor\Strategy;

use AIMultilingual\Elementor\ElementorDiagnostics;
use AIMultilingual\Elementor\ElementorIdentity;
use AIMultilingual\Elementor\ElementorTranslationUnit;

/**
 * Registry-dispatched extract/apply behaviour for one control entry.
 */
interface ElementorControlStrategy {

	/**
	 * Extract zero or more units from widget settings for one registry entry.
	 *
	 * @param int                      $owner_post_id Owner post.
	 * @param string                   $element_id    Element ID.
	 * @param string                   $widget_type   Widget type.
	 * @param array<string, mixed>     $settings      Widget settings.
	 * @param array<string, mixed>     $entry         Registry entry.
	 * @param ElementorIdentity        $identity      Identity builder.
	 * @param ElementorDiagnostics|null $diagnostics  Optional diagnostics.
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
	): array;

	/**
	 * Apply overlays onto settings for matching units (local failure → skip).
	 *
	 * @param array<string, mixed>                   $settings    Widget settings (by ref).
	 * @param array<string, mixed>                   $entry       Registry entry.
	 * @param array<string, string>                  $overlays    segment_key => text.
	 * @param list<ElementorTranslationUnit>         $units       Units for this element/control.
	 * @param ElementorDiagnostics|null              $diagnostics Optional diagnostics.
	 */
	public function apply(
		array &$settings,
		array $entry,
		array $overlays,
		array $units,
		?ElementorDiagnostics $diagnostics = null
	): void;
}
