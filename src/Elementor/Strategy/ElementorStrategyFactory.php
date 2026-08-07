<?php
/**
 * Registry strategy resolver.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor\Strategy;

/**
 * Maps registry extractor/renderer strategy names to implementations.
 */
final class ElementorStrategyFactory {

	public const EXTRACTOR_SETTINGS_STRING = 'settings_string';

	public const EXTRACTOR_REPEATER_FIELD = 'repeater_field';

	public const EXTRACTOR_IMAGE_CUSTOM_CAPTION = 'image_custom_caption';

	/**
	 * Strategy instances keyed by name.
	 *
	 * @var array<string, ElementorControlStrategy>
	 */
	private array $strategies;

	/**
	 * Seeds built-in strategies.
	 */
	public function __construct() {
		$this->strategies = array(
			self::EXTRACTOR_SETTINGS_STRING       => new SettingsStringStrategy(),
			self::EXTRACTOR_REPEATER_FIELD         => new RepeaterFieldStrategy(),
			self::EXTRACTOR_IMAGE_CUSTOM_CAPTION  => new ImageCustomCaptionStrategy(),
		);
	}

	/**
	 * Resolve strategy for a registry entry (extractor name).
	 *
	 * @param array<string, mixed> $entry Registry entry.
	 */
	public function for_entry( array $entry ): ?ElementorControlStrategy {
		$name = (string) ( $entry['extractor'] ?? self::EXTRACTOR_SETTINGS_STRING );
		return $this->strategies[ $name ] ?? null;
	}
}
